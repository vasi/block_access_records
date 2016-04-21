<?php

/**
 * @file
 * Contains Drupal\block_access_records\Plugin\block_access_records/Theme.
 */

namespace Drupal\block_access_records\Plugin\block_access_records;

use Drupal\block\BlockInterface;
use Drupal\block_access_records\BlockAccessRecord;
use Drupal\block_access_records\BlockAccessRecordsPluginBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A block access plugin for the current theme name.
 *
 * @Plugin(
 *   id = "theme"
 * )
 */
class Theme extends BlockAccessRecordsPluginBase implements ContainerFactoryPluginInterface {
  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface $themeManager
   */
  protected $themeManager;

  /**
   * Plugin constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ThemeManagerInterface $theme_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->themeManager = $theme_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('theme.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function contexts() {
    return ['theme'];
  }

  /**
   * {@inheritdoc}
   */
  public function currentContext(CacheableMetadata $cacheable_metadata) {
    $cacheable_metadata->addCacheContexts(['theme']);
    return [
      'theme' => [$this->themeManager->getActiveTheme()->getName()],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function accessRecords(BlockInterface $block) {
    $record = new BlockAccessRecord('theme');
    $record->addValue($block->getTheme());
    return [$record];
  }

}
