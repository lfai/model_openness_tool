<?php declare(strict_types=1);

namespace Drupal\mof;

use Drupal\mof\ModelInterface;
use Drupal\mof\ComponentManagerInterface;
use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Exception\UnsupportedFormatException;
use Psr\Log\LoggerInterface;

/**
 * ModelSerializer class.
 */
final class ModelSerializer implements ModelSerializerInterface {

  /**
   * Construct a ModelSerializer instance.
   */
  public function __construct(
    private readonly SerializerInterface $serializer,
    private readonly ComponentManagerInterface $componentManager,
    private readonly LoggerInterface $logger
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
        'repository' => $model->getRepository() ?? '',
        'huggingface' => $model->getHuggingface() ?? '',
      ],
    ];

    /**
     * An array of Component objects that are included in the model.
     * @var \Drupal\mof\Component[] $completed
     */
    $completed = array_filter(
      $this->componentManager->getComponents(),
      fn($c) => in_array($c->id, $model->getComponents()));

    // Build global licenses section.
    $licenses = $model->getLicenses();
    $data['release']['license'] = [];

    if (isset($licenses['global'])) {
      foreach ($licenses['global'] as $key => $type) {
        $data['release']['license'][$key]['name'] = $type['name'];
        $data['release']['license'][$key]['path'] = $type['path'];
      }
    }

    // Build component section of all included components.
    foreach ($completed as $component) {
      $data['release']['components'][] = [
        'name' => $component->name,
        'description' => $component->description,
      ];

      // Component must have a global license attached.
      if (!isset($licenses['components'][$component->id])) continue;

      // Process component-specific license.
      $delta = array_key_last($data['release']['components']);
      foreach ($licenses['components'][$component->id] as $key => $value) {
        if ($key === 'license' && $value === '') {
          $data['release']['components'][$delta]['license'] = 'unlicensed';
        }
        else if ($value !== '') {
          $data['release']['components'][$delta][$key] = $value;
        }
      }
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
      $this->logger->error('@exception', ['@exception' => $e->getMessage()]);
      throw new \RuntimeException('Failed to convert model to YAML.', $e->getCode(), $e);
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
      $this->logger->error('@exception', ['@exception' => $e->getMessage()]);
      throw new \RuntimeException('Failed to convert model to JSON.', $e->getCode(), $e);
    }
  }

}

