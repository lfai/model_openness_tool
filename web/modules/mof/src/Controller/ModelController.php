<?php declare(strict_types=1);

namespace Drupal\mof\Controller;

use Drupal\mof\ModelInterface;
use Drupal\mof\ModelEvaluatorInterface;
use Drupal\mof\ModelSerializerInterface;
use Drupal\mof\BadgeGeneratorInterface;
use Drupal\mof\Services\GitHubPullRequestManager;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\DependencyInjection\ContainerInterface;


final class ModelController extends ControllerBase {

  /** @var \Drupal\mof\ModelEvaluatorInterface. */
  private readonly ModelEvaluatorInterface $modelEvaluator;

  /** @var \Drupal\mof\BadgeGeneratorInterface. */
  private readonly BadgeGeneratorInterface $badgeGenerator;

  /** @var \Drupal\Core\Render\RendererInterface. */
  private readonly RendererInterface $renderer;

  /** @var \Symfony\Component\HttpFoundation\Session\Session. */
  private readonly Session $session;

  /** @var \Drupal\mof\ModelSerializerInterface. */
  private readonly ModelSerializerInterface $modelSerializer;

  /** @var \Drupal\mof\Services\GitHubPullRequestManager. */
  private readonly GitHubPullRequestManager $githubPrManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->modelEvaluator = $container->get('model_evaluator');
    $instance->badgeGenerator = $container->get('badge_generator');
    $instance->renderer = $container->get('renderer');
    $instance->session = $container->get('session');
    $instance->modelSerializer = $container->get('model_serializer');
    $instance->githubPrManager = $container->get('github_pr_manager');
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
   * This uses PNG images because HuggingFace doesn't support SVG.
   */
  public function badgePage(ModelInterface $model): array {
    $build = ['#markup' => $this->t('Use the following markdown to embed your model badges.')];
    $badges = $this->badgeGenerator->generate($model);

    for ($i = 1; $i <= 3; $i++) {
      $model_status = (string) $badges[$i]['#status'];
      if ($model_status == "Qualified")
        $status = 'qualified';
      elseif ($model_status == "Not met")
        $status = 'notmet';
      else
        $status = 'inprogress';
      $badge = Url::fromRoute('mof.model_badge_png', ['class' => $i, 'status' => $status]);

      $model_url = $model->toUrl()->setAbsolute(TRUE)->toString();

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
        '#value' => "[![mof-class{$i}-{$status}]({$badge->setAbsolute(TRUE)->toString()})]($model_url)",
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
      $response = new Response();
      $response->setContent($e->getMessage());
      $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
      return $response;
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
      $response = new Response();
      $response->setContent($e->getMessage());
      $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
      return $response;
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

  /**
   * Submit a pull request to GitHub with the evaluated model YAML.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with success or error message.
   */
  public function submitPullRequest(): JsonResponse {
    // Check if user is authenticated with GitHub
    if (!$this->githubPrManager->isAuthenticated()) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $this->t('You must be logged in with GitHub to submit a pull request.'),
        'login_url' => Url::fromRoute('social_auth.network.redirect', ['network' => 'github'], [
          'query' => ['destination' => Url::fromRoute('mof.model.evaluate_form')->toString()],
        ])->toString(),
      ], 401);
    }

    // Check if we have model session data
    if (!$this->session->has('model_session_data')) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $this->t('No model data found. Please evaluate a model first.'),
      ], 400);
    }

    try {
      // Get model data from session and create model entity
      $model_data = $this->session->get('model_session_data');
      $model = $this->entityTypeManager()->getStorage('model')->create($model_data);

      // Generate YAML content
      $yaml_content = $this->modelSerializer->toYaml($model);
      $model_name = $model->label();
      $filename = preg_replace('/[^a-zA-Z0-9-_]/', '-', $model_name) . '.yml';

      // GitHub repository details
      $source_owner = 'lfai';
      $repo = 'model_openness_tool';
      $branch_name = 'model-' . strtolower(preg_replace('/[^a-zA-Z0-9-]/', '-', $model_name)) . '-' . time();

      // Ensure fork exists
      $this->githubPrManager->ensureFork($source_owner, $repo);

      // Wait a moment for fork to be ready (GitHub needs time to create it)
      sleep(2);

      // Create branch
      $this->githubPrManager->createBranch($repo, $branch_name);

      // Commit file
      $commit_message = "Add model: $model_name";
      $this->githubPrManager->commitFile(
        $repo,
        $branch_name,
        "models/$filename",
        $yaml_content,
        $commit_message
      );

      // Generate PR body
      $pr_body = $this->generatePrBody($model);

      // Create pull request
      $pr_data = $this->githubPrManager->createPullRequest(
        $source_owner,
        $repo,
        $branch_name,
        "Add model: $model_name",
        $pr_body
      );

      return new JsonResponse([
        'success' => TRUE,
        'message' => $this->t('Pull request created successfully!'),
        'pr_url' => $pr_data['html_url'] ?? NULL,
        'pr_number' => $pr_data['number'] ?? NULL,
      ]);
    }
    catch (\Exception $e) {
      $this->getLogger('mof')->error('Failed to create pull request: @message', [
        '@message' => $e->getMessage(),
      ]);

      return new JsonResponse([
        'success' => FALSE,
        'error' => $this->t('Failed to create pull request: @message', [
          '@message' => $e->getMessage(),
        ]),
      ], 500);
    }
  }

  /**
   * Generate the pull request body with model details.
   *
   * @param \Drupal\mof\ModelInterface $model
   *   The model entity.
   *
   * @return string
   *   The PR body text.
   */
  private function generatePrBody(ModelInterface $model): string {
    $this->modelEvaluator->setModel($model);
    $evaluation = $this->modelEvaluator->evaluate();

    $body = "## Model Submission\n\n";
    $body .= "This PR adds the model evaluation for **" . $model->label() . "**.\n\n";

    $body .= "### Model Details\n";
    if ($model->getOrganization()) {
      $body .= "- **Producer**: " . $model->getOrganization() . "\n";
    }
    if ($model->getRepository()) {
      $body .= "- **Repository**: " . $model->getRepository() . "\n";
    }
    if ($model->getHuggingface()) {
      $body .= "- **HuggingFace**: " . $model->getHuggingface() . "\n";
    }

    $body .= "\n### MOF Classification\n";
    for ($class = 1; $class <= 3; $class++) {
      $status = $evaluation[$class]['qualified'] ? '✅ Qualified' : '❌ Not met';
      $body .= "- **Class $class**: $status\n";
    }

    $body .= "\n---\n";
    $body .= "*Submitted via [Model Openness Tool](https://mot.isitopen.ai) evaluation form.*\n";

    return $body;
  }

}
