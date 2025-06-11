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

    // Allow access to all operations if user has `administer model` perms.
    if ($account->hasPermission('administer model')) {
      return AccessResult::allowed();
    }

    switch ($operation) {
    case 'view':
    case 'update':
      // Note that users can edit/update the model form, but if they're anonymous
      // form submissions will not be saved to the database.
      // @see Drupal\mof\Form\ModelEditForm::save().
      return AccessResult::allowedIf($entity->getStatus() === Model::STATUS_APPROVED);

    case 'delete':
    default:
      return AccessResult::forbidden();
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
    return AccessResult::allowedIfHasPermission('administer model');
  }

}

