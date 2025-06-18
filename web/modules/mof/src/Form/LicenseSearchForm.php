<?php declare(strict_types=1);

namespace Drupal\mof\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

final class LicenseSearchForm extends FormBase {

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
    return 'license_search_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $req = $this->request;

    $form['search'] = [
      '#type' => 'details',
      '#title' => $this->t('Search filters'),
      '#open' => $req->get('name') || $req->get('license_id') ? TRUE : FALSE,
    ];

    $form['search']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $req->get('name') ?? '',
    ];

    $form['search']['license_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('License ID'),
      '#default_value' => $req->get('license_id') ?? '',
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

    $name = $form_state->getValue('name') ?? FALSE;
    if ($name) {
      $query['name'] = $name;
    }
    else if (isset($query['name']) && !$name) {
      unset($query['name']);
    }

    $license_id = $form_state->getValue('license_id') ?? FALSE;
    if ($license_id) {
      $query['license_id'] = $license_id;
    }
    else if (isset($query['license_id']) && !$license_id) {
      unset($query['license_id']);
    }

    $form_state->setRedirect($this->request->attributes->get('_route'), $query);
  }

  /**
   * Reset the form.
   */
  public function resetForm(array $form, FormStateInterface $form_state) {
    $form_state->setRedirect($this->request->attributes->get('_route'));
  }

}
