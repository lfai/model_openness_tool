<?php

declare(strict_types=1);

namespace Drupal\mof;

use Drupal\mof\ModelInterface;
use Drupal\mof\ComponentManagerInterface;
use Drupal\Component\Serialization\Yaml;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * ModelSerializer class.
 */
final class ModelSerializer {

  /**
   * Construct a ModelSerializer instance.
   */
  public function __construct(
    private readonly SerializerInterface $serializer,
    private readonly ModelEvaluatorInterface $modelEvaluator,
    private readonly ComponentManagerInterface $componentManager
  ) {}

  /**
   * Transform model to an array for serialization.
   *
   * @param \Drupal\mof\ModelInterface $model
   *   The model to process.
   * @return array
   *   An array representing the model.
   */
  private function processModel(ModelInterface $model): array {
    $owner = $model->getOwner();

    $data = [
      'framework' => [
        'name' => 'Model Openness Framework',
        'version' => '1.0',
        'date' => '2024-12-15',
      ],
      'release' => [
        'name' => $model->label(),
        'version' => $model->getVersion() ?? '',
        'date' => date('Y-m-d', $model->getChangedTime()),
        'type' => $model->getType() ?? '',
        'architecture' => $model->getArchitecture() ?? '',
        'origin' => $model->getOrigin() ?? '',
        'producer' => $model->getOrganization() ?? '',
        'contact' => $owner->id() > 1 ? $owner->getEmail() : '',
      ],
    ];

    if ($model->getGithubSlug()) {
      $data['release']['github'] = 'https://github.com/' . $model->getGithubSlug();
    }
    if ($model->getHuggingfaceSlug()) {
      $data['release']['huggingface'] = 'https://huggingface.co/' . $model->getHuggingfaceSlug();
    }

    $completed = array_filter(
      $this->componentManager->getComponents(),
      fn($c) => in_array($c->id, $model->getCompletedComponents()));

    $licenses = $model->getLicenses();
    foreach ($completed as $component) {
      $data['release']['components'][] = [
        'name' => $component->name,
        'description' => $component->description,
        'location' => $licenses[$component->id]['component_path'],
        'license_name' => $licenses[$component->id]['license'],
        'license_path' => $licenses[$component->id]['license_path'],
      ];
    }

    return $data;
  }

  /**
   * Return a YAML representation of the model.
   *
   * @param \Drupal\mof\ModelInterface $model
   *   The model to convert to YAML.
   * @return string
   *   A string representing the model in YAML format.
   */
  public function toYaml(ModelInterface $model): string {
    return Yaml::encode($this->processModel($model));
  }

  /**
   * Return a JSON representation of the model.
   *
   * @param \Drupal\mof\ModelInterface $model
   *   The model to convert to JSON.
   * @return string
   *   A string representing the model in JSON format.
   */
  public function toJson(ModelInterface $model): string {
    return $this
      ->serializer
      ->serialize($this
      ->processModel($model), 'json', [
        'json_encode_options' => \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES
      ]);
  }

}

