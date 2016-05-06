<?php

/**
 * @file
 * Contains Drupal\block_access_records\Plugin\block_access_records/Path.
 */

namespace Drupal\block_access_records\Plugin\block_access_records;

use Drupal\block\BlockInterface;
use Drupal\block_access_records\BlockAccessRecord;
use Drupal\block_access_records\BlockAccessRecordsPluginBase;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Database\Query\ConditionInterface;
use Drupal\Core\Path\AliasManagerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A block access plugin for the current path.
 *
 * In addition to the current path, also provides any alias, if one exists.
 * Also supports '<front>' for the front page.
 *
 * In addition to complete paths, it also supports simple wildcards.
 *
 * @Plugin(
 *   id = "path",
 *   label = @Translation("Path")
 * )
 */
class Path extends BlockAccessRecordsPluginBase implements ContainerFactoryPluginInterface {
  /**
   * The current path stack.
   *
   * @var \Drupal\Core\Path\CurrentPathStack $currentPath
   */
  protected $currentPath;

  /**
   * The alias manager.
   *
   * @var \Drupal\Core\Path\AliasManagerInterface $aliasManager
   */
  protected $aliasManager;

  /**
   * The current front page.
   *
   * @var string $frontPage
   */
  protected $frontPage;

  /**
   * Plugin constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Path\CurrentPathStack $current_path
   *   The current path stack.
   * @param \Drupal\Core\Path\AliasManagerInterface $alias_manager
   *   The alias manager.
   * @param string $front_page
   *   The current front page.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CurrentPathStack $current_path, AliasManagerInterface $alias_manager, $front_page) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentPath = $current_path;
    $this->aliasManager = $alias_manager;
    $this->frontPage = $front_page;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('path.current'),
      $container->get('path.alias_manager'),
      $container->get('config.factory')->get('system.site')->get('page.front')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function contexts() {
    return ['path'];
  }

  /**
   * {@inheritdoc}
   */
  public function currentContext(CacheableMetadata $cacheable_metadata) {
    $path = rtrim($this->currentPath->getPath(), '/');
    $paths = [$path];

    // Check the current alias.
    $alias = Unicode::strtolower($this->aliasManager->getAliasByPath($path));
    if ($path !== $alias) {
      $paths[] = $alias;
    }

    // Check the front page.
    if ($path === $this->frontPage) {
      $paths[] = '<front>';
    }

    $cacheable_metadata->addCacheContexts(['url.path', 'route']);
    $cacheable_metadata->addCacheTags(['config:system.site']);

    return ['path' => $paths];
  }

  /**
   * Add a query condition for paths.
   *
   * We don't just match directly, but use a reverse LIKE statement.
   * It's reverse because it says 'placeholder LIKE field' instead of the
   * typical 'field LIKE placeholder'.
   *
   * @param string $context
   *   The context we're matching.
   * @param array $values
   *   The values to match $field against.
   * @param \Drupal\Core\Database\Query\ConditionInterface $query
   *   The query to add to.
   * @param string $field
   *   The field to match against.
   */
  public function addCondition($context, $values, ConditionInterface $query, $field) {
    $group = $query->orConditionGroup();
    foreach ($values as $delta => $value) {
      $placeholder = ":{$context}_{$delta}";
      $group->where("$placeholder LIKE $field",
        [$placeholder => $value]);
    }
    $query->condition($group);
  }

  /**
   * {@inheritdoc}
   */
  public function accessRecords(BlockInterface $block, array &$visibility) {
    $records = [];
    if (isset($visibility['request_path']['pages'])) {
      $record = new BlockAccessRecord('path');

      // Path records can be negated!
      $record->setNegated(!empty($visibility['request_path']['negate']));

      // It's not an array, but newline-separated paths.
      $pages = $visibility['request_path']['pages'];
      $pages = preg_split('/[\r\n]+/', $pages);
      foreach ($pages as $page) {
        // Change wildcard asterisks for SQL-style wildcards.
        $page = str_replace('*', '%', $page);
        $record->addValue($page);
      }
      $records[] = $record;

      unset($visibility['request_path']);
    }

    return $records;
  }

  /**
   * {@inheritdoc}
   */
  public function handledConditions() {
    return ['request_path'];
  }

}
