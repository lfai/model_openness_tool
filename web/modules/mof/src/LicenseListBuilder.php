<?php

declare(strict_types=1);

namespace Drupal\mof;

use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides a list controller for the license entity type.
 */
final class LicenseListBuilder extends EntityListBuilder {

  use PageLimitTrait;

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header = [
      'name' => [
        'data' => $this->t('Name'),
        'field' => 'name',
        'specifier' => 'name',
      ],
      'license_id' => [
        'data' => $this->t('License ID'),
        'field' => 'license_id',
        'specifier' => 'license_id',
      ],
    ];

    // Conditionally add the Operations column header for users with the administer licenses permission.
    if (\Drupal::currentUser()->hasPermission('administer licenses')) {
      $header += parent::buildHeader();
    }

    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    $row = [
      'name' => $entity->getName(),
      'license_id' => $entity->getLicenseId(),
    ];

    // Conditionally add the Operations column rows for users with the administer licenses permission.
    if (\Drupal::currentUser()->hasPermission('administer licenses')) {
      $row += parent::buildRow($entity);
    }

    return $row;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityListQuery(): QueryInterface {
    $this->setPageLimit();
    $header = $this->buildHeader();

    $query = $this
      ->getStorage()
      ->getQuery()
      ->accessCheck(TRUE)
      ->tableSort($header);

    if ($this->limit) {
      $query->pager($this->limit);
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array|RedirectResponse {
    return ($url = $this->getPageRedirectUrl()) != NULL ? $this->redirectPage($url) : parent::render();
  }

}
