<?php

namespace Drupal\commerce_preorder_batch;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Preorder batch entity.
 *
 * @see \Drupal\commerce_preorder_batch\Entity\PreorderBatch.
 */
class PreorderBatchAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\commerce_preorder_batch\Entity\PreorderBatch $entity*/
    switch ($operation) {
      case 'view':
        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished preorder_batch entities');
        }
        return AccessResult::allowedIfHasPermission($account, 'view preorder_batch entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit preorder_batch entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete preorder_batch entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add preorder_batch entities');
  }

} 