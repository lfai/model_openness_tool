<?php

declare(strict_types=1);

namespace Drupal\mof\Form;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\component\Datetime\TimeInterface;
use Drupal\mof\LicenseHandlerInterface;
use Drupal\mof\ModelEvaluatorInterface;
use Drupal\mof\ComponentManagerInterface;
use Drupal\mof\GitHubService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the model entity.
 */
abstract class ModelForm extends ContentEntityForm {

  /** @var \Drupal\mof\LicenseHandler */
  protected LicenseHandlerInterface $licenseHandler;

  /** @var \Drupal\mof\ModelEvaluator */
  protected ModelEvaluatorInterface $modelEvaluator;

  /** @var \Drupak\mof\GitHubService */
  protected GitHubService $github;

  /** @var \Drupal\mof\ComponentManager */
  protected ComponentManagerInterface $componentManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityRepositoryInterface $entity_repository,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    TimeInterface $time,
    LicenseHandlerInterface $license_handler,
    ModelEvaluatorInterface $model_evaluator,
    GitHubService $github,
    ComponentManagerInterface $component_manager
  ) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->licenseHandler = $license_handler;
    $this->modelEvaluator = $model_evaluator;
    $this->github = $github;
    $this->componentManager = $component_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('license_handler'),
      $container->get('model_evaluator'),
      $container->get('github'),
      $container->get('component.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form += parent::form($form, $form_state);

    $form['#tree'] = TRUE;
    $form['#attached']['library'][] = 'mof/model-evaluate';

    $form['code'] = [
      '#type' => 'details',
      '#title' => $this->t('Code components'),
      '#weight' => -9,
    ];

    $form['data'] = [
      '#type' => 'details',
      '#title' => $this->t('Data components'),
      '#weight' => -9,
    ];

    $form['document'] = [
      '#type' => 'details',
      '#title' => $this->t('Document components'),
      '#weight' => -9,
    ];

    $model_licenses = $this->entity->getLicenses();

    // Build a datalist for each license type.
    foreach (['code', 'data', 'document'] as $type) {
      $form['license'][$type] = $this->buildDataList($type);
    }

    // Process each component.
    foreach ($this->componentManager->getComponents() as $component) {
      $cid = $component->id;

      // If a component has extra licenses defined
      // or belongs to more than one content_type
      // we need to build a separate datalist for the component.
      if (($extra = $component->extraLicenses) !== NULL || is_array($component->contentType)) {
        $form['license'][$cid] = $this->buildDataList($cid);
      }

      // Use the first content type for form placement.
      $group = is_array($component->contentType) ? $component->contentType[0] : $component->contentType;

      // Set default license value if one is set or if community preferred is selected.
      if (isset($model_licenses[$cid]['license'])) {
        $default_license = $model_licenses[$cid]['license'];
      }
      else if ($this->getRequest()->query->get('community') !== NULL) {
        $this->messenger()->addMessage($this->t('Component licenses set to community preferred'));
        if ($group === 'code') {
          $default_license = 'MIT';
        }
        else if ($group === 'data') {
          $default_license = 'CDLA-Permissive-2.0';
        }
        else if ($group === 'document') {
          $default_license = 'CC-BY-4.0';
        }
      }
      else {
        $default_license = '';
      }

      $form[$group]['components'][$cid] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['component-wrapper'],
        ],
        'label_wrap' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['component-license-wrapper'],
            'id' => "component-{$cid}",
          ],
          'label' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#value' => $component->name,
          ],
          'license' => [
            '#type' => 'textfield',
            '#parents' => ['components', $cid, 'license'],
            '#description' => $component->tooltip,
            '#default_value' => $default_license,
            '#placeholder' => $this->t('Begin typing to find a license'),
            '#attributes' => [
              'data-component-id' => $cid,
              'list' => isset($form['license'][$cid])
                ? "license-datalist-{$cid}"
                : "license-datalist-{$group}",
              'class' => ['license-input'],
              'autocomplete' => 'off',
            ],
          ],
        ],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    // Ensure the entered or selected license is valid for each component.
    foreach ($this
      ->filterComponents($form_state
      ->getValue('components')) as $cid => $item) {

      $license = array_filter($this
        ->componentManager
        ->getComponent($cid)
        ->getLicenses(), fn($a) => $a['licenseId'] === $item['license']);

      if (empty($license)) {
        $form_state
          ->setErrorByName("components][{$cid}][license", $this
          ->t('Invalid license selected.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $licenses = $this->filterComponents($form_state->getValue('components'));
    $form_state->setValue('components', array_keys($licenses));
    $form_state->setValue('license_data', ['licenses' => $licenses]);
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);

    try {
      $status = $this
        ->modelEvaluator
        ->setModel($this->entity)
        ->evaluate();

      foreach ($status as $class => $missing_components) {
        if (empty($missing_components)) {
          $this
            ->messenger()
            ->addStatus($this->t('Model qualified for class @class', ['@class' => $class]));
        }
      }
    }
    catch (\Exception $e) {
      $this->messenger()->addError($e->getMessage());
    }

    $logger_args = [
      '%label' => $this->entity->label(),
      'link' => $this->entity->toLink($this->t('View'))->toString(),
    ];

    switch ($result) {
      case SAVED_NEW:
        $this->logger('mof')->notice('New model (%label) has been created.', $logger_args);
        break;

      case SAVED_UPDATED:
        $this->logger('mof')->notice('The model (%label) has been updated.', $logger_args);
        break;

      default:
        throw new \LogicException('Could not save the model.');
    }

    $form_state->setRedirectUrl($this->entity->toUrl());
    return $result;
  }

  /**
   * Build an HTML5 datalist for the specific content type or component.
   */
  final protected function buildDataList(int|string $type): array {
    $licenses = [];

    $datalist = [
      '#type' => 'html_tag',
      '#tag' => 'datalist',
      '#attributes' => ['id' => "license-datalist-{$type}"],
      'licenses' => [],
    ];

    // Collect licenses for the individual component.
    if (is_int($type)) {
      $licenses = $this->componentManager->getComponent($type)->getLicenses();
    }
    // Collect all licenses by content type.
    // 'code' 'document' or 'data'
    else {
      $licenses = $this->licenseHandler->getLicensesByType($type);
    }

    // Special case for 'code' components.
    // Include all OSI-approved licenses.
    if ($type === 'code' || (is_int($type) && $this->componentManager->getComponent($type)->contentType === 'code')) {
      $licenses = array_unique([...$licenses, ...$this->licenseHandler->getOsiApproved()], SORT_REGULAR);
    }

    foreach ($licenses as $license) {
      $datalist['licenses'][] = [
        '#type' => 'html_tag',
        '#tag' => 'option',
        '#value' => $license['name'],
        '#attributes' => ['value' => $license['licenseId']],
      ];
    }

    return $datalist;
  }

  /**
   * Filter a list of set licenses for each component.
   */
  private function filterComponents(array $components): array {
    return array_filter($components, fn($a) => $a['license'] !== '');
  }
}

