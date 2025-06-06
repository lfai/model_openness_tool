<?php declare(strict_types=1);

namespace Drupal\mof\Controller;

use Drupal\mof\ModelInterface;
use Drupal\mof\ModelEvaluatorInterface;
use Drupal\mof\BadgeGeneratorInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\DependencyInjection\ContainerInterface;


final class ModelController extends ControllerBase {

  /** @var \Drupal\mof\ModelEvaluatorInterface. */
  private readonly ModelEvaluatorInterface $modelEvaluator;

  /** @var \Drupal\mof\BadgeGeneratorInterface. */
  private readonly BadgeGeneratorInterface $badgeGenerator;

  /** @var \Drupal\Core\Render\RendererInterfce. */
  private readonly RendererInterface $renderer;

  /** @var \Symfony\Component\HttpFoundation\Session\Session. */
  private readonly Session $session;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->modelEvaluator = $container->get('model_evaluator');
    $instance->badgeGenerator = $container->get('badge_generator');
    $instance->renderer = $container->get('renderer');
    $instance->session = $container->get('session');
    return $instance;
  }

  /**
   * Set the page title to the model name.
   */
  public function pageTitle(Request $request, ModelInterface $model): string|TranslatableMarkup {
    switch ($request->attributes->get('_route')) {
    case 'entity.model.badge':
      $subtitle = $this->t('Badges');
      break;
    }

    $t_args = ['@model_name' => $model->label()];

    if (isset($subtitle)) {
      $t_args['@subtitle'] = $subtitle;
      return $this->t('@model_name: @subtitle', $t_args);
    }

    return $this->t('@model_name', $t_args);
  }

  /**
   * Display instructions/ markdown code for embedding badges.
   */
  public function badgePage(ModelInterface $model): array {
    $build = ['#markup' => $this->t('Use the following markdown to embed your model badges.')];
    $badges = $this->badgeGenerator->generate($model);

    for ($i = 1; $i <= 3; $i++) {
      $badge = Url::fromRoute('mof.model_badge', ['model' => $model->id(), 'class' => $i]);

      $build[$i] = [
        '#type' => 'container',
      ];

      $build[$i]['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->modelEvaluator->getClassLabel($i),
      ];

      $build[$i]['badge'] = $badges[$i];

      $build[$i]['md'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => "![mof-class{$i}]({$badge->setAbsolute(TRUE)->toString()})",
        '#weight' => 10,
      ];

      $build[$i]['md']['copy'] = [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#attributes' => ['class' => ['btn-copy']],
        '#value' => '<i class="fas fa-copy icon"></i>',
      ];
    }

    $build['#attached']['library'][] = 'mof/model-badge';
    return $build;
  }

  /**
   * Return an SVG badge for specified model and class.
   */
  public function badge(ModelInterface $model, int $class): Response {
    $badges = $this->badgeGenerator->generate($model);
    $svg = (string)$this->renderer->render($badges[$class]);

    $response = new Response();
    $response->setContent($svg);
    $response->headers->set('Content-Length', (string)strlen($svg));
    $response->headers->set('Content-Type', 'image/svg+xml');

    return $response;
  }

  /**
   * Download a YAML representation of a model stored in the user's session data.
   * If no session data is available, return a string indicating such.
   *
   * @return \Symfony\Component\HttpFoundation\Response.
   */
  public function download(): Response {
    if ($this->session->has('model_session_data')) {
      $model = $this->session->get('model_session_data');
      $model = $this->entityTypeManager()->getStorage('model')->create($model);
      return $this->yaml($model);
    }

    $response = new Response();
    $response->setContent((string)$this->t('No model data defined.'));
    return $response;
  }

  /**
   * Return a yaml representation of the model.
   */
  public function yaml(ModelInterface $model): Response {
    try {
      return $model->download('yaml');
    }
    catch (\InvalidArgumentException $e) {
      $response->setContent($e->getMessage());
      $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
  }

  /**
   * Return a json file representation of the model.
   */
  public function json(ModelInterface $model): Response {
    try {
      return $model->download('json');
    }
    catch (\InvalidArgumentException $e) {
      $response->setContent($e->getMessage());
      $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
  }

  /**
   * List model collection for admins.
   */
  public function collection(): array|RedirectResponse {
    return $this->entityTypeManager()->getHandler('model', 'admin_list_builder')->render();
  }

  /**
   * Approve a model.
   */
  public function setStatus(ModelInterface $model, string $status): RedirectResponse {
    $model->setApprover($this->currentUser())->setStatus($status)->save();
    return $this->redirect('entity.model.admin_collection');
  }

}

