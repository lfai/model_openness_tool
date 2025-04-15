<?php declare(strict_types=1);

namespace Drupal\mof\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * @file
 * Provides form processing for evaluating model openness and license compliance.
 */
final class ModelEvaluateForm extends ModelForm {

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
    $form['#title'] = $this->t('Evaluate model');

    // Hide elements that aren't needed for evaluation only.
    $hide = [
      'label',
      'description',
      'version',
      'organization',
      'type',
      'architecture',
      'treatment',
      'origin',
      'revision_information',
      'github',
      'huggingface',
      'status',
    ];

    foreach ($hide as $field_name) {
      if (isset($form[$field_name])) {
        $form[$field_name]['#access'] = FALSE;
      }
    }

    return $form; 
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Evaluate');

    if ($this->session->get('model_session_data') !== NULL) {
      $actions['reset'] = [
        '#type' => 'submit',
        '#limit_validation_errors' => [],
        '#value' => $this->t('Start over'),
        '#submit' => [[$this, 'resetForm']],
      ];
    }

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);
    $this
      ->session
      ->set('model_session_evaluation', TRUE);
    $this
      ->session
      ->set('model_session_data', $form_state
      ->getValues());
    $form_state
      ->setRebuild(TRUE);
    $form_state
      ->set('evaluation', $this
      ->entityTypeManager
      ->getViewBuilder('model')
      ->view($this->entity));
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    // We do not save the model when using `evaluate model` form.
    // Models are only saved when using `submit model` form.
    return 0;
  }

  /**
   * Clear session variables.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function resetForm(array &$form, FormStateInterface $form_state): void {
    $this->session->remove('model_session_evaluation');
    $this->session->remove('model_session_data');
  }

}
