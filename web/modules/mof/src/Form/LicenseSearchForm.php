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

    $form['search']['content_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Content Type'),
      '#options' => [
        '' => $this->t('Any'),
        'code' => $this->t('Code'),
        'document' => $this->t('Documentation'),
        'data' => $this->t('Data'),
      ],
      '#default_value' => $req->get('content_type') ?? '',
    ];

    $form['search']['osi_approved'] = [
      '#type' => 'select',
      '#title' => $this->t('OSI Approved'),
      '#options' => [
        '' => $this->t('Any'),
        '1' => $this->t('Yes'),
        '0' => $this->t('No'),
      ],
      '#default_value' => $req->get('osi_approved') ?? '',
    ];

    $form['search']['fsf_libre'] = [
      '#type' => 'select',
      '#title' => $this->t('FSF Libre'),
      '#options' => [
        '' => $this->t('Any'),
        '1' => $this->t('Yes'),
        '0' => $this->t('No'),
      ],
      '#default_value' => $req->get('fsf_libre') ?? '',
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

    $content_type = $form_state->getValue('content_type') ?? FALSE;
    if ($content_type) {
      $query['content_type'] = $content_type;
    }
    else if (isset($query['content_type']) && !$content_type) {
      unset($query['content_type']);
    }

    $osi_approved = $form_state->getValue('osi_approved') ?? FALSE;
    if ($osi_approved !== '') {
      $query['osi_approved'] = $osi_approved;
    }
    else if (isset($query['osi_approved']) && $osi_approved === '') {
      unset($query['osi_approved']);
    }

    $fsf_libre = $form_state->getValue('fsf_libre') ?? FALSE;
    if ($fsf_libre !== '') {
      $query['fsf_libre'] = $fsf_libre;
    }
    else if (isset($query['fsf_libre']) && $fsf_libre === '') {
      unset($query['fsf_libre']);
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
