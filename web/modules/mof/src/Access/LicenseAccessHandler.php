<?php

declare(strict_types=1);

namespace Drupal\mof\Access;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Provides an access handler for models.
 */
class LicenseAccessHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  public function access(
    EntityInterface $entity,
    $operation,
    AccountInterface $account = NULL,
    $return_as_object = FALSE
  ) {
    if (!$account) {
      $account = $this->prepareUser();
    }

    if ($account->hasPermission('administer licenses')) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(
    AccountInterface $account,
    array $context,
    $entity_bundle = NULL
  ) {
    return AccessResult::allowedIfHasPermission($account, 'administer licenses');
  }

}

