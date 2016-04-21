<?php

/**
 * @file
 * Contains Drupal\block_access_records\BlockAccessRecordsPluginBase.
 */

namespace Drupal\block_access_records;

use Drupal\block\BlockInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Database\Query\ConditionInterface;
use Drupal\Core\Plugin\PluginBase;

/**
 * A good default implementation of BlockAccessRecordsPluginInterface.
 */
abstract class BlockAccessRecordsPluginBase extends PluginBase implements BlockAccessRecordsPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function addCondition($context, $values, ConditionInterface $query, $field) {
    $query->condition($field, $values, 'IN');
  }

  /**
   * Indicate where to find a plugin's info within block visibility.
   *
   * If a plugin gets its access records from a block's visibility settings,
   * it can override this method.
   *
   * A plugin that does so should return an array of parent keys, such that
   * NestedArray::getValue($visibility, $parents) yields an array whose keys
   * are this plugin's access record values.
   *
   * @return string[]
   *   A path within a block's visibility settings.
   */
  protected function visibilityParents() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function accessRecords(BlockInterface $block) {
    $parents = $this->visibilityParents();
    if (empty($parents)) {
      throw new \Exception("Can't get block visibility parents");
    }

    $visibility = $block->getVisibility();
    $exists = NULL;
    $values = NestedArray::getValue($visibility, $parents, $exists);
    if (!$exists) {
      return [];
    }

    $contexts = $this->contexts();
    $record = new BlockAccessRecord(reset($contexts));
    $record->setValues(array_keys($values));
    return [$record];
  }

}
