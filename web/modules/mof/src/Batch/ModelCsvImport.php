<?php declare(strict_types=1);

namespace Drupal\mof\Batch;

use Drupal\mof\Entity\Model;

class ModelCsvImport {

  /**
   * Called when the batch job has finished.
   */
  public static function finished($success, $results, $operations) {
    if ($success) {
      if (isset($results['imported']) && $results['imported'] > 1) {
        \Drupal::messenger()->addMessage(t('Imported @num models', ['@num' => $results['imported']]));
      }
    }
  }

  /**
   * Import model from CSV data.
   */
  public static function import(array $data, array &$context) {
    $context['message'] = t('Importing model %name', ['%name' => $data['Name']]);

    if (!isset($context['results']['imported'])) {
      $context['results']['imported'] = 0;
    }

    $model = [
      'label' => $data['Name'],
      'description' => $data['Description'] ?: '-',
      'version' => $data['Version/Parameters'],
      'organization' => $data['Organization'],
      'type' => self::getAllowedValueKey('type', $data['Model Type']) ?? '',
      'architecture' => self::getAllowedValueKey('architecture', $data['Architecture']) ?? '',
      'treatment' => self::getAllowedValueKey('treatment', $data['Training Treatment']) ?? '',
      'origin' => $data['Base Model'],
      'github' => self::getPathFromUrl($data['Github Repo URL']),
      'huggingface' => self::getPathFromUrl($data['HuggingFace Model URL']),
      'approver' => self::setApprover($data['Researcher']), 'status' => 'approved',
    ];

    $license_data = [
      9 => [
        'license' => $data['Model Architecture'] ?: 'Pending evaluation',
        'license_path' => '',
        'component_path' => '',
      ],
      16 => [
        'license' => $data['Data Preprocessing Code'] ?: 'Pending evaluation',
        'license_path' => '',
        'component_path' => '',
      ],
      7 => [
        'license' => $data['Training Code'] ?: 'Pending evaluation',
        'license_path' => '',
        'component_path' => '',
      ],
      8 => [
        'license' => $data['Inference Code'] ?: 'Pending evaluation',
        'license_path' => '',
        'component_path' => '',
      ],
      18 => [
        'license' => $data['Evaluation Code'] ?: 'Pending evaluation',
        'license_path' => '',
        'component_path' => '',
      ],
      22 => [
        'license' => $data['Supporting Libraries and Tools'] ?: 'Pending evaluation',
        'license_path' => '',
        'component_path' => '',
      ],
      15 => [
        'license' => $data['Datasets'] ?: 'Pending evaluation',
        'license_path' => '',
        'component_path' => '',
      ],
      10 => [
        'license' => $data['Model Parameters (Final)'] ?: 'Pending evaluation',
        'license_path' => '',
        'component_path' => '',
      ],
      17 => [
        'license' => $data['Model Metadata'] ?: 'Pending evaluation',
        'license_path' => '',
        'component_path' => '',
      ],
      24 => [
        'license' => $data['Model Parameters (Intermediate)'] ?: 'Pending evaluation',
        'license_path' => '',
        'component_path' => '',
      ],
      19 => [
        'license' => $data['Evaluation Data'] ?: 'Pending evaluation',
        'license_path' => '',
        'component_path' => '',
      ],
      20 => [
        'license' => $data['Sample Model Outputs'] ?: 'Pending evaluation',
        'license_path' => '',
        'component_path' => '',
      ],
      12 => [
        'license' => $data['Evaluation Results'] ?: 'Pending evaluation',
        'license_path' => '',
        'component_path' => '',
      ],
      13 => [
        'license' => $data['Model Card'] ?: 'Pending evaluation',
        'license_path' => '',
        'component_path' => '',
      ],
      14 => [
        'license' => $data['Data Card'] ?: 'Pending evaluation',
        'license_path' => '',
        'component_path' => '',
      ],
      11 => [
        'license' => $data['Technical Report'] ?: 'Pending evaluation',
        'license_path' => '',
        'component_path' => '',
      ],
      21 => [
        'license' => $data['Research Paper'] ?: 'Pending evaluation',
        'license_path' => '',
        'component_path' => '',
      ],
    ];

    $model['license_data']['licenses'] = $license_data;
    $model['components'] = array_keys($license_data);
    $entity = Model::create($model);

    if ($entity->save() === 1) {
      $context['results']['imported']++;
    }
  }

  public static function getAllowedValueKey(string $field, string $label): ?string {
    $label = strtolower($label);

    switch ($field) {
    case 'type':
      $map = [
        'language model' => 'language',
        'vision model' => 'vision',
        'image model' => 'image',
        'audio model' => 'audio',
        'video model' => 'video',
        '3d model' => '3d',
        'code model' => 'code',
        'multimodal model' => 'multimodal',
        'other model' => 'other',
      ];
      break;

    case 'architecture':
      $map = [
        'transformer' => 'transformer',
        'transformer (decoder-only)' => 'transformer decoder',
        'transformer (encoder-only)' => 'transformer encoder',
        'transformer (encoder-decoder)' => 'transformer encoder-decoder',
        'decoder-only' => 'decoder',
        'encoder-only' => 'encoder',
        'undisclosed' => 'undisclosed',
        'diffusion' => 'diffusion',
        'rnn' => 'RNN',
        'cnn' => 'CNN',
        'lstm' => 'LSTM',
        'nerf' => 'NeRF',
        'hybrid' => 'hybrid',
        'other' => 'other',
      ];
      break;

    case 'treatment':
      $map = [
        'pre-trained' => 'pre-trained',
        'instruct fine-tuned' => 'instruct fine-tuned',
        'chat fine-tuned' => 'chat fine-tuned',
      ];
    }

    return $map[$label] ?? NULL;
  }

  public static function getPathFromUrl(string $url): string {
    $parsed = parse_url($url);
    return isset($parsed['path']) ? ltrim($parsed['path'], '/') : '';
  }

  public static function setApprover(string $researcher): int {
    $username = strtolower(str_replace(' ', '.', $researcher));
    $user_storage = \Drupal::entityTypeManager()->getStorage('user');
    $user = $user_storage->loadByProperties(['name' => $username]);

    if (!empty($user)) {
      return (int)reset($user)->id();
    }

    // Generate new user with random password.
    $user = $user_storage->create([
      'name' => $username,
      'pass' => \Drupal::service('password')->hash(random_bytes(16)),
      'mail' => '',
    ]);

    $user->save();
    return (int)$user->id();
  }

}

