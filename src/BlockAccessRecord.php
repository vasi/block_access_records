<?php

/**
 * @file
 * Contains Drupal\block_access_records\BlockAccessRecord.
 */

namespace Drupal\block_access_records;

/**
 * A record that controls the visibility of a block.
 */
class BlockAccessRecord {
  /**
   * Context for which this record applies.
   *
   * @var string $context
   */
  protected $context;

  /**
   * Values of this record.
   *
   * A block will only be visible if the context values of the current request
   * matches one of these values.
   *
   * @var string[] $values
   */
  protected $values = [];

  /**
   * Whether this record is negated.
   *
   * If a record is negated, a block will be visible if the current context
   * values do NOT match one of this record's values.
   *
   * @var boolean $negated
   */
  protected $negated = FALSE;

  /**
   * BlockAccessRecord constructor.
   *
   * @param string $context
   *   The context for which this record applies.
   */
  public function __construct($context) {
    $this->context = $context;
  }

  /**
   * Get this record's context.
   *
   * @return string
   *   The context.
   */
  public function getContext() {
    return $this->context;
  }

  /**
   * Get this record's values.
   *
   * @return string[]
   *   The values.
   */
  public function getValues() {
    return $this->values;
  }

  /**
   * Set this record's values.
   *
   * @param string[] $values
   *   The new values.
   */
  public function setValues($values) {
    $this->values = $values;
  }

  /**
   * Add to this record's values.
   *
   * @param string $value
   *   A new value.
   */
  public function addValue($value) {
    $this->values[] = $value;
  }

  /**
   * Get whether this record is negated.
   *
   * @return bool
   *   Whether this record is negated.
   */
  public function isNegated() {
    return $this->negated;
  }

  /**
   * Set whether this record is negated.
   *
   * @param bool $negated
   *   Whether this record is negated.
   */
  public function setNegated($negated) {
    $this->negated = $negated;
  }

}
