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
    return [
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
    ] + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    return [
      'name' => $entity->getName(),
      'license_id' => $entity->getLicenseId(),
    ] + parent::buildRow($entity);
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

  /**
   * Count of total license entites.
   *
   * @return int Number of license entities.
   */
  protected function getEntityCount(): int {
    return $this
      ->getStorage()
      ->getQuery()
      ->accessCheck(TRUE)
      ->count()
      ->execute();
  }

}

