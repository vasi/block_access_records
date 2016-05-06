<?php

/**
 * @file
 * Contains Drupal\block_access_records\Plugin\block_access_records/Language.
 */

namespace Drupal\block_access_records\Plugin\block_access_records;

use Drupal\block\BlockInterface;
use Drupal\block_access_records\BlockAccessRecordsPluginBase;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A block access plugin for the current language ID.
 *
 * @Plugin(
 *   id = "language",
 *   label = @Translation("Language")
 * )
 */
class Language extends BlockAccessRecordsPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface $languageManager
   */
  protected $languageManager;

  /**
   * Plugin constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LanguageManagerInterface $language_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function contexts() {
    return ['language_interface'];
  }

  /**
   * {@inheritdoc}
   */
  public function currentContext(CacheableMetadata $cacheable_metadata) {
    $cacheable_metadata->addCacheContexts(['languages:language_interface']);
    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    return ['language_interface' => [$langcode]];
  }

  /**
   * {@inheritdoc}
   */
  protected function visibilityParents() {
    return ['language', 'langcodes'];
  }

  public function accessRecords(BlockInterface $block, array &$visibility) {
    $mapping = NestedArray::getValue($visibility, ['language', 'context_mapping', 'language']);
    if ($mapping !== '@language.current_language_context:language_interface') {
      return [];
    }
    return parent::accessRecords($block, $visibility);
  }

}
