<?php

/**
 * @file
 * Contains Drupal\block_access_records\Plugin\block_access_records/NodeType.
 */

namespace Drupal\block_access_records\Plugin\block_access_records;

use Drupal\block_access_records\BlockAccessRecordsPluginBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;
use Drupal\node\NodeTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A block access plugin for the current node type, if one exists.
 *
 * @Plugin(
 *   id = "node_type",
 *   label = @Translation("Node type")
 * )
 */
class NodeType extends BlockAccessRecordsPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The route match.
   *
   * @var RouteMatchInterface $routeMatch
   */
  protected $routeMatch;

  /**
   * Plugin constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function contexts() {
    return ['node_type'];
  }

  /**
   * {@inheritdoc}
   */
  public function currentContext(CacheableMetadata $cacheable_metadata) {
    $types = [];
    if ($node = $this->routeMatch->getParameter('node')) {
      // Add the type of the current node.
      if ($node instanceof NodeInterface) {
        $types[] = $node->bundle();
      }
    }
    if ($this->routeMatch->getRouteName() == 'node.add') {
      // Also add the type of the node we're creating.
      if ($type = $this->routeMatch->getParameter('node_type')) {
        if ($type instanceof NodeTypeInterface) {
          $types[] = $type->id();
        }
      }
    }
    $cacheable_metadata->addCacheContexts(['route']);

    return [
      'node_type' => array_unique($types),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function visibilityParents() {
    return ['node_type', 'bundles'];
  }

}
