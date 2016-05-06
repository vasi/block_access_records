<?php

/**
 * @file
 * Hooks provided by the block_access_records module.
 */

use Drupal\block\Entity\Block;

/**
 * Control which blocks are visible.
 *
 * When using block_access_records, hook_block_access only gets called on
 * blocks that look like their conditions pass. This hook allows arbitrary
 * other blocks to be added to the visibility list.
 *
 * @param \Drupal\block\BlockInterface[] &$blocks
 *   An array of visible blocks, indexed by ID.
 */
function hook_block_visibility_alter(&$blocks) {
  // Force the login block to appear for anonymous users,
  // even if it's otherwise forbidden.
  if (\Drupal::currentUser()->isAnonymous()) {
    $blocks['userlogin'] = Block::load('userlogin');
  }
}
