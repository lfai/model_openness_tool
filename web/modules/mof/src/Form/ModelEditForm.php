<?php declare(strict_types=1);

namespace Drupal\mof\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;

/**
 * @file
 * Handles form processing logic for editing a model entity.
 *
 * Any user can edit an existing model, but only administrators can save
 * changes directly. For non-admin users, the edited model will be exported
 * as a YAML file for manual review and submission.
 */
final class ModelEditForm extends ModelForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form += parent::form($form, $form_state);
    $form['#attributes']['novalidate'] = 'novalidate';

    // Only admins can approve models.
    $form['status']['#access'] = $this->isAdmin();

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
    if ($this->isAdmin()) {
      // Redirect user to evaluation/ model view page.
      $form_state->setRedirect('entity.model.canonical', ['model' => $this->entity->id()]);
    }
    else {
      // Download YAML-formatted model.
      $response = $this->entity->download();
      $form_state->setResponse($response);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);

    $actions['submit']['#value'] = $this->isAdmin()
      ? $this->t('Save')
      : $this->t('Evaluate');

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    // Save model entity only if we're an admin.
    if ($this->isAdmin()) {
      return parent::save($form, $form_state);
    }
  }

  /**
   * Check if the current user is an admin.
   *
   * @return bool TRUE if admin, FALSE if not admin.
   */
  private function isAdmin(): bool {
    return $this->currentUser()->hasPermission('administer model');
  }

}
