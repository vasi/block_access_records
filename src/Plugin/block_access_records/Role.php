<?php

/**
 * @file
 * Contains Drupal\block_access_records\Plugin\block_access_records/Role.
 */

namespace Drupal\block_access_records\Plugin\block_access_records;

use Drupal\block_access_records\BlockAccessRecordsPluginBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A block access plugin for the current role IDs.
 *
 * @Plugin(
 *   id = "role",
 *   label = @Translation("User role")
 * )
 */
class Role extends BlockAccessRecordsPluginBase implements ContainerFactoryPluginInterface {
  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface $currentUser
   */
  protected $currentUser;

  /**
   * Plugin constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The user account.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function contexts() {
    return ['role'];
  }

  /**
   * {@inheritdoc}
   */
  public function currentContext(CacheableMetadata $cacheable_metadata) {
    $cacheable_metadata->addCacheContexts(['user.roles']);
    return [
      'role' => $this->currentUser->getRoles(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function visibilityParents() {
    return ['user_role', 'roles'];
  }

}
