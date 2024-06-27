<?php

declare(strict_types=1);

namespace Drupal\mof;

use Drupal\mof\ModelInterface;
use Drupal\mof\ComponentManagerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * ModelSerializer class.
 */
final class ModelSerializer {

  /**
   * Construct a ModelSerializer instance.
   */
  public function __construct(
    private SerializerInterface $serializer,
    private ModelEvaluatorInterface $modelEvaluator,
    private ComponentManagerInterface $componentManager
  ) {}

  public function toJson(ModelInterface $model): string {
    $owner = $model->getOwner();

    $json = [
      'framework' => [
        'name' => 'Model Openness Framework',
        'version' => '1.0',
        'date' => '2024-12-15',
      ],
      'release' => [
        'name' => $model->label(),
        'version' => $model->getVersion(),
        'date' => date('Y-m-d', $model->getChangedTime()),
        'type' => $model->getType(),
        'architecture' => $model->getArchitecture(),
        'origin' => $model->getOrigin(),
        'producer' => $model->getOrganization(),
        'contact' => $owner->id() > 1 ? $owner->getEmail() : '',
        'mof_class' => $this->modelEvaluator->setModel($model)->getClassification(),
      ],
    ];

    $licenses = $model->getLicenses();
    $completed = array_filter($this->componentManager->getComponents(), fn($c) => in_array($c->id, $model->getCompletedComponents()));

    foreach ($completed as $component) {
      $json['components'][$component->name] = [
        'description' => $component->description,
        'location' => $licenses[$component->id]['component_path'],
        'license_name' => $licenses[$component->id]['license'],
        'license_path' => $licenses[$component->id]['license_path'],
      ];
    }

    return $this
      ->serializer
      ->serialize($json, 'json', ['json_encode_options' => \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES]);
  }

}

