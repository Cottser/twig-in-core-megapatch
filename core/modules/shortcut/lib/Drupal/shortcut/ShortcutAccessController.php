<?php

/**
 * @file
 * Contains \Drupal\shortcut\ShortcutAccessController.
 */

namespace Drupal\shortcut;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityAccessController;
use Drupal\user\Plugin\Core\Entity\User;

/**
 * Defines the access controller for the shortcut entity type.
 */
class ShortcutAccessController extends EntityAccessController {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, $langcode, User $account) {
    switch ($operation) {
      case 'edit':
        if (user_access('administer shortcuts', $account)) {
          return TRUE;
        }
        if (user_access('customize shortcut links', $account)) {
          return !isset($entity) || $entity == shortcut_current_displayed_set($account);
        }
        return FALSE;
        break;
      case 'delete':
        if (!user_access('administer shortcuts', $account)) {
          return FALSE;
        }
        return $entity->id() != 'default';
        break;
    }
  }
}
