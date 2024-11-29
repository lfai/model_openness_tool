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

/**
 * Provides a list controller for the model entity type.
 */
final class ModelListBuilder extends EntityListBuilder {

  use PageLimitTrait;

  private $modelEvaluator;

  private $formBuilder;

  private $request;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    ModelEvaluatorInterface $model_evaluator,
    FormBuilderInterface $form_builder,
    Request $request
  ) {
    parent::__construct($entity_type, $storage);
    $this->modelEvaluator = $model_evaluator;
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
      $container->get('model_evaluator'),
      $container->get('form_builder'),
      $container->get('request_stack')->getCurrentRequest()
    );
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
    $build['search'] = $this->formBuilder->getForm('\Drupal\mof\Form\ModelSearchForm', 'entity.model.collection');
    $build['search']['#weight'] = -100;
    $build['table']['#attributes']['class'][] = 'tablesaw';
    $build['table']['#attributes']['class'][] = 'tablesaw-stack';
    $build['table']['#attributes']['data-tablesaw-mode'] = 'stack';

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    return [
      'name' => [
        'data' => $this->t('Name'),
        'field' => 'label',
        'specifier' => 'label',
      ],
      'owner' => [
        'data' => $this->t('Organization'),
        'field' => 'owner',
        'specifier' => 'organization',
      ],
      'class' => [
        'data' => $this->t('Classification'),
        'field' => 'total_progress',
        'specifier' => 'total_progress',
        'sort' => 'desc',
      ],
      'updated' => [
        'data' => $this->t('Last updated'),
        'field' => 'changed',
        'specifier' => 'changed',
        'class' => ['model-updated'],
      ],
      'badge' => $this->t('Badge'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    $row['label'] = [
      'data' => [
        '#theme' => 'model_link',
        '#model' => $entity->toLink(),
      ],
      'class' => 'model-label',
    ];

    if (($slug = $entity->getGithubSlug())) {
      $row['label']['data']['#github' ] = $slug;
    }

    if (($slug = $entity->getHuggingfaceSlug())) {
      $row['label']['data']['#huggingface'] = $slug;
    }

    $row['owner'] = [
      'data' => $entity->getOrganization(),
      'class' => 'model-org',
    ];

    $row['class'] = [
      'data' => $entity->getClassification(),
      'class' => 'model-class',
    ];

    $row['updated'] = [
      'data' => $entity
        ->get('changed')
        ->view([
          'label' => 'hidden',
          'settings' => ['date_format' => 'html_date']
        ]),
      'class' => 'model-updated',
    ];

    $row['badge'] = [
      'data' => $this->modelEvaluator->setModel($entity)->generateBadge(mini: TRUE),
      'class' => ['badge'],
      'data-tablesaw-no-labels' => '',
    ];

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
      ->condition('status', Model::STATUS_APPROVED)
      ->tableSort($header);

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

}
