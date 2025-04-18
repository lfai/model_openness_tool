<?php declare(strict_types=1);

namespace Drupal\mof\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;

final class ModelSubmitForm extends ModelForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form += parent::form($form, $form_state);

    // @todo use _title_callback on route to set this in ModelController::pageTitle().
    if ($this->entity->isNew()) {
      $form['#title'] = $this->t('Submit model');
    }
    else {
      $form['#title'] = $this->t('@model: Edit', ['@model' => $this->entity->label()]);
    }

    $form['#attached']['library'][] = 'mof/model-submit';
    $form['#attributes']['novalidate'] = 'novalidate';

    // Only admins can approve models.
    $form['status']['#access'] = $this->currentUser()->hasPermission('administer model');

    // Open when ajax rebuilds the form.
    if ($form_state->isRebuilding()) {
      $form['details']['#open'] = FALSE;
    }

    // Add fields to capture license and component paths.
    $license_data = $this->entity->getLicenses();
    foreach (['code', 'data', 'document'] as $group) {
      foreach ($form[$group]['components'] as $id => $component) {
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
          ],
          '#type' => 'container',
          '#attributes' => [
            'class' => ['license-details'],
          ],
          '#states' => [
            'invisible' => [
              ':input[name="components['.$id.'][license]"]' => [
                ['empty' => TRUE], 'or', ['value' => 'Component not included'], 'or', ['value' => 'License not specified']
              ],
            ],
          ],
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    if (preg_match('/\s/', $form_state->getValue('label')[0]['value'])) {
      $form_state->setErrorByName('label', $this->t('Model name cannot have spaces.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Submit');
    return $actions;
  }

}
