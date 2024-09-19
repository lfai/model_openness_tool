<?php declare(strict_types=1);

namespace Drupal\mof;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mof\ModelInterface;

final class ModelUpdater {

  private readonly EntityStorageInterface $modelStorage;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ComponentManagerInterface $componentManager
  ) {
    $this->modelStorage = $entityTypeManager->getStorage('model');
  }

  public function exists(array $model_data): bool|ModelInterface {
    $model = $this
      ->modelStorage
      ->loadByProperties(['label' => $model_data['name']]);

    if (empty($model)) {
      return FALSE;
    }

    return reset($model);
  }

  public function update(ModelInterface $model, array $model_data): void {
    foreach ($model_data as $field => $value) {
      // @todo Rename these fields on the entity(?)
      if ($field === 'name') $field = 'label';
      if ($field === 'producer') $field = 'organization';
      if ($field === 'contact') continue;

      if ($field === 'components') {
        $license_data = $this->processLicenses($value);
        $model->set('license_data', ['licenses' => $license_data]);
        $model->set('components', array_keys($license_data));
      }
      else {
        $model->set($field, $value);
      }
    }

    $model->save();
  }

  public function create(array $model_data): void {
    $model = $this->modelStorage->create();
    $this->update($model, $model_data);
  }

  private function processLicenses(array $license_data): array {
    $licenses = [];

    foreach ($license_data as $component_data) {
      $component = $this
        ->componentManager
        ->getComponentByName($component_data['name']);

      $licenses[$component->id] = [
        'license' => $component_data['license_name'],
        'license_path' => $component_data['license_path'],
        'component_path' => $component_data['location'],
      ];
    }

    return $licenses;
  }

}

