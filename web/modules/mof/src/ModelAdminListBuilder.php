<?php

declare(strict_types=1);

namespace Drupal\mof;

use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Url;
use Drupal\mof\Entity\Model;

class ModelAdminListBuilder extends EntityListBuilder {

  use PageLimitTrait;

  /**
   * {@inheritdoc}
   */
  public function getOperations(EntityInterface $entity) {
    $operations = parent::getOperations($entity);

    $operations['edit']['url'] = Url::fromRoute('entity.model.admin_edit_form', ['model' => $entity->id()]);

    $operations['view'] = [
      'title' => $this->t('View'),
      'weight' => 5,
      'url' => Url::fromRoute('entity.model.canonical', ['model' => $entity->id()]),
    ];

    $operations[Model::STATUS_APPROVED] = [
      'title' => $this->t('Approve'),
      'weight' => 0,
      'url' => Url::fromRoute('entity.model.set_status', ['model' => $entity->id(), 'status' => Model::STATUS_APPROVED]),
    ];

    $operations[Model::STATUS_UNAPPROVED] = [
      'title' => $this->t('Unapprove'),
      'weight' => 0,
      'url' => Url::fromRoute('entity.model.set_status', ['model' => $entity->id(), 'status' => Model::STATUS_UNAPPROVED]),
    ];

    $operations[Model::STATUS_REJECTED] = [
      'title' => $this->t('Reject'),
      'weight' => 1,
      'url' => Url::fromRoute('entity.model.set_status', ['model' => $entity->id(), 'status' => Model::STATUS_REJECTED]),
    ];

    // Remove operation that represents the model's current status.
    unset($operations[$entity->getStatus()]);

    // Resort the operations.
    uasort($operations, '\\Drupal\\Component\\Utility\\SortArray::sortByWeightElement');
    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityListQuery(): QueryInterface {
    $this->setPageLimit();

    $query = parent::getEntityListQuery();
    $query->sort('id', 'DESC');

    if ($this->limit) {
      $query->pager($this->limit);
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    return [
      'name' => $this->t('Name'),
      'approved' => $this->t('Status'),
      'updated' => $this->t('Last updated'),
      'approver' => $this->t('Approver'),
    ] + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    return [
      'name' => $entity->label(),
      'approved' => $entity->getStatus(TRUE),
      'updated' => ['data' => $entity->get('changed')->view(['label' => 'hidden'])],
      'approver' => $entity->getApprover() ? $entity->getApprover()->getDisplayName() : '',
    ] + parent::buildRow($entity);
  }

}
