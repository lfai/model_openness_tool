<?php

declare(strict_types=1);

namespace Drupal\mof\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\file\FileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class ModelImportForm extends FormBase {

  private readonly EntityStorageInterface $modelStorage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $form = new static();
    $form->modelStorage = $container->get('entity_type.manager')->getStorage('model');
    $form->setMessenger($container->get('messenger'));
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'model_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['csv'] = [
      '#type' => 'file',
      '#title' => $this->t('Upload CSV'),
      '#description' => $this->t('Upload a model-formatted CSV file.'),
      '#required' => TRUE,
      '#upload_validators' => ['FileExtension' => ['extensions' => 'csv']],
      '#upload_location' => 'temporary://',
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Upload'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (($file = $this->getUploadedFile($form['csv'], $form_state)) !== NULL) {
      $mime = $file->getMimeType();
      if ($mime !== 'text/csv' && $mime !== 'application/csv') {
        $form_state->setErrorByName('csv', $this->t('File must be valid CSV format'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (($file = $this->getUploadedFile($form['csv'], $form_state)) !== NULL) {
      $batch = [
        'operations' => [],
        'finished' => '\Drupal\mof\Batch\ModelCsvImport::finished',
        'title' => $this->t('Importing models...'),
        'init_message' => $this->t('Starting model import...'),
        'progress_message' => $this->t('Processing @current of @total'),
        'error_message' => $this->t('Model import failed'),
      ];

      $data = $this->parseCsvFile($file->getFileUri());
      $data = array_filter($data, fn($a) => $a['Name'] !== '');

      $ids = $this
        ->modelStorage
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('label', array_column($data, 'Name'), 'IN')
        ->execute();

      $existing_models = [];
      foreach ($this
        ->modelStorage
        ->loadMultiple($ids) as $model) {
        $existing_models[] = $model->label();
      }

      $skip = $import = 0;
      foreach ($data as $model) {
        if (in_array($model['Name'], $existing_models)) {
          $skip++;
        }
        else {
          $import++;
          $batch['operations'][] = ['\Drupal\mof\Batch\ModelCsvImport::import', [$model]];
        }
      }

      $this->messenger->addMessage($this->t('Importing @num records', ['@num' => $import]));
      $this->messenger->addMessage($this->t('Skipped @num records', ['@num' => $skip]));

      batch_set($batch);
    }
  }

  /**
   * Get file from form submission.
   */
  private function getUploadedFile(array $element, FormStateInterface $form_state): ?FileInterface {
    $file = _file_save_upload_from_form($element, $form_state, 0);
    return $file !== NULL && $file !== FALSE ? $file : NULL;
  }

  /** 
   * Parse CSV file.
   */
  private function parseCsvFile(string $file_path): ?array {
    $data = [];

    if (($fh = fopen($file_path, 'r')) === FALSE) {
      return NULL;
    }

    $i = 0; $keys = [];
    while (($row = fgetcsv($fh)) !== FALSE) {
      if ($i === 0) {
        // Skip first line.
      }
      else if ($i === 1) {
        $keys = array_values($row);
      }
      else {
        $data[] = array_combine($keys, $row);
      }
      $i++;
    }

    fclose($fh);
    return $data;
  }

}

