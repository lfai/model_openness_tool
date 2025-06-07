<?php declare(strict_types=1);

namespace Drupal\mof\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;

final class ModelEditForm extends ModelForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form += parent::form($form, $form_state);
    $form['#attributes']['novalidate'] = 'novalidate';

    $is_admin = $this->currentUser()->hasPermission('administer model');

    // Only admins can approve models.
    $form['status']['#access'] = $is_admin;

    // Show a message for non-admins indicating their changes will not be saved,
    // but rather a PR should be submitted from the YAML.
    if (!$is_admin) {
      // @todo Show a message here.
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
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);
    if ($this->currentUser()->hasPermission('administer model')) {
      $form_state->setRedirect('entity.model.canonical', ['model' => $this->entity->id()]);
    }
    else {
      $response = $this->entity->download();
      $form_state->setResponse($response);
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

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    // Save model entity only if we're an admin.
    if ($this->currentUser()->hasPermission('administer model')) {
      return parent::save($form, $form_state);
    }
  }

}
