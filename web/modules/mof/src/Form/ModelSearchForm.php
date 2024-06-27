<?php declare(strict_types=1);

namespace Drupal\mof\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

final class ModelSearchForm extends FormBase {

  private readonly Request $request;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $form = new static();
    $form->request = $container->get('request_stack')->getCurrentRequest();
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'model_search_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $req = $this->request;

    $form['search'] = [
      '#type' => 'details',
      '#title' => $this->t('Search filters'),
      '#open' => $req->get('label') || $req->get('org') ? TRUE : FALSE,
    ];

    $form['search']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $req->get('label') ?? '',
    ];

    $form['search']['org'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Organization'),
      '#default_value' => $req->get('org') ?? '',
    ];

    $form['search']['actions']['wrapper'] = [
      '#type' => 'container',
    ];

    $form['search']['actions']['wrapper']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
    ];

    if ($req->getQueryString()) {
      $form['search']['actions']['wrapper']['reset'] = [
        '#type' => 'submit',
        '#value' => $this->t('Reset'),
        '#submit' => ['::resetForm'],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $query = $this->request->query->all();

    $label = $form_state->getValue('name') ?? FALSE;
    if ($label) {
      $query['label'] = $label;
    }
    else if (isset($query['label']) && !$label) {
      unset($query['label']);
    }

    $org = $form_state->getValue('org') ?? FALSE;
    if ($org) {
      $query['org'] = $org;
    }
    else if (isset($query['org']) && !$org) {
      unset($query['org']);
    }

    $form_state->setRedirect('entity.model.collection', $query);
  }

  /** 
   * Reset the form.
   */
  public function resetForm(array $form, FormStateInterface $form_state) {
    $form_state->setRedirect('entity.model.collection');
  }

}

