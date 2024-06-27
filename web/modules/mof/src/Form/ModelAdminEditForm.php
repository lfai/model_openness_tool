<?php

declare(strict_types=1);

namespace Drupal\mof\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;

final class ModelAdminEditForm extends ModelForm {

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Admin's bypass form/entity validation.
    $form_state->setTemporaryValue('entity_validated', TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $this->entity->set('github', $form_state->getValue('github_admin'));
    return parent::save($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form += parent::form($form, $form_state);
    $entity = $this->entity;

    $form['#attached']['library'][] = 'mof/model-submit';
    $form['#attributes']['novalidate'] = 'novalidate';

    // Admins cannot select/change github repo.
    $form['github']['#access'] = FALSE;

    // But admins can freehand a github repo name.
    $form['github_admin'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Github'),
      '#weight' => 10,
      '#parents' => ['github_admin'],
      '#default_value' => $entity->get('github')->value,
    ];

    $model_details = [
      'label',
      'description',
      'version',
      'organization',
      'type',
      'architecture',
      'treatment',
      'origin',
      'revision_information',
      'github_admin',
      'huggingface',
    ];

    $form['details'] = [
      '#type' => 'details',
      '#title' => $this->t('Model details'),
      '#open' => FALSE,
      '#weight' => -90,
      '#prefix' => '<div id="details-wrap">',
      '#suffix' => '</div>',
      '#open' => TRUE,
    ];

    // Move entity defined fields into a details element.
    foreach ($model_details as $field) {
      $form['details'][$field] = $form[$field];
      unset($form[$field]);
    }

    // Consolidate licenses into one big datalist.
    // Admins can assign any license to any component.
    $licenses = $form['license'];

    $form['license'] = [
      '#type' => 'html_tag',
      '#tag' => 'datalist',
      '#attributes' => ['id' => 'license-datalist'],
      'licenses' => [],
    ];

    foreach ($licenses as $element) {
      foreach ($element['licenses'] as $license) {
        if (!in_array($license, $form['license']['licenses'])) {
          $form['license']['licenses'][] = $license;
        }
      }
    }

    // Add fields to capture license and component paths.
    $license_data = $this->entity->getLicenses();
    foreach (['code', 'data', 'document'] as $group) {
      foreach ($form[$group]['components'] as $id => $component) {

        // Use consolidated list for license datalist.
        $form[$group]['components'][$id]['label_wrap']['license']['#attributes']['list'] = 'license-datalist';

        $form[$group]['components'][$id]['details'] = [
          'license_path' => [
            '#type' => 'textfield',
            '#title' => $this->t('License path'),
            '#default_value' => $license_data[$id]['license_path'] ?? '',
            '#parents' => ['components', $id, 'license_path'],
            '#wrapper_attributes' => [
              'class' => ['license-path-wrapper'],
            ],
            '#attributes' => [
              'class' => ['license-path'],
              'autocomplete' => 'off',
            ],
            '#states' => [
              'optional' => [
                ':input[name="components['.$id.'][license]"]' => ['empty' => TRUE],
              ],
            ],
          ],
          'component_path' => [
            '#type' => 'textfield',
            '#title' => $this->t('Component path'),
            '#default_value' => $license_data[$id]['component_path'] ?? '',
            '#parents' => ['components', $id, 'component_path'],
            '#wrapper_attributes' => [
              'class' => ['component-path-wrapper'],
            ],
            '#attributes' => [
              'class' => ['component-path'],
              'autocomplete' => 'off',
            ],
            '#states' => [
              'optional' => [
                ':input[name="components['.$id.'][license]"]' => ['empty' => TRUE],
              ],
            ],
          ],
          '#type' => 'container',
          '#attributes' => [
            'class' => ['license-details'],
          ],
          '#states' => [
            'invisible' => [
              ':input[name="components['.$id.'][license]"]' => [
                ['empty' => TRUE], 'or', ['value' => 'Component not included'],
              ],
            ],
          ],
        ];
      }
    }

    return $form;
  }

}
