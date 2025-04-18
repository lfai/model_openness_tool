<?php declare(strict_types=1);

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

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    private readonly ModelEvaluatorInterface $modelEvaluator,
    private readonly BadgeGeneratorInterface $badgeGenerator,
    private readonly FormBuilderInterface $formBuilder,
    private readonly Request $request
  ) {
    parent::__construct($entity_type, $storage);
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
      $container->get('badge_generator'),
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
    $build['search'] = $this->formBuilder->getForm('\Drupal\mof\Form\ModelSearchForm');
    $build['search']['#weight'] = -100;
    $build['table']['#attributes']['class'][] = 'tablesaw';
    $build['table']['#attributes']['class'][] = 'tablesaw-stack';
    $build['table']['#attributes']['data-tablesaw-mode'] = 'stack';

    $build['#cache'] = [
      'contexts' => [
        'url.query_args:label',
        'url.query_args:org',
        'url.query_args:page',
        'url.query_args:limit',
        'url.query_args:sort',
        'url.query_args:order',
      ],
    ];

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
      'badge' => [
        'data' => $this->t('Badge'),
        'class' => ['badge'],
      ],
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

    if (($slug = $entity->getRepository())) {
      $row['label']['data']['#repository' ] = $slug;
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
      'data' => $this->badgeGenerator->generate($entity, mini: TRUE),
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
