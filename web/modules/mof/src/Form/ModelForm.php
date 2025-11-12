<?php declare(strict_types=1);

namespace Drupal\mof\Form;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\component\Datetime\TimeInterface;
use Drupal\mof\LicenseHandlerInterface;
use Drupal\mof\ModelEvaluatorInterface;
use Drupal\mof\ComponentManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * @file
 * Form controller for evaluate and submit forms.
 */
abstract class ModelForm extends ContentEntityForm {

  /** @var \Drupal\mof\LicenseHandler */
  protected readonly LicenseHandlerInterface $licenseHandler;

  /** @var \Drupal\mof\ModelEvaluator */
  protected readonly ModelEvaluatorInterface $modelEvaluator;

  /** @var \Drupal\mof\ComponentManager */
  protected readonly ComponentManagerInterface $componentManager;

  /** @var \Symfony\Component\HttpFoundation\Session\Session. */
  protected readonly Session $session;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityRepositoryInterface $entity_repository,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    TimeInterface $time,
    LicenseHandlerInterface $license_handler,
    ModelEvaluatorInterface $model_evaluator,
    ComponentManagerInterface $component_manager,
    Session $session
  ) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->licenseHandler = $license_handler;
    $this->modelEvaluator = $model_evaluator;
    $this->componentManager = $component_manager;
    $this->session = $session;
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
      $container->get('component.manager'),
      $container->get('session')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    $form['#cache']['contexts'][] = 'session';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form += parent::form($form, $form_state);
    $form['#tree'] = TRUE;

    // Hide status field.
    $form['status']['#access'] = false;

    // Hide revision field.
    // @todo Strip revision support from model entity; it's not needed.
    $form['revision_log']['#access'] = false;

    $model_details = [
      'label',
      'organization',
      'description',
      'version',
      'type',
      'architecture',
      'treatment',
      'origin',
      'repository',
      'huggingface',
    ];

    $form['details'] = [
      '#type' => 'details',
      '#title' => $this->t('Model details'),
      '#open' => false,
      '#weight' => -90,
      '#prefix' => '<div id="details-wrap">',
      '#suffix' => '</div>',
    ];

    // Move entity defined fields into a details element.
    foreach ($model_details as $field) {
      if (isset($form[$field])) {
        $form['details'][$field] = $form[$field];
        unset($form[$field]);
      }
    }

    // The description field is not currently saved in the YAML model file so we may as well hide it away
    // until we decide whether to just get rid of it altogether or add it to the YAML file.
    $form['details']['description']['#access'] = FALSE;
    // Hide more fields to keep input to the what's really useful and make the form simpler
    $form['details']['version']['#access'] = FALSE;
    $form['details']['type']['#access'] = FALSE;
    $form['details']['architecture']['#access'] = FALSE;
    $form['details']['treatment']['#access'] = FALSE;
    $form['details']['origin']['#access'] = FALSE;

    // Model licenses to populate fields with a default value.
    $model_licenses = $this->entity->getLicenses() ?? [];
    $model_components = $this->entity->getComponents() ?? [];

    $form['license'] = [
      '#type' => 'details',
      '#weight' => -50,
      '#title' => $this->t('Global licenses'),
      '#open' => false,
    ];

    $form['license']['distribution']['included']  = [
      '#type' => 'checkbox',
      '#title' => $this->t('This model has a global/distribution-wide license'),
      '#default_value' => isset($model_licenses['global']['distribution']),
    ];

    $form['license']['distribution']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Distribution-wide license'),
      '#description' => $this->t('Enter the name of the global/distribution-wide license (e.g. Apache-2.0).'),
      '#weight' => 1,
      '#attributes' => ['list' => 'license-datalist-all'],
      '#placeholder' => $this->t('Begin typing to find a license'),
      '#default_value' => $model_licenses['global']['distribution']['name'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name="license[distribution][included]"]' => ['checked' => true],
        ],
      ],
    ];

    $form['license']['distribution']['path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('License URL'),
      '#description' => $this->t('Enter the URL for the license (e.g. https://example.com/license).'),
      '#default_value' => $model_licenses['global']['distribution']['path'] ?? '',
      '#weight' => 2,
      '#states' => [
        'visible' => [
          ':input[name="license[distribution][included]"]' => ['checked' => true],
        ],
      ],
    ];

    $form['license']['code']['included'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('This model has a global license for code components'),
      '#default_value' => isset($model_licenses['global']['code']),
    ];

    $form['license']['code']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Code component license'),
      '#description' => $this->t('Enter the name of the code component license (e.g. Apache-2.0).'),
      '#weight' => 1,
      '#attributes' => ['list' => 'license-datalist-code'],
      '#placeholder' => $this->t('Begin typing to find a license'),
      '#default_value' => $model_licenses['global']['code']['name'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name="license[code][included]"]' => ['checked' => true],
        ],
      ],
    ];

    $form['license']['code']['path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('License URL'),
      '#description' => $this->t('Enter the URL for the license (e.g. https://example.com/license).'),
      '#default_value' => $model_licenses['global']['code']['path'] ?? '',
      '#weight' => 2,
      '#states' => [
        'visible' => [
          ':input[name="license[code][included]"]' => ['checked' => true],
        ],
      ],
    ];

    $form['license']['data']['included'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('This model has a global license for data components'),
      '#default_value' => isset($model_licenses['global']['data']),
    ];

    $form['license']['data']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Data component license'),
      '#description' => $this->t('Enter the name of the data component license (e.g. Apache-2.0).'),
      '#default_value' => $model_licenses['global']['data']['name'] ?? '',
      '#attributes' => ['list' => 'license-datalist-data'],
      '#placeholder' => $this->t('Begin typing to find a license'),
      '#weight' => 1,
      '#states' => [
        'visible' => [
          ':input[name="license[data][included]"]' => ['checked' => true],
        ],
      ],
    ];

    $form['license']['data']['path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('License URL'),
      '#description' => $this->t('Enter the URL for the license (e.g. https://example.com/license).'),
      '#default_value' => $model_licenses['global']['data']['path'] ?? '',
      '#weight' => 2,
      '#states' => [
        'visible' => [
          ':input[name="license[data][included]"]' => ['checked' => true],
        ],
      ],
    ];

    $form['license']['document']['included'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('This model has a global license for document components'),
      '#default_value' => isset($model_licenses['global']['document']),
    ];

    $form['license']['document']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Document component license'),
      '#description' => $this->t('Enter the name of the document component license (e.g. Apache-2.0).'),
      '#default_value' => $model_licenses['global']['document']['name'] ?? '',
      '#weight' => 1,
      '#attributes' => ['list' => 'license-datalist-document'],
      '#placeholder' => $this->t('Begin typing to find a license'),
      '#states' => [
        'visible' => [
          ':input[name="license[document][included]"]' => ['checked' => true],
        ],
      ],
    ];

    $form['license']['document']['path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('License URL'),
      '#description' => $this->t('Enter the URL for the license (e.g. https://example.com/license).'),
      '#default_value' => $model_licenses['global']['document']['path'] ?? '',
      '#weight' => 2,
      '#states' => [
        'visible' => [
          ':input[name="license[document][included]"]' => ['checked' => true],
        ],
      ],
    ];

    $form['components'] = [
      '#type' => 'container',
      '#weight' => -50,
    ];

    $form['components']['code'] = [
      '#type' => 'details',
      '#title' => $this->t('Code components'),
      '#description' => $this->t('Check each component included in the model distribution.'),
      '#weight' => 1,
    ];

    $form['components']['data'] = [
      '#type' => 'details',
      '#title' => $this->t('Data components'),
      '#description' => $this->t('Check each component included in the model distribution.'),
      '#weight' => 2,
    ];

    $form['components']['document'] = [
      '#type' => 'details',
      '#title' => $this->t('Document components'),
      '#description' => $this->t('Check each component included in the model distribution.'),
      '#weight' => 3,
    ];

    // We need a datalist of all available licenses for global/ distribution-wide.
    $form['datalist']['all'] = $this->buildDataList();

    // Build a datalist for each license type.
    foreach (['code', 'data', 'document'] as $type) {
      $form['datalist'][$type] = $this->buildDataList($type);
    }

    // Process each component.
    foreach ($this->componentManager->getComponents() as $component) {
      $cid = $component->id;
      $type = $component->contentType;

      $form['components'][$type][$cid] = [
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
          'included' => [
            '#type' => 'checkbox',
            '#title' => $component->name,
            '#default_value' => in_array($cid, $model_components) ? $cid : false,
            '#return_value' => $cid,
            '#parents' => ['component', $cid],
          ],
          'component_path' => [
            '#type' => 'textfield',
            '#parents' => ['component_data', $cid, 'component_path'],
            '#description' => $this->t('The URL for the component (e.g. https://example.com/component).'),
            '#default_value' => $model_licenses['components'][$cid]['component_path'] ?? '',
            '#placeholder' => $this->t('Enter the component URL'),
            '#weight' => 100,
            '#attributes' => [
              'autocomplete' => 'off',
            ],
            '#states' => [
              'visible' => [
                [
                  ":input[name=\"component[{$cid}]\"]" => ['checked' => true],
                ],
              ],
            ],
          ],
          'global' => [
            '#type' => 'checkbox',
            '#parents' => ['global', $cid],
            '#title' => $this->t('Check if this component uses a component-specific license'),
            '#default_value' => in_array($cid, $model_components) && isset($model_licenses['components'][$cid]['license']),
            '#states' => [
              'visible' => [
                [
                  ":input[name=\"component[{$cid}]\"]" => ['checked' => true],
                  ":input[name=\"license[{$type}][included]\"]" => ['checked' => true],
                ],
                'or',
                [
                  ":input[name=\"component[{$cid}]\"]" => ['checked' => true],
                  ":input[name=\"license[distribution][included]\"]" => ['checked' => true],
                ],
              ],
            ],
          ],
          'license' => [
            '#type' => 'textfield',
            '#parents' => ['component_data', $cid, 'license'],
            '#description' => $component->tooltip,
            '#default_value' => $model_licenses['components'][$cid]['license'] ?? '',
            '#placeholder' => $this->t('Begin typing to find a license; leave blank if unlicensed'),
            '#attributes' => [
              'data-component-id' => $cid,
              'list' => 'license-datalist-' . $type,
              'class' => ['license-input'],
              'autocomplete' => 'off',
            ],
            '#states' => [
              'visible' => [
                [
                  ":input[name=\"component[{$cid}]\"]" => ['checked' => true],
                  ":input[name=\"global[{$cid}]\"]" => ['checked' => true],
                ],
                'or',
                [
                  ":input[name=\"component[{$cid}]\"]" => ['checked' => true],
                  ":input[name=\"license[distribution][included]\"]" => ['checked' => false],
                  ":input[name=\"license[{$type}][included]\"]" => ['checked' => false],
                ],
              ],
            ],
          ],
          'license_path' => [
            '#type' => 'textfield',
            '#parents' => ['component_data', $cid, 'license_path'],
            '#description' => $this->t('The URL for the license (e.g. https://example.com/license).'),
            '#default_value' => $model_licenses['components'][$cid]['license_path'] ?? '',
            '#placeholder' => $this->t('Enter the license URL'),
            '#attributes' => [
              'autocomplete' => 'off',
            ],
            '#states' => [
              'visible' => [
                [
                  ":input[name=\"component[{$cid}]\"]" => ['checked' => true],
                  ":input[name=\"global[{$cid}]\"]" => ['checked' => true],
                ],
                'or',
                [
                  ":input[name=\"component[{$cid}]\"]" => ['checked' => true],
                  ":input[name=\"license[distribution][included]\"]" => ['checked' => false],
                  ":input[name=\"license[{$type}][included]\"]" => ['checked' => false],
                ],
              ],
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
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Gather global licenses attached to the model.
    $global_license = array_filter($form_state->getValue('license'), fn($a) => $a['included'] !== 0);

    // Gather included components and associated data.
    $included_components = array_filter($form_state->getValue('component'));
    $component_data = $form_state->getValue('component_data');

    // Gather component-specific licenses.
    $component_specific = array_filter($form_state->getValue('global'));

    // Build license data.
    $license_data = [
      'global' => $global_license,
      'components' => [],
    ];

    // Determine if component has a component-specific license or if it's inherited.
    foreach ($included_components as $cid) {
      // Component-specific.
      if (empty($global_license) || isset($component_specific[$cid])) {
        $license_data['components'][$cid] = $component_data[$cid];
      }
      // Inherits global license.
      else {
        // Check if component has a path set.
        if ($component_data[$cid]['component_path'] !== '') {
          $license_data['components'][$cid]['component_path'] = $component_data[$cid]['component_path'];
        }
      }
    }

    $form_state->setValue('components', $included_components);
    $form_state->setValue('license_data', ['licenses' => $license_data]);
    parent::submitForm($form, $form_state);
  }

  /**
   * Build an HTML5 datalist of licenses.
   *
   * @param string|null $type
   *   The content type ("code", "document" or "data").
   *   Pass null to return all licenses, regardless of type.
   *
   * @return array Drupal render array describing the HTML datalist.
   */
  final protected function buildDataList(?string $type = null): array {
    $licenses = [];

    $datalist = [
      '#type' => 'html_tag',
      '#tag' => 'datalist',
      '#attributes' => ['id' => 'license-datalist-' . ($type ?? 'all')],
      'licenses' => [],
    ];

    // Fetch licenses based on type or get all licenses.
    $licenses = $type === null
      ? $this->licenseHandler->licenses
      : $this->licenseHandler->getLicensesByType($type);

    // Add open source licenses based on type.
    $open_licenses = match ($type) {
      'code' => $this->licenseHandler->getOsiApproved(),
      'data' => array_filter(
        $this->licenseHandler->licenses,
        fn($license) => in_array((string)$license, $this->licenseHandler::OPEN_DATA_LICENSES)
      ),
      default => [],
    };

    // Merge and remove duplicates.
    $licenses = array_unique([...$licenses, ...$open_licenses], SORT_STRING);

    // Sort licenses by name.
    uasort($licenses, fn($a, $b) => strcasecmp($a->getName(), $b->getName()));

    foreach ($licenses as $license) {
      $datalist['licenses'][] = [
        '#type' => 'html_tag',
        '#tag' => 'option',
        '#value' => $license->getName(),
        '#attributes' => ['value' => $license->getLicenseId()],
      ];
    }

    return $datalist;
  }

}

