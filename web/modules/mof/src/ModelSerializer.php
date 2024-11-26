<?php

declare(strict_types=1);

namespace Drupal\mof;

use Drupal\mof\ModelInterface;
use Drupal\mof\ComponentManagerInterface;
use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Exception\UnsupportedFormatException;

/**
 * ModelSerializer class.
 */
final class ModelSerializer implements ModelSerializerInterface {

  /**
   * Construct a ModelSerializer instance.
   */
  public function __construct(
    private readonly SerializerInterface $serializer,
    private readonly ModelEvaluatorInterface $modelEvaluator,
    private readonly ComponentManagerInterface $componentManager
  ) {}

  /**
   * {@inheritdoc}
   */
  public function normalize(ModelInterface $model): array {
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
   * {@inheritdoc}
   */
  public function toYaml(ModelInterface $model): string {
    try {
      return Yaml::encode($this->normalize($model));
    }
    catch (InvalidDataTypeException $e) {
      // @todo Log exception.
    }
  }

  /**
   * {@inheritdoc}
   */
  public function toJson(ModelInterface $model): string {
    try {
      return $this
        ->serializer
        ->serialize($this->normalize($model), 'json', [
          'json_encode_options' => \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES
        ]);
    }
    catch (UnsupportedFormatException $e) {
      // @todo Log exception.
    }
  }

}

