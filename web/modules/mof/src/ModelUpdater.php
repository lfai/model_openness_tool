<?php declare(strict_types=1);

namespace Drupal\mof;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserStorageInterface;
use Drupal\user\UserInterface;
use Drupal\mof\Entity\Model;
use Drupal\mof\ModelInterface;

final class ModelUpdater {

  /** @var \Drupal\Core\Entity\EntityStorageInterface. */
  private readonly EntityStorageInterface $modelStorage;

  /** @var \Drupal\user\UserStorageInterface. */
  private readonly UserStorageInterface $userStorage;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   * @param \Drupal\mof\ComponentManagerInterface $componentManager
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ComponentManagerInterface $componentManager
  ) {
    $this->modelStorage = $entityTypeManager->getStorage('model');
    $this->userStorage = $entityTypeManager->getStorage('user');
  }

  /**
   * Check if a model exists by its name/label.
   *
   * @param array $model_data
   *   The model data in array format.
   * @return \Drupal\mof\ModelInterface|NULL
   *   The model if it exists; NULL otherwise.
   */
  public function exists(array $model_data): ?ModelInterface {
    $model = $this
      ->modelStorage
      ->loadByProperties(['label' => $model_data['name']]);

    if (empty($model)) {
      return NULL;
    }

    return reset($model);
  }

  /**
   * Update a model entity with supplied model data.
   *
   * @param \Drupal\mof\ModelInterface $model
   *   The model entity that will be updated.
   * @param array $model_data
   *   The model data we are updating $model with.
   * @param int
   *   Either SAVED_NEW or SAVED_UPDATED.
   */
  public function update(ModelInterface $model, array $model_data): int {
    $license_data = [];

    foreach ($model_data as $field => $value) {
      // @todo Rename these fields on the entity(?)
      if ($field === 'name') $field = 'label';
      if ($field === 'producer') $field = 'organization';

      if ($field === 'license') {
        $license_data['global'] = $value;
      }
      else if ($field === 'components') {
        $license_data['components'] = $this->processComponentLicenses($value);
        $model->set('license_data', ['licenses' => $license_data]);
        $model->set('components', array_keys($license_data['components']));
      }
      else if ($field === 'contact') {
        $model->set('uid', $this->processOwnerContact($value));
      }
      else if ($field === 'date') {
        $model->set('changed', strtotime($value));
      }
      else if ($field === 'github') {
        $parsed = parse_url($value);
        if (isset($parsed['path'])) {
          $model->set('github', ltrim($parsed['path'], '/'));
        }
      }
      else if ($field === 'huggingface') {
        $parsed = parse_url($value);
        if (isset($parsed['path'])) {
          $model->set('huggingface', ltrim($parsed['path'], '/'));
        }
      }
      else {
        $model->set($field, $value);
      }
    }

    $model->setStatus(Model::STATUS_APPROVED);
    return $model->save();
  }

  /**
   * Create a model entity.
   *
   * @param array $model_data
   *   The model data.
   * @return int *   Should always be SAVED_NEW.
   */
  public function create(array $model_data): int {
    $model = $this->modelStorage->create();
    return $this->update($model, $model_data);
  }

  /**
   * Process contact field.
   * Find or create a Drupal user entity.
   *
   * @param string $email
   *   An email address belonging to the model contact.
   * @return \Drupal\user\UserInterface.
   *   A Drupal user account or NULL if not found or cannot be created.
   */
  private function processOwnerContact(string $email): ?UserInterface {
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === FALSE) {
      return NULL;
    }

    $user = $this->userStorage->loadByProperties(['mail' => $email]);

    if (empty($user)) {
      $user = $this->userStorage->create([
        'mail' => $email,
        'name' => explode('@', $email)[0],
        'pass' => \Drupal::service('password')->hash(random_bytes(16)),
      ]);

      $user->save();
    }
    else {
      $user = reset($user);
    }

    return $user;
  }

  /**
   * Process licenses for each component of the model.
   *
   * @param array $license_data
   *   The license data to process for each component.
   * @return array
   *   The license array structured for a model entity.
   */
  private function processComponentLicenses(array $license_data): array {
    $licenses = [];

    foreach ($license_data as $component_data) {
      $component = $this
        ->componentManager
        ->getComponentByName($component_data['name']);

      $licenses[$component->id] = [
        'license' => $component_data['license'] ?? null,
        //'license_path' => $component_data['license_path'],
        //'component_path' => $component_data['location'],
      ];
    }

    return $licenses;
  }

}

