<?php

declare(strict_types=1);

namespace Drupal\mof\Access;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\mof\Entity\Model;

/**
 * Provides an access handler for models.
 */
class ModelAccessHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  public function access(
    EntityInterface $entity,
    $operation,
    AccountInterface $account = NULL,
    $return_as_object = FALSE
  ) {
    $result = parent::access($entity, $operation, $account, $return_as_object);

    if (!$account) {
      $account = $this->prepareUser();
    }

    if ($account->hasPermission('administer model')) {
      return AccessResult::allowed();
    }

    switch ($operation) {
    case 'view':
      $is_owner = AccessResult::allowedIf($this->isOwner($entity, $account));
      return AccessResult::allowedIf($entity->getStatus() === Model::STATUS_APPROVED)->orIf($is_owner);
    
    case 'update':
    case 'delete':
      $is_owner = AccessResult::allowedIf($this->isOwner($entity, $account));
      return AccessResult::allowedIf(!$entity->getOwner()->isAnonymous())->andIf($is_owner);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(
    AccountInterface $account,
    array $context,
    $entity_bundle = NULL
  ) {
    return AccessResult::allowedIfHasPermission($account, 'submit model');
  }

  /**
   * Return true if the owner of $entity matches the $account owner.
   */
  protected function isOwner(EntityInterface $entity, AccountInterface $account) {
    return $entity->getOwner()->id() === $account->id();
  }

}

