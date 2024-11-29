<?php

declare(strict_types=1);

namespace Drupal\mof;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Url;
use Drupal\mof\Entity\Model;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ModelAdminListBuilder extends EntityListBuilder {

  use PageLimitTrait;

  private $formBuilder;

  private $request;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    FormBuilderInterface $form_builder,
    Request $request
  ) {
    parent::__construct($entity_type, $storage);
    $this->formBuilder = $form_builder;
    $this->request = $request;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(
    ContainerInterface $container,
    EntityTypeInterface $entity_type
  ) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('form_builder'),
      $container->get('request_stack')->getCurrentRequest()
    );
  }

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

    if ($label = $this->request->get('label')) {
      $label = addcslashes($label, '\\%_');
      $query->condition('label', "%{$label}%", 'LIKE');
    }

    if ($org = $this->request->get('org')) {
      $org = addcslashes($org, '\\%_');
      $query->condition('organization', "%{$org}%", 'LIKE');
    }

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

  /**
   * {@inheritdoc}
   */
  public function render(): array|RedirectResponse {
    if (($url = $this->getPageRedirectUrl()) !== NULL) {
      return $this->redirectPage($url);
    }

    $build = parent::render();
    $build['#attached']['library'][] = 'mof/model-list';
    $build['search'] = $this->formBuilder->getForm('\Drupal\mof\Form\ModelSearchForm', 'entity.model.admin_collection');
    $build['search']['#weight'] = -100;
    $build['table']['#attributes']['class'][] = 'tablesaw';
    $build['table']['#attributes']['class'][] = 'tablesaw-stack';
    $build['table']['#attributes']['data-tablesaw-mode'] = 'stack';

    return $build;
  }

}
