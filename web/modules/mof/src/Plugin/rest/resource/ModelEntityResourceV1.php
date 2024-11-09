<?php declare(strict_types=1);

namespace Drupal\mof\Plugin\rest\resource;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Pager\Pager;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\mof\ModelInterface;
use Drupal\mof\ModelSerializerInterface;
use Drupal\mof\ModelEvaluatorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides a REST resource for Model entities.
 * version 1
 *
 * @RestResource(
 *   id = "model_v1",
 *   label = @Translation("Model v1"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/model/{model}",
 *     "collection" = "/api/v1/models"
 *   }
 * )
 */
final class ModelEntityResourceV1 extends ResourceBase {

  private readonly SqlEntityStorageInterface $modelStorage;

  /**
   * Constructs a Drupal\mof\Plugin\rest\resource\ModelEntityResourceV1 object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   * @param \Drupal\mof\ModelSerializerInterface $modelSerializer
   *   The model serializer service.
   * @param \Drupal\mof\ModelEvaluatorInterface $modelEvaluator
   *   The model evaluator service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    $serializer_formats,
    LoggerInterface $logger,
    EntityTypeManagerInterface $entity_type_manager,
    private readonly ModelSerializerInterface $modelSerializer,
    private readonly ModelEvaluatorInterface $modelEvaluator
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $serializer_formats,
      $logger
    );

    $this->modelStorage = $entity_type_manager->getStorage('model');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('api/v1/model'),
      $container->get('entity_type.manager'),
      $container->get('model_serializer'),
      $container->get('model_evaluator')
    );
  }

  /**
   * Responds to model_v1 GET requests.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request.
   * @param \Drupal\mof\ModelInterface $model
   *   The model entity. If NULL a model collection is returned.
   * 
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   The response containing model data.
   */
  public function get(Request $request, ?ModelInterface $model = NULL): CacheableJsonResponse {
    return ($model) ? $this->getModel($model) : $this->listModels($request);
  }

  /**
   * Retrieve a single model.
   * 
   * This method returns a cacheable JSON response
   * containing a model with classification information.
   *
   * @param \Drupal\mof\ModelInterface $model
   *   The model entity.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   The response containing the model.
   */
  protected function getModel(ModelInterface $model): CacheableJsonResponse {
    $json = $this->classify($model);
    $response = new CacheableJsonResponse($json);
    $response->addCacheableDependency($model);
    return $response;
  }

  /**
   * Retrieve a collection of models.
   *
   * This method returns a cacheable JSON response containing
   * a list of models with pagination details.
   *
   * Query parameters:
   * - page (int):  Current page number (1-indexed).
   *                Defaults to 1 if not specified.
   * - limit (int): The maximum number of models to display per page.
   *                Defaults to 100 if not specified.
   * 
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   The response containing models and pager details.
   */
  protected function listModels(Request $request): CacheableJsonResponse {
    $collection = ['pager' => [], 'models' => []];

    $page = max(1, (int) $request->query->get('page', 1));
    $limit = max(1, (int) $request->query->get('limit', 100));

    $pager = new Pager($this->getModelCount(), $limit, $page);
    $collection['pager']['total_items'] = $pager->getTotalItems();
    $collection['pager']['total_pages'] = $pager->getTotalPages();
    $collection['pager']['current_page'] = $page;

    $models = $this
      ->modelStorage
      ->getQuery()
      ->accessCheck(TRUE)
      ->sort('id', 'ASC')
      ->range(($page - 1) * $limit, $limit)
      ->execute();

    foreach ($this
      ->modelStorage
      ->loadMultiple($models) as $model) {

      $collection['models'][] = $this->classify($model);
    }

    $cache_metadata = new CacheableMetadata();
    $cache_metadata->setCacheMaxAge(3600);
    $cache_metadata->addCacheContexts(['url.query_args:limit', 'url.query_args:page']);

    $response = new CacheableJsonResponse($collection);
    $response->addCacheableDependency($cache_metadata);
    return $response;
  }

  /**
   * Return a total number of models in the database.
   *
   * @return int
   *   The number of models.
   */ 
  protected function getModelCount(): int {
    return $this
      ->modelStorage
      ->getQuery()
      ->accessCheck(TRUE)
      ->count()
      ->execute();
  }

  /**
   * Evaluate and add classification information to model.
   *
   * @param \Drupal\mof\ModelInterface $model
   *   The model entity.
   *
   * @return array
   *   An array containing model data suitable for JSON encoding.
   */
  protected function classify(ModelInterface $model): array {
    $evaluator = $this->modelEvaluator->setModel($model);

    $json = $this->modelSerializer->toJson($model);
    $json = json_decode($json, TRUE);

    $class = $evaluator->getClassification(FALSE);
    $json['classification']['class'] = $class;
    $json['classification']['label'] = $evaluator->getClassLabel($class);

    for ($i = 1; $i <= 3; ++$i) {
      $json['classification']['progress'][$i] = $evaluator->getProgress($i);
    }

    return ['id' => $model->id(), ...$json];
  }

  /**
   * {@inheritdoc}
   */
  public function routes() {
    $routes = new RouteCollection();
    $definition = $this->getPluginDefinition();

    foreach ($definition['uri_paths'] as $key => $uri) {
      $route = $this->getBaseRoute($uri, 'GET');

      if (strstr($uri, '{model}')) {
        $route->setOption('parameters', ['model' => ['type' => 'entity:model']]);
        $route->setRequirement('_entity_access', 'model.view');
      }

      $routes->add("{$this->pluginId}.{$key}.GET", $route);
    }

    return $routes;
  }

}

