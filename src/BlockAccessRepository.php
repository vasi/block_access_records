<?php

/**
 * @file
 * Contains Drupal\block_access_records\BlockAccessRepository.
 */

namespace Drupal\block_access_records;

use Drupal\block\BlockRepositoryInterface;
use Drupal\block\Entity\Block;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Theme\ThemeManagerInterface;

/**
 * A block repository that uses access records to determine visibility.
 */
class BlockAccessRepository implements BlockRepositoryInterface {
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
   */
  public function __construct(EntityTypeManagerInterface $type_manager, ThemeManagerInterface $theme_manager, Connection $connection, BlockAccessRecordsPluginManager $record_plugin_manager) {
    $this->blockStorage = $type_manager->getStorage('block');
    $this->themeManager = $theme_manager;
    $this->connection = $connection;
    $this->recordPluginManager = $record_plugin_manager;
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
    $regions = array_fill_keys($this->themeManager->getActiveTheme()->getRegions(), []);
    // Get a superset of blocks that may be visible, based on access records.
    $block_ids = $this->getVisibleBlocks($cacheable_metadata);

    if (!empty($block_ids)) {
      $account = \Drupal::currentUser();

      // Load only the blocks we think are likely to be visible.
      $blocks = $this->blockStorage->loadMultiple($block_ids);

      // Assign blocks to regions.
      foreach ($blocks as $block_id => $block) {
        /** @var \Drupal\block\Entity\Block $block */
        $region = $block->getRegion();
        // Check that the region exists before doing an expensive access check.
        if (isset($regions[$region])) {
          // Check if the plugin forbids this block.
          if ($block->getPlugin()->access($account, TRUE)->isForbidden()) {
            continue;
          }
          $regions[$region][$block_id] = $block;
        }
      }
    }

    // Sort the blocks.
    foreach ($regions as &$region) {
      uasort($region, 'Drupal\block\Entity\Block::sort');
    }
    return $regions;
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
   */
  public function rebuildAccessRecords() {
    foreach ($this->blockStorage->loadMultiple() as $block) {
      $this->updateAccessRecords($block);
    }
  }

  /**
   * Update the access records for a block.
   *
   * May either delete the records, or delete them and replace them with
   * updated records.
   *
   * TODO: It might be nice to check $block->getVisibility(), and make sure
   * no conditions are unhandled. Otherwise, there could be new Conditions
   * that we don't know about!
   *
   * @param \Drupal\block\Entity\Block $block
   *   The block for which to update.
   * @param bool $delete_only
   *   Whether to only delete records for this block.
   */
  public function updateAccessRecords(Block $block, $delete_only = FALSE) {
    // Calculate new values.
    $rows = $delete_only ? [] : $this->buildAccessRows($block);

    // Ensure deleting and re-adding happen together.
    $transaction = $this->connection->startTransaction();
    try {
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
    }
    catch (\Exception $e) {
      $transaction->rollback();
      throw $e;
    }
  }

  /**
   * Build the access records for a block.
   *
   * @param \Drupal\block\Entity\Block $block
   *   The block.
   *
   * @return BlockAccessRecord[]
   *   The new access records.
   */
  protected function buildAccessRecords(Block $block) {
    // Ask each plugin for records.
    $return = [];
    foreach ($this->recordPluginManager->getInstances() as $instance) {
      /** @var BlockAccessRecordsPluginInterface $instance */
      $contexts = array_fill_keys($instance->contexts(), 1);

      // Ensure records match the contexts reported by the plugin.
      $records = $instance->accessRecords($block);
      foreach ($records as $record) {
        $context = $record->getContext();
        if (!isset($contexts[$context])) {
          throw new \Exception("Unknown block access record context '$context'");
        }
        $return[] = $record;
        unset($contexts[$context]);
      }

      // Generate empty records if some are missing.
      foreach (array_keys($contexts) as $context) {
        $return[] = new BlockAccessRecord($context);
      }
    }

    return $return;
  }

  /**
   * Build access table rows for a block.
   *
   * Each row should have fields [block-id, context, value, negated].
   *
   * @param \Drupal\block\Entity\Block $block
   *   The block.
   *
   * @return array
   *   An array of arrays of fields.
   */
  protected function buildAccessRows(Block $block) {
    $return = [];
    $block_id = $block->id();

    // Get the records to turn into DB rows.
    $records = $this->buildAccessRecords($block);
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

}
