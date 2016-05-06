<?php

/**
 * @file
 * Contains Drupal\block_access_records\BlockAccessVisibilityException.
 */

namespace Drupal\block_access_records;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * An exception about block visibility.
 */
class BlockAccessVisibilityException extends \Exception {
  use StringTranslationTrait;

  /**
   * The original message passed to the constructor.
   */
  protected $originalMessage;

  /**
   * The plugin causing the exception.
   *
   * @var \Drupal\block_access_records\BlockAccessRecordsPluginInterface $accessPlugin
   */
  protected $accessPlugin = NULL;

  /**
   * The visibility conditions this exception is about.
   *
   * @var array $conditions
   */
  protected $conditions = [];

  /**
   * The block this exception is about.
   *
   * @var \Drupal\block\BlockInterface $block
   */
  protected $block = NULL;

  /**
   * BlockAccessVisibilityException constructor.
   *
   * @param string $message
   *   A custom message for the exception.
   * @param \Exception $previous
   *   The exception that caused this exception.
   */
  public function __construct($message = NULL, \Exception $previous = NULL) {
    parent::__construct($message, 0, $previous);
    $this->originalMessage = $message;
  }

  /**
   * @return \Drupal\block\BlockInterface
   */
  public function getBlock() {
    return $this->block;
  }

  /**
   * @param \Drupal\block\BlockInterface $block
   * @return BlockAccessVisibilityException
   */
  public function setBlock($block) {
    $this->block = $block;
    return $this;
  }

  /**
   * @return array
   */
  public function getConditions() {
    return $this->conditions;
  }

  /**
   * @param array $conditions
   * @return BlockAccessVisibilityException
   */
  public function setConditions($conditions) {
    $this->conditions = $conditions;
    $this->message = $this->errorMessage($this->conditions);
    return $this;
  }

  /**
   * @return BlockAccessRecordsPluginInterface
   */
  public function getPlugin() {
    return $this->accessPlugin;
  }

  /**
   * @param BlockAccessRecordsPluginInterface $accessPlugin
   * @return BlockAccessVisibilityException
   */
  public function setPlugin($accessPlugin) {
    $this->accessPlugin = $accessPlugin;
    $this->message = $this->errorMessage($this->conditions);
    return $this;
  }

  /**
   * Get an error message for a condition.
   */
  public function errorMessage($conditions = NULL, $condition_objects = []) {
    $values = ['@message' => $this->originalMessage];
    if ($conditions) {
      if (!is_array($conditions)) {
        $conditions = [$conditions];
      }

      $labels = [];
      foreach ($conditions as $id) {
        if (isset($condition_objects[$id])) {
          $labels[] = $condition_objects[$id]->getPluginDefinition()['label'];
        }
        else {
          $labels[] = $id;
        }
      }
      $values['@conditions'] = implode(', ', $labels);
    }
    if ($this->accessPlugin) {
      $values['@plugin'] = $this->accessPlugin->getPluginDefinition()['label'];
    }

    if ($conditions && $this->accessPlugin) {
      return (string) $this->t('@conditions: @message (@plugin)', $values);
    }
    elseif ($conditions) {
      return (string) $this->t('@conditions: @message', $values);
    }
    elseif ($this->accessPlugin) {
      return (string) $this->t('@message (@plugin)', $values);
    }
    else {
      return $this->originalMessage;
    }

  }

}
