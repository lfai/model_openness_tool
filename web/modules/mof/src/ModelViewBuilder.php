<?php

declare(strict_types=1);

namespace Drupal\mof;

use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Theme\Registry;
use Drupal\mof\Entity\Model;
use Drupal\mof\ModelEvaluatorInterface;
use Drupal\mof\ComponentManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * ModelViewBuilder class.
 */
class ModelViewBuilder extends EntityViewBuilder {

  /** @var \Drupal\mof\ModelEvaluatorInterface. */
  protected ModelEvaluatorInterface $modelEvaluator;

  /** @var array */
  protected array $modelComponents;

  /** @var \Drupal\Core\Messenger\MessengerInterface. */
  protected MessengerInterface $messenger;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityRepositoryInterface $entity_repository,
    LanguageManagerInterface $language_manager,
    Registry $theme_registry,
    EntityDisplayRepositoryInterface $entity_display_repository,
    ModelEvaluatorInterface $model_evaluator,
    ComponentManagerInterface $component_manager,
    MessengerInterface $messenger
  ) {
    parent::__construct(
      $entity_type,
      $entity_repository,
      $language_manager,
      $theme_registry,
      $entity_display_repository
    );
    $this->modelEvaluator = $model_evaluator;
    $this->modelComponents = $component_manager->getComponents();
    $this->messenger = $messenger;
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
      $container->get('entity.repository'),
      $container->get('language_manager'),
      $container->get('theme.registry'),
      $container->get('entity_display.repository'),
      $container->get('model_evaluator'),
      $container->get('component.manager'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(array $build) {
    if ($build['#model']->isPending()) {
      $this->messenger->addWarning($this->t('Model pending evaluation'));
      return $build;
    }

    $this->modelEvaluator->setModel($build['#model']);
    $evaluation = $this->modelEvaluator->evaluate();

    if ($build['#model']->id() !== NULL) {
      if ($build['#model']->getStatus() === Model::STATUS_UNAPPROVED) {
        $this->messenger->addWarning($this->t('This model is awaiting approval'));
      }
      else if ($build['#model']->getStatus() === Model::STATUS_REJECTED) {
        $this->messenger->addWarning($this->t('This model has been rejected'));
      }

      $build['json'] = [
        '#type' => 'link',
        '#title' => $this->t('Download JSON'),
        '#url' => $build['#model']->toUrl('json'),
        '#weight' => -150,
        '#attributes' => ['class' => ['model-link', 'json-download']],
      ];

      $build['icons'] = [
        '#theme' => 'model_link',
        '#github' => $build['#model']->getGithubSlug(),
        '#huggingface' => $build['#model']->getHuggingfaceSlug(),
        '#weight' => -180,
      ];
    }

    $badges = $this
      ->modelEvaluator
      ->setModel($build['#model'])
      ->generateBadge();

    $build['evaluations'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['evaluation']],
      '#weight' => -100,
    ];

    for ($i = 3; $i >= 1; $i--) {
      $build['evaluations'][$i] = [
        '#type' => 'container',
        'class_name' => [
          '#type' => 'html_tag',
          '#tag' => 'h4',
          '#value' => $this->modelEvaluator->getClassLabel($i),
        ],
        'badge' => $badges[$i],
        'evaluation' => [
          'included' => $this->buildIncluded($evaluation[$i]['included']),
          'missing' => $this->buildMissing($evaluation[$i]['missing']),
          'invalid' => $this->buildInvalid($evaluation[$i]['invalid']),
          '#weight' => 10,
        ],
      ];
    }

    if ($evaluation[3]['conditional']) {
      $this->messenger->addMessage($this->t('This model has a Class III conditional pass because it has an open source license for Model Parameters (Final)'));
    }

    $build['#attached']['library'][] = 'mof/model-evaluation';
    return parent::build($build);
  }

  private function buildIncluded(array $included_components): array {
    $build = [];

    if (!empty($included_components)) {
      $build = [
        'included_components' => [
          '#theme' => 'item_list',
          '#title' => $this->t('Included'),
        ],
      ];
    }

    $components = array_filter($this->modelComponents, fn($c) => in_array($c->id, $included_components));
    foreach ($components as $component) {
      $build['included_components']['#items'][] = $component->name;
    }

    return $build;
  }

  private function buildInvalid(array $components): array {
    $build = [];

    $invalid = array_filter($this->modelComponents, fn($c) => in_array($c->id, $components));
    if (!empty($invalid)) {
      $build = [
        'invalid_components' => [
          '#theme' => 'item_list',
          '#title' => $this->t('Invalid license'),
        ],
      ];

      foreach ($invalid as $component) {
        $build['invalid_components']['#items'][] = $component->name;
      }
    }

    return $build;
  }

  private function buildMissing(array $missing_components): array {
    $build = [];

    if (!empty($missing_components)) {
      $build = [
        'missing_components' => [
          '#theme' => 'item_list',
          '#title' => $this->t('Missing components'),
        ],
      ];
    }

    $components = array_filter($this->modelComponents, fn($c) => in_array($c->id, $missing_components));
    foreach ($components as $component) {
      $build['missing_components']['#items'][] = $component->name;
    }

    return $build;
  }

}

