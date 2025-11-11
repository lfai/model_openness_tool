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
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Return evaluation results if available, otherwise display user input form.
    return $form_state->get('evaluation') ?: parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form += parent::form($form, $form_state);
    $form['#attributes']['novalidate'] = 'novalidate';
    if (!$this->isAdmin()) {
      $form['help'] = [
        '#type' => 'markup',
        '#weight' => -150,
        '#markup' => 'For information on how to use this form, please consult the <a href="https://github.com/lfai/model_openness_tool/tree/main?tab=readme-ov-file#editing-a-model">Model editing section of the documentation</a>.',
      ];
    }
    // Only admins can approve models.
    $form['status']['#access'] = $this->isAdmin();

    // Start a session if we're a regular or anonymous user editing this model.
    // Changes will be saved to a session and not saved directly to the database.
    if (!$this->isAdmin() && !$this->session->isStarted()) {
      $this->session->start();
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

    // Build session data for model evaluation.
    if (!$this->isAdmin()) {
      $entity = $this->entity;
      $entity->enforceIsNew(TRUE);

      $this
        ->session
        ->set('model_session_evaluation', TRUE);

      $this
        ->session
        ->set('model_session_data', $form_state->getValues());

      $form_state->setRebuild(TRUE);

      $form_state
        ->set('evaluation', $this
        ->entityTypeManager
        ->getViewBuilder('model')
        ->view($entity));
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
      parent::save($form, $form_state);
      $form_state->setRedirect('entity.model.canonical', ['model' => $this->entity->id()]);
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
