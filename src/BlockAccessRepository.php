<?php

/**
 * @file
 * Contains Drupal\block_access_records\BlockAccessRepository.
 */

namespace Drupal\block_access_records;

use Drupal\block\BlockInterface;
use Drupal\block\BlockRepositoryInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Condition\ConditionInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Theme\ThemeManagerInterface;

/**
 * A block repository that uses access records to determine visibility.
 */
class BlockAccessRepository implements BlockRepositoryInterface {
  use StringTranslationTrait;

  /**
   * The block storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface $blockStorage
   */
  protected $blockStorage;

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface $themeManager
   */
  protected $themeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection $connection
   */
  protected $connection;

  /**
   * The access record plugin manager.
   *
   * @var BlockAccessRecordsPluginManager $recordPluginManager
   */
  protected $recordPluginManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandler $moduleHandler
   */
  protected $moduleHandler;

  /**
   * BlockAccessRepository constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\block_access_records\BlockAccessRecordsPluginManager $record_plugin_manager
   *   The access record plugin manager.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   The string translation interface.
   */
  public function __construct(EntityTypeManagerInterface $type_manager, ThemeManagerInterface $theme_manager, Connection $connection, BlockAccessRecordsPluginManager $record_plugin_manager, TranslationInterface $translation, ModuleHandler $module_handler) {
    $this->blockStorage = $type_manager->getStorage('block');
    $this->themeManager = $theme_manager;
    $this->connection = $connection;
    $this->recordPluginManager = $record_plugin_manager;
    $this->stringTranslation = $translation;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public function getVisibleBlocksPerRegion(array &$cacheable_metadata_list = []) {
    // Just create one item of metadata, they'll get merged anyhow.
    // TODO: Copy this per-region?
    $cacheable_metadata = new CacheableMetadata();
    // If the block config changes, so do our results.
    $cacheable_metadata->addCacheTags(['config:block_list']);
    $cacheable_metadata_list[] = $cacheable_metadata;

    // Get a list of regions.
    $regions = array_fill_keys($this->themeManager->getActiveTheme()
      ->getRegions(), []);
    // Get a superset of blocks that may be visible, based on access records.
    $block_ids = $this->getVisibleBlocks($cacheable_metadata);
    $visible = [];

    if (!empty($block_ids)) {
      $account = \Drupal::currentUser();

      // Load only the blocks we think are likely to be visible.
      $blocks = $this->blockStorage->loadMultiple($block_ids);

      // Assign blocks to regions.
      foreach ($blocks as $block_id => $block) {
        /** @var \Drupal\block\BlockInterface $block */
        $region = $block->getRegion();
        // Check that the region exists before doing an expensive access check.
        if (isset($regions[$region])) {
          // Check if any hooks forbid this block.
          if ($this->hooksForbid($block, $account)) {
            continue;
          }

          // Check if the plugin forbids this block.
          if ($block->getPlugin()->access($account, TRUE)->isForbidden()) {
            continue;
          }
          $visible[$block_id] = $block;
        }
      }
    }

    // Allow modules to change the visible blocks.
    $this->moduleHandler->alter('block_visibility', $visible);

    // Sort the blocks.
    foreach ($visible as $id => $block) {
      $regions[$block->getRegion()][$id] = $block;
    }
    foreach ($regions as &$region) {
      uasort($region, 'Drupal\block\Entity\Block::sort');
    }

    return $regions;
  }

  /**
   * Check if hooks forbid a block.
   */
  protected function hooksForbid(BlockInterface $block, $account) {
    foreach (['entity_access', 'block_access'] as $hook) {
      $access = $this->moduleHandler->invokeAll($hook, [$block, 'view', $account]);
      foreach ($access as $result) {
        if ($result->isForbidden()) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * Get the blocks that are likely to be visible, based on access records.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheable_metadata
   *   The cacheable metadata.
   *
   * @return string[]
   *   A list of block IDs.
   */
  protected function getVisibleBlocks(CacheableMetadata $cacheable_metadata) {
    /** @var \Drupal\Core\Database\Query\SelectInterface $query */
    $query = $this->connection->select('block_access_records', 'base')
      ->groupBy('base.block')
      ->fields('base', ['block']);

    // Is this the initial query, with no joins?
    $initial = TRUE;

    foreach ($this->recordPluginManager->getInstances() as $instance) {
      /** @var \Drupal\block_access_records\BlockAccessRecordsPluginInterface $instance */
      $values = $instance->currentContext($cacheable_metadata);
      foreach ($instance->contexts() as $context) {
        // For each context, get the context values. Default to empty.
        $cvals = isset($values[$context]) ? $values[$context] : [];

        if ($initial) {
          // For the initial context, we already have the table.
          $alias = 'base';
          $initial = FALSE;
        }
        else {
          // For each context, do another join against our table,
          // to add our condition.
          $alias = $context;
          $query->join('block_access_records', $alias,
            "base.block = $alias.block");
        }

        // If the record value is NULL, that means it's unfiltered. Otherwise,
        // the value must match the context.
        $group = $query->orConditionGroup();
        $group->isNull("$alias.value");
        if (!empty($cvals)) {
          $instance->addCondition($context, $cvals, $group, "$alias.value");
        }

        $query->condition($group);
        $query->condition("$alias.context", $context);

        // Negated conditions always have a row with record value NULL, so
        // they will always match at least one row.
        // If they match more rows, then they matched an anti-condition,
        // so exclude that block.
        $query->having("SUM($alias.negate) <= 1");
      }
    }

    return $query->execute()->fetchCol();
  }

  /**
   * Rebuild all block access records.
   *
   * @return \Drupal\block_access_records\BlockAccessVisibilityException[]
   *   Any errors that were thrown.
   */
  public function rebuildAccessRecords() {
    $exceptions = [];
    foreach ($this->blockStorage->loadMultiple() as $block) {
      try {
        $this->updateAccessRecords($block);
      }
      catch (\Exception $e) {
        $exceptions[] = $e;
      }
    }
    return $exceptions;
  }

  /**
   * Update the access records for a block.
   *
   * May either delete the records, or delete them and replace them with
   * updated records.
   *
   * @param \Drupal\block\BlockInterface $block
   *   The block for which to update.
   * @param bool $delete_only
   *   Whether to only delete records for this block.
   */
  public function updateAccessRecords(BlockInterface $block, $delete_only = FALSE) {
    try {
      // Calculate new values.
      $rows = $delete_only ? [] : $this->buildAccessRows($block);

      // Ensure deleting and re-adding happen together.
      $transaction = $this->connection->startTransaction();

      // Delete old items.
      $id = $block->id();
      $this->connection->delete('block_access_records')
        ->condition('block', $id)
        ->execute();

      // Add new items.
      if (!empty($rows)) {
        $insert = $this->connection->insert('block_access_records')
          ->fields(['block', 'context', 'value', 'negate']);
        foreach ($rows as $row) {
          $insert->values($row);
        }
        $insert->execute();
      }
    } catch (\Exception $e) {
      if (!empty($transaction)) {
        $transaction->rollback();
      }
      throw $e;
    }
  }

  /**
   * Get the visibility conditions a plugin handles.
   *
   * @param $plugin
   *   The plugin.
   */
  protected function handledConditions(BlockAccessRecordsPluginInterface $plugin) {
    if (method_exists($plugin, 'visibilityParents')) {
      $parents = $plugin->visibilityParents();
      return [reset($parents)];
    }
    return [];
  }

  /**
   * Build the access records for a block.
   *
   * @param \Drupal\block\BlockInterface $block
   *   The block.
   *
   * @return BlockAccessRecord[]
   *   The new access records.
   */
  protected function buildAccessRecords(BlockInterface $block, &$errors = []) {
    $visibility = $block->getVisibility();

    // Ask each plugin for records.
    $return = [];
    foreach ($this->recordPluginManager->getInstances() as $instance) {
      /** @var BlockAccessRecordsPluginInterface $instance */
      $contexts = array_fill_keys($instance->contexts(), 1);

      try {
        $records = $instance->accessRecords($block, $visibility);

        // Ensure records match the contexts reported by the plugin.
        foreach ($records as $record) {
          $context = $record->getContext();
          if (!isset($contexts[$context])) {
            $message = $this->t(
              "Unknown block_access_records context '@context'", ['@context' => $context]
            );
            $errors[] = (new BlockAccessVisibilityException($message))
              ->setPlugin($instance)
              ->setBlock($block)
              ->setConditions($this->handledConditions($instance));
            continue;
          }
          $return[] = $record;
          unset($contexts[$context]);
        }

        // Generate empty records if some are missing.
        foreach (array_keys($contexts) as $context) {
          $return[] = new BlockAccessRecord($context);
        }

      } catch (BlockAccessVisibilityException $e) {
        $errors[] = $e;
      } catch (\Exception $e) {
        $errors[] = (new BlockAccessVisibilityException($e->getMessage(), $e))
          ->setPlugin($instance)
          ->setBlock($block)
          ->setConditions($this->handledConditions($instance));
      }

    }

    if (!empty($visibility)) {
      $unhandled = array_keys($visibility);
      sort($unhandled);
      $errors[] = (new BlockAccessVisibilityException($this->t("Condition cannot be handled by block_access_records")))
        ->setBlock($block)
        ->setConditions($unhandled);
    }

    return $return;
  }

  /**
   * Build access table rows for a block.
   *
   * Each row should have fields [block-id, context, value, negated].
   *
   * @param \Drupal\block\BlockInterface $block
   *   The block.
   *
   * @return array
   *   An array of arrays of fields.
   */
  protected function buildAccessRows(BlockInterface $block) {
    // Disabled rows are never visible.
    if (!$block->status()) {
      return [];
    }

    $return = [];
    $block_id = $block->id();

    // Get the records to turn into DB rows.
    $errors = [];
    $records = $this->buildAccessRecords($block, $errors);
    if (!empty($errors)) {
      throw $errors[0];
    }

    foreach ($records as $record) {
      $values = $record->getValues();
      $negated = (int) $record->isNegated();

      // No values means no restriction, so add a NULL value to match.
      // If we're negated, we also need to match one row if none of the
      // anti-conditions apply.
      if ($negated || empty($values)) {
        $values[] = NULL;
      }

      foreach ($values as $value) {
        $return[] = [$block_id, $record->getContext(), $value, $negated];
      }
    }

    return $return;
  }

  /**
   * Alter a block form to hide unsupported conditions.
   */
  public function alterForm(&$form, FormStateInterface $state, $form_id) {
    $form['#validate'][] = [$this, 'validateForm'];

    $handled = [];
    foreach ($this->recordPluginManager->getInstances() as $instance) {
      foreach ($instance->handledConditions() as $id) {
        $handled[$id] = TRUE;
      }
    }

    foreach ($state->get(['conditions']) as $id => $condition) {
      if (!isset($handled[$id])) {
        /** @var ConditionInterface $condition */
        $condition->setConfiguration($condition->defaultConfiguration());
        $default_form = $condition->buildConfigurationForm([], $state);

        $elements =& $form['visibility'][$id];
        $elements = array_merge($elements, $default_form);

        $elements['#disabled'] = TRUE;
        $elements['#title'] = $this->t('@title (disabled)', ['@title' => $elements['#title']]);
        $elements['#description'] = $this->t('This condition is disabled, because it is not supported by block_access_records');
      }
    }
  }

  /**
   * Build a block from the form, so it can be validated.
   */
  protected function buildFormEntity(&$form, FormStateInterface $state) {
    /** @var \Drupal\block\BlockForm $form_object */
    $form_object = $state->getFormObject();
    /** @var BlockInterface $block */
    $block = $form_object->buildEntity($form, $state);

    // Submit the conditions.
    foreach ($state->get(['conditions']) as $id => $condition) {
      /** @var ConditionInterface $condition */
      $values = $state->getValue(['visibility', $id]);
      $value_state = (new FormState())->setValues($values);
      $condition->submitConfigurationForm($form, $value_state);
      if ($condition instanceof ContextAwarePluginInterface) {
        $context_mapping = isset($values['context_mapping']) ? $values['context_mapping'] : [];
        $condition->setContextMapping($context_mapping);
      }

      $block->getVisibilityConditions()->addInstanceId($id, $condition->getConfiguration());
    }

    // Make sure the entity has a sane visibility array.
    $config = $block->getVisibilityConditions()->getConfiguration();
    $block->set('visibility', $config);
    return $block;
  }

  /**
   * Validate a block form.
   */
  public function validateForm(&$form, FormStateInterface $state) {
    $block = $this->buildFormEntity($form, $state);

    // Check that our config is valid.
    /** @var BlockAccessVisibilityException[] $errors */
    $errors = [];
    $this->buildAccessRecords($block, $errors);

    // Add any errors to the form.
    if (!empty($errors)) {
      foreach ($errors as $error) {
        $conditions = $error->getConditions();
        if (empty($conditions)) {
          $conditions[] = NULL;
        }

        foreach ($conditions as $condition) {
          $element =& $form['visibility'];
          if ($condition) {
            $element =& $element[$condition];
          }

          $message = $error->errorMessage($condition, $state->get('conditions'));
          $state->setError($element, $message);
        }
      }
    }
  }

}