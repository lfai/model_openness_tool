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
use Drupal\Core\Url;
use Drupal\Component\Utility\SortArray;
use Drupal\mof\Entity\Model;
use Drupal\mof\ModelEvaluatorInterface;
use Drupal\mof\ComponentManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\Session;

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

  /** @var \Symfony\Component\HttpFoundation\Session\Session. */
  protected Session $session;

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
    MessengerInterface $messenger,
    Session $session
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
    $this->session = $session;
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
      $container->get('messenger'),
      $container->get('session')
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
          'included' => $this
            ->getComponentList($evaluation[$i]['included'], 'included'),
          'missing' => $this
            ->getComponentList($evaluation[$i]['missing'], 'missing'),
          'invalid' => $this
            ->getComponentList($evaluation[$i]['invalid'], 'invalid'),
          '#weight' => 10,
        ],
      ];
    }

    if ($evaluation[3]['conditional']) {
      $list = ['#theme' => 'item_list', '#items' => []];

      $message = $this->modelEvaluator->getConditionalMessage();
      $this->messenger->addMessage(array_shift($message));

      $list['#items'] = $message;
      $this->messenger->addMessage($list);
    }

    if ($this->session->get('model_evaluation') === TRUE) {
      $build['retry'] = [
        '#type' => 'link',
        '#title' => $this->t('Retry'),
        '#url' => Url::fromRoute('mof.model.evaluate_form'),
        '#weight' => -200,
        '#attributes' => [
          'class' => ['button', 'button--action', 'button--primary'],
        ],
      ];

      $build['submit'] = [
        '#type' => 'link',
        '#title' => $this->t('Submit model'),
        '#url' => Url::fromRoute('entity.model.add_form'),
        '#weight' => -200,
        '#attributes' => [
          'class' => ['button', 'button--action', 'button--primary'],
        ],
      ];

      $this->session->set('model_evaluation', FALSE);
    }

    $build['#attached']['library'][] = 'mof/model-evaluation';
    $build += parent::build($build);

    uasort($build, [SortArray::class, 'sortByWeightProperty']);
    return $build;
  }

  /**
   * Build a render array of completed, missing or invalid model components.
   *
   * @param array $components
   *   Model components.
   * @param string $status
   *   A value of "missing" or "completed" or "invalid"
   * @return array
   *   A drupal render array of components.
   */
  private function getComponentList(array $components, string $status): array {
    $build = [];

    if (empty($components) && !in_array($status, ['missing', 'invalid', 'completed'])) {
      return $build;
    }

    $build = [
      "{$status}_components" => [
        '#theme' => 'item_list',
        '#title' => $this->t('@status components', ['@status' => ucfirst($status)]),
      ],
    ];

    $components = array_filter(
      $this->modelComponents,
      fn($c) => in_array($c->id, $components));

    foreach ($components as $component) {
      $build["{$status}_components"]['#items'][] = $component->name;
    }

    return $build;
  }

}

