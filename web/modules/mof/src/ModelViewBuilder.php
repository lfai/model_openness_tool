<?php declare(strict_types=1);

namespace Drupal\mof;

use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Theme\Registry;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Component\Utility\SortArray;
use Drupal\mof\Entity\Model;
use Drupal\mof\ModelEvaluatorInterface;
use Drupal\mof\ComponentManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * ModelViewBuilder class.
 */
final class ModelViewBuilder extends EntityViewBuilder {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityRepositoryInterface $entity_repository,
    LanguageManagerInterface $language_manager,
    Registry $theme_registry,
    EntityDisplayRepositoryInterface $entity_display_repository,
    private readonly ModelEvaluatorInterface $modelEvaluator,
    private readonly BadgeGeneratorInterface $badgeGenerator,
    private readonly array $modelComponents,
    private readonly MessengerInterface $messenger,
    private readonly Session $session
  ) {
    parent::__construct(
      $entity_type,
      $entity_repository,
      $language_manager,
      $theme_registry,
      $entity_display_repository
    );
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
      $container->get('badge_generator'),
      $container->get('component.manager')->getComponents(),
      $container->get('messenger'),
      $container->get('session')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(array $build) {
    if (!$build['#model']->isNew()) {
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

      $build['yaml'] = [
        '#type' => 'link',
        '#title' => $this->t('Download YAML'),
        '#url' => $build['#model']->toUrl('yaml'),
        '#weight' => -140,
        '#attributes' => ['class' => ['model-link', 'yaml-download']],
      ];

      $build['icons'] = [
        '#theme' => 'model_link',
        '#repository' => $build['#model']->getRepository(),
        '#huggingface' => $build['#model']->getHuggingface(),
        '#weight' => -180,
      ];
    }

    $model = $build['#model']->toArray();
    $this->modelEvaluator->setModel($build['#model']);
    $evaluation = $this->modelEvaluator->evaluate();
    $badges = $this->badgeGenerator->generate($build['#model']);

    $build['evaluations'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['evaluation']],
      '#weight' => -100,
    ];

    for ($class = 3; $class >= 1; $class--) {
      $build['evaluations'][$class] = [
        '#type' => 'container',
        'class_name' => [
          '#type' => 'html_tag',
          '#tag' => 'h4',
          '#value' => $this->modelEvaluator->getClassLabel($class),
        ],
        'badge' => $badges[$class],
        'evaluation' => [
          'included' => $this
            ->buildComponentList($class, $evaluation, 'included', $model),
          'unspecified' => $this
            ->buildComponentList($class, $evaluation, 'unlicensed', $model),
          'invalid' => $this
            ->buildComponentList($class, $evaluation, 'invalid', $model),
          'missing' => $this
            ->buildComponentList($class, $evaluation, 'missing', $model),
          '#weight' => 10,
        ],
      ];
    }

    if ($evaluation['not-type-appropriate'] != null) {
      $list = ['#theme' => 'item_list', '#items' => []];

      $message = $this->modelEvaluator->getConditionalMessage();
      $this->messenger->addMessage(array_shift($message));

      $list['#items'] = $message;
      $this->messenger->addMessage($list);
    }

    if ($this->session->get('model_session_evaluation') === TRUE) {
      $build['retry'] = [
        '#type' => 'link',
        '#title' => $this->t('Retry'),
        '#url' => Url::fromRoute('mof.model.evaluate_form'),
        '#weight' => -200,
        '#attributes' => [
          'class' => ['button', 'button--action', 'button--primary'],
        ],
      ];

      $build['download'] = [
        '#type' => 'link',
        '#title' => $this->t('Download YAML'),
        '#url' => Url::fromRoute('mof.model.evaluate_form.download'),
        '#weight' => -200,
        '#attributes' => [
          'class' => ['button', 'button--action', 'button--primary'],
        ],
      ];

      $this->session->set('model_session_evaluation', FALSE);
    }

    $build['#attached']['library'][] = 'mof/model-evaluation';
    $build += parent::build($build);

    uasort($build, [SortArray::class, 'sortByWeightProperty']);
    return $build;
  }

  /**
   * Build a render array of completed, missing, unspecified or invalid model components.
   *
   * @param int $class
   *   The MOF class: 1, 2 or 3.
   * @param array $evaluation
   *   An evaluated model array containing component and license data.
   * @param string $status
   *   A value of "missing" or "included" or "invalid" or "unlicensed".
   * @param array  $model
   * The model array containing license and component paths.
   *
   * @return array
   *   A drupal render array of components.
   */
  private function buildComponentList(int $class, array $evaluation, string $status, array $model): array {
    $build = [];

    if (!in_array($status, ['missing', 'invalid', 'included', 'unlicensed'])) {
      return $build;
    }

    switch ($status) {
    case 'invalid':
      $title = $this->t('Components with an invalid license');
      break;

    case 'unlicensed':
      $title = $this->t('Components without a license');
      break;

    case 'missing':
      $title = $this->t('Components missing');
      break;

    case 'included':
      $title = $this->t('Components included');
      break;
    }

    $build["{$status}_components"] = [
      '#theme' => 'item_list',
      '#title' => $title,
      '#items' => [],
    ];

    $components = array_filter(
      $this->modelComponents,
      fn($c) => in_array($c->id, $evaluation[$class]['components'][$status]));

    // append optionals to included
    if ($status == 'included') {
      $optionals = array_filter(
        $this->modelComponents,
        fn($c) => in_array($c->id, $evaluation[$class]['components']['optional']));

      $components = [
        ...$components,
        ...$optionals
      ];
    }

    foreach ($components as $component) {
      $license = $evaluation[$class]['licenses'][$component->id] ?? null;

      // License paths in order of precedence.
      $license_paths = [
        $model['license_data'][0]['licenses']['components'][$component->id]['license_path'] ?? null,
        $model['license_data'][0]['licenses']['global'][$component->contentType]['path'] ?? null,
        $model['license_data'][0]['licenses']['global']['distribution']['path'] ?? null,
      ];

      $license_url = array_reduce($license_paths, fn($carry, $path) => $carry ?? $path, null);
      $name_url = $model['license_data'][0]['licenses']['components'][$component->id]['component_path'] ?? null;

      if ($license) {
        try {
          if ($license_url) {
            $url = Url::fromUri($license_url);
            $license_item = Link::fromTextAndUrl($license, $url)->toRenderable();
          }
          else {
            $license_item = ['#markup' => $license];
          }
        }
        catch (\InvalidArgumentException $e) {
          // Fallback to plaintext if $license_url is malformed.
          $license_item = ['#markup' => $license];
        }

        $license_item['#prefix'] = ' [';
        $license_item['#suffix'] = '] ';
      }
      else {
        $license_item = ['#markup' => ''];
      }

      try {
        if ($name_url) {
          $url = Url::fromUri($name_url);
          $component_item = Link::fromTextAndUrl($component->name, $url)->toRenderable();
        }
        else {
          $component_item = ['#markup' => $component->name];
        }
      }
      catch (\InvalidArgumentException $e) {
        // Fallback to plaintext if $name_url is malformed.
        $component_item = ['#markup' => $component->name];
      }

      $build["{$status}_components"]['#items'][] = [$component_item, $license_item];
    }

    return $build;
  }

}

