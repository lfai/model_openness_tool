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

    // Only admins can approve models.
    $form['status']['#access'] = $this->currentUser()->hasPermission('administer model');

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
