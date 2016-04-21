<?php

/**
 * @file
 * Contains Drupal\block_access_records\BlockAccessRecordsPluginInterface.
 */

namespace Drupal\block_access_records;

use Drupal\block\BlockInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Database\Query\ConditionInterface;

/**
 * A plugin that provides block access records.
 *
 * Plugins are responsible for providing access records for each block,
 * and context values for the current request. Only if the context matches the
 * access records is the block allowed.
 */
interface BlockAccessRecordsPluginInterface {

  /**
   * Get all contexts this plugin may provide.
   *
   * A context is just a string, eg: 'node_type', representing an aspect
   * of the current request by which block visibility may vary.
   *
   * @return string[]
   *   The supported contexts.
   */
  public function contexts();

  /**
   * Get the context values for the current request.
   *
   * Returns an array whose keys are contexts as returned by contexts(),
   * and whose values are arrays. If you do not provide a value for a given
   * context, it is equivalent to an empty array.
   *
   * Eg: A node_type context might return ['node_type' => ['page']];
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheable_metadata
   *   The metadata for calculating block visiblity, to be altered.
   *
   * @return array
   *   The context values.
   */
  public function currentContext(CacheableMetadata $cacheable_metadata);

  /**
   * Get the access records for a given block.
   *
   * Each returned record must have a context from contexts(). If you do not
   * provide a record for a given context, it is equivalent to a record with
   * no values.
   *
   * @param \Drupal\block\BlockInterface $block
   *   The block being saved.
   *
   * @return BlockAccessRecord[]
   *   Records for this block.
   */
  public function accessRecords(BlockInterface $block);

  /**
   * Add a query condition for a context.
   *
   * The condition should match the context values $values against the field
   * $field. For most cases, you should use:
   *
   * @code
   *   $query->condition($field, $values, 'IN');
   * @endcode
   *
   * However, certain contexts may have special handling that requires a
   * custom condition.
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
  public function addCondition($context, $values, ConditionInterface $query, $field);

}
