<?php

/**
 * @file
 * Contains Drupal\block_access_records\BlockAccessRecordsServiceProvider.
 */

namespace Drupal\block_access_records;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * A service provider to use our faster block repository.
 */
class BlockAccessRecordsServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Save the original block.repository, in case anyone wants it.
    $container->setDefinition('block.repository.original',
      $container->getDefinition('block.repository'));

    // Make block.repository an alias to our own faster version.
    $container->removeDefinition('block.repository');
    $container->setAlias('block.repository', 'block.repository.access_records');
  }

}
