<?php

declare(strict_types=1);

namespace Drupal\mof;

use Drupal\mof\ModelInterface;
use Drupal\mof\ComponentManagerInterface;
use Drupal\mof\LicenseHandlerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;

final class ModelEvaluator implements ModelEvaluatorInterface {

  use StringTranslationTrait;

  // If any of these component IDs have a
  // open source license then the model qualifies for class 3.
  const CLASS_3_CIDS = [10, 11, 12, 13, 14];

  /** @var \Drupal\mof\Entity\Model. */
  private ?ModelInterface $model = NULL;

  /**
   * Construct a ModelEvaluator instance.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ComponentManagerInterface $componentManager,
    private readonly LicenseHandlerInterface $licenseHandler
  ) {}

  /**
   * {@inheritdoc}
   */
  public function setModel(ModelInterface $model): self {
    $this->model = $model;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(): array {
    if (!$this->model) {
      throw new \Exception($this->t('Unable to evaluate: no model set.'));
    }

    $evaluation = $required_components = [];
    $model_components = $this->model->getComponents();
    $model_licenses = $this->model->getLicenses();

    for ($class = 3; $class >= 1; $class--) {
      $evaluation[$class]['components'] = [
        'missing' => [],
        'included' => [],
        'invalid' => [],
        'unlicensed' => [],
      ];

      $evaluation[$class]['licenses'] = [];

      // Combine required components from each previous class to the current class.
      $required_components = [
        ...$required_components,
        ...$this->componentManager->getRequired($class)
      ];

      foreach ($required_components as $cid) {
        // Skip if component is not included.
        if (!in_array($cid, $model_components)) {
          $evaluation[$class]['components']['missing'][] = $cid;
          continue;
        }

        // Get the component and resolve the license for the component.
        $component = $this->componentManager->getComponent($cid);
        $license = $this->resolveLicense($cid, $model_licenses) ?? 'unlicensed';

        // Cast contentType to array to ensure consistent iterations
        // ('string' becomes ['string'], an array stays as-is).
        $types = (array) $component->contentType;

        // Does the component have a type-specific license? and if it's open.
        $type_specific = $this->isTypeSpecific($license, $types);
        $is_open = $this->isOpenSourceLicense($component, $license);

        if ($type_specific && $is_open) {
          $evaluation[$class]['components']['included'][] = $cid;
        }
        else if ($is_open) {
          $evaluation[$class]['components']['included'][] = $cid;
          // Display warning.
        }
        else if ($license === 'unlicensed') {
          $evaluation[$class]['components']['unlicensed'][] = $cid;
          // Display warning.
        }
        else {
          $evaluation[$class]['components']['invalid'][] = $cid;
        }

        $evaluation[$class]['licenses'][$cid] = $license;
      }
    }

    // Check if class 3 has a conditional pass.
    $evaluation[3]['conditional'] = false;
    foreach ($evaluation[3]['licenses'] as $cid => $license) {
      if (in_array($cid, self::CLASS_3_CIDS)) {
        $component = $this->componentManager->getComponent($cid);
        $evaluation[3]['conditional'] = $this->isOpenSourceLicense($component, $license);
        if ($evaluation[3]['conditional']) break;
      }
    }

    return $evaluation;
  }

  /**
   * Check if a license is specific to any of the given component types.
   *
   * Flattens type-specific license arrays and checks if the provided license ID exists among them.
   *
   * @param string $license
   *   The license ID to check (e.g., 'MIT').
   *
   * @param array $types
   *   The component's content types (e.g., ['code', 'data']).
   *
   * @return bool
   *   TRUE if the license ID is type-specific, FALSE otherwise.
   */
  private function isTypeSpecific(string $license, array $types): bool {
    $licenses = array_merge(...array_map([$this->licenseHandler, 'getLicensesByType'], $types));
    return in_array($license, array_column($licenses, 'licenseId'), true);
  }

  /**
   * Determines the license for a given component.
   *
   * @param int $cid
   *   Component ID.
   *
   * @param array $licenses
   *   An array of licenses attached to the model.
   *
   * @return string
   *   License ID attached to the component or NULL if none is set.
   */
  private function resolveLicense(int $cid, array $licenses): ?string {
    $component_license = $licenses['components'][$cid] ?? [];

    // Check if there is a component-specific license attached.
    if (isset($component_license['license'])) {
      return $component_license['license'] ?: null;
    }

    // If no component-specific license,
    // check global (type-specific or distribution) licenses.
    $component = $this->componentManager->getComponent($cid);

    // Cast contentType to array to ensure consistent iterations
    // ('string' becomes ['string'], an array stays as-is).
    $types = (array) $component->contentType;

    // Check component-type-specific first.
    foreach ($types as $type) {
      if (!empty($licenses['global'][$type]['name'])) {
        return $licenses['global'][$type]['name'];
      }
    }

    // Fallback to distribution-wide global license.
    if (!empty($licenses['global']['distribution']['name'])) {
      return $licenses['global']['distribution']['name'];
    }

    // No license found at any level.
    return null;
  }

  /**
   * Return the specified class label.
   */
  public function getClassLabel(int $class): TranslatableMarkup {
    switch ($class) {
    case 0:
      return $this->t('Unclassified');

    case 1:
      return $this->t('Class I - Open Science Model');

    case 2:
      return $this->t('Class II - Open Tooling Model');

    case 3:
      return $this->t('Class III - Open Model');

    case -1:
      return $this->t('Pending evaluation');

    default:
      // @todo Implement ModelEvaluatorException.
      throw new \Exception($this->t('Specify class 1, 2 or 3'));
      return $this->t('Invalid class');
    }
  }

  /**
   * Determine the model's final classification.
   *
   * @param bool $label
   *   TRUE to return translatable markup text.
   *   FALSE to return class integer (1, 2, or 3).
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|int
   */
  public function getClassification(bool $label = TRUE): TranslatableMarkup|int {
    $completed_class = 0;

    for ($class = 3; $class > 0; $class--) {
      $progress = $this->getProgress($class);
      if ($progress === 100.0) $completed_class = $class;
    }

    return $label ? $this->getClassLabel($completed_class) : $completed_class;
  }

  /**
   * Get the model's total progress across all 3 classes.
   *
   * @return float
   *   Progress percentage.
   */
  public function getTotalProgress(): float {
    $total = 0;

    for ($class = 3; $class > 0; $class--) {
      $total += $this->getProgress($class);
    }

    return $total / 3;
  }

  /**
   * Class 3 has a conditional pass if these components have an open source license but we will
   * inform the user that a type-appropriate license should be used.
   *
   *  - `Model parameters (Final)` (10)
   *  - `Technical report` (11)
   *  - `Evaluation results` (12)
   *  - `Model card` (13)
   *  - `Data card` (14)
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   An array of translatable string messages.
   */
  public function getConditionalMessage(): array {
    $messages = [
      $this->t('This model has an open source license on the following components, it should be using a type-appropriate license:'),
    ];

    $component_messages = [
      10 => $this->t('Model parameters (Final) of type data.'),
      11 => $this->t('Technical report of type documentation.'),
      12 => $this->t('Evaluation results of type documentation.'),
      13 => $this->t('Model card of type documentation.'),
      14 => $this->t('Data card of type documentation.'),
    ];

    $evaluation = $this->evaluate();
    foreach ($evaluation[3]['licenses'] as $cid => $license) {
      if (in_array($cid, static::CLASS_3_CIDS)) {
        $component = $this->componentManager->getComponent($cid);
        if ($this->isOpenSourceLicense($component, $license)) {
          $messages[] = $component_messages[$cid];
        }
      }
    }

    return $messages;
  }

  /**
   * Determine if a component is using an open source license.
   *
   * @param \Drupal\mof\Component $component
   *   The MOF component we're checking.
   *
   * @param string $license
   *   The license name.
   *
   * @return bool
   *   TRUE if license is open-source.
   *   FALSE otherwise.
   */
  private function isOpenSourceLicense(Component $component, string $license): bool {
    $component_types = (array) $component->contentType;

    foreach ($component_types as $type) {
      if ($type === 'document' && $this->licenseHandler->isFsfApproved($license)) {
        return true;
      }

      if ($type === 'data' && $this->licenseHandler->isOpenData($license)) {
        return true;
      }

      if ($type === 'code' && $this->licenseHandler->isOsiApproved($license)) {
        return true;
      }
    }

    return false;
  }

  /**
   * Returns a percentage indicating the models progress for specified class.
   *
   * @param int $class
   *   Class 1, 2, or 3.
   *
   * @return float
   *   Progress percentage.
   */
  public function getProgress(int $class): float {
    $evaluate = $this->evaluate();

    if (empty($evaluate)) {
      return 0;
    }

    if ($class === 3 && $evaluate[3]['conditional'] === true) {
      return 100;
    }

    $total = 0;
    for ($i = 3; $i >= $class; $i--) {
      $required = $this->componentManager->getRequired($i);
      $total += sizeof($required);
    }

    return (sizeof($evaluate[$class]['components']['included']) / $total) * 100;
  }

}

