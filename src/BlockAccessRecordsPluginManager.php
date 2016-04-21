<?php

/**
 * @file
 * Contains Drupal\block_access_records\BlockAccessRecordsPluginManager.
 */

namespace Drupal\block_access_records;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * The plugin manager for block access record plugins.
 */
class BlockAccessRecordsPluginManager extends DefaultPluginManager {
  /**
   * All plugins.
   *
   * @var BlockAccessRecordsPluginInterface[]
   */
  protected $instances = [];

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    $this->alterInfo('block_access_records_plugins');
    $this->setCacheBackend($cache_backend, 'block_access_records_plugins');
    parent::__construct(
      'Plugin/block_access_records',
      $namespaces,
      $module_handler,
      'Drupal\block_access_records\BlockAccessRecordsPluginBase'
    );
  }

  /**
   * Get all plugin instances.
   *
   * @return \Drupal\block_access_records\BlockAccessRecordsPluginInterface[]
   *   All plugin instances.
   */
  public function getInstances() {
    if (empty($this->instances)) {
      foreach ($this->getDefinitions() as $key => $value) {
        $this->instances[$key] = $this->createInstance($key);
      }
    }
    return $this->instances;
  }

}
