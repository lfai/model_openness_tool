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

    $evaluation = $required_components = $optional_components = [];
    $model_components = $this->model->getComponents();
    $model_licenses = $this->model->getLicenses();
    $evaluation['not-type-appropriate'] = [];

    for ($class = 3; $class >= 1; $class--) {
      $evaluation[$class]['components'] = [
        'missing' => [],
        'included' => [],
        'invalid' => [],
        'unlicensed' => [],
        'optional' => [],
      ];

      $evaluation[$class]['licenses'] = [];

      // Combine required components from each previous class to the current class.
      $required_components = [
        ...$required_components,
        ...$this->componentManager->getRequired($class)
      ];
      // Combine optional components from each previous class to the current class.
      $optional_components = [
        ...$optional_components,
        ...$this->componentManager->getOptional($class)
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

        $type = $component->contentType;

        // Does the component have a type-appropriate license? and is it open?
        $type_appropriate = $this->isTypeAppropriate($license, $type);
        $is_open = $this->isOpenSourceLicense($license);

        if ($type_appropriate && $is_open) {
          $evaluation[$class]['components']['included'][] = $cid;
        }
        else if ($is_open) {
          $evaluation[$class]['components']['included'][] = $cid;
          if (!in_array($cid, $evaluation['not-type-appropriate'])) {
            $evaluation['not-type-appropriate'][] = $cid;
          }
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

      foreach($optional_components as $cid) {
        // Skip if component is not included.
        if (!in_array($cid, $model_components)) {
          continue;
        }
        // Get the component and resolve the license for the component.
        $component = $this->componentManager->getComponent($cid);
        $license = $this->resolveLicense($cid, $model_licenses) ?? 'unlicensed';

        $type = $component->contentType;

        // Does the component have a type-appropriate license? and is it open?
        $type_appropriate = $this->isTypeAppropriate($license, $type);
        $is_open = $this->isOpenSourceLicense($license);

        if ($type_appropriate && $is_open) {
          $evaluation[$class]['components']['optional'][] = $cid;
        }
        else if ($is_open) {
          $evaluation[$class]['components']['optional'][] = $cid;
          if (!in_array($cid, $evaluation['not-type-appropriate'])) {
            $evaluation['not-type-appropriate'][] = $cid;
          }
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

    // The technical report (cid 11) MAY be omitted if a research paper (cid 21) is provided
    $techreport = array_search(11, $evaluation[3]['components']['included']);
    if (!$techreport) {
      $status = false;
      if (array_search(21, $evaluation[1]['components']['included']) !== false) {
        $status = 'included';
      } elseif (array_search(21, $evaluation[1]['components']['invalid']) !== false) {
        $status = 'invalid';
      } elseif (array_search(21, $evaluation[1]['components']['unlicensed']) !== false) {
          $status = 'unlicensed';
      }
      if ($status !== false) { // the research paper is provided
        // remove tech report from missing if it's listed as such
        $techreport = array_search(11, $evaluation[3]['components']['missing']);
        if ($techreport !== false) {
          array_splice($evaluation[3]['components']['missing'], $techreport, 1);
        }
        $techreport = array_search(11, $evaluation[2]['components']['missing']);
        if ($techreport !== false) {
          array_splice($evaluation[2]['components']['missing'], $techreport, 1);
        }
        $techreport = array_search(11, $evaluation[1]['components']['missing']);
        if ($techreport !== false) {
          array_splice($evaluation[1]['components']['missing'], $techreport, 1);
        }
        // add research paper to classes 2 and 3
        $evaluation[3]['components'][$status][] = 21;
        $evaluation[3]['licenses'][21] = $evaluation[1]['licenses'][21];
        $evaluation[2]['components'][$status][] = 21;
        $evaluation[2]['licenses'][21] = $evaluation[1]['licenses'][21];
      }
    }

    return $evaluation;
  }

  /**
   * Check if a license is appropriate for the given component type.
   *
   * Get type-appropriate license arrays and checks if the provided license ID exists among them.
   *
   * @param string $license
   *   The license ID to check (e.g., 'MIT').
   *
   * @param string $type
   *   The component's content type (i.e., 'code', 'data', or 'document').
   *
   * @return bool
   *   TRUE if the license ID is type-specific, FALSE otherwise.
   */
  private function isTypeAppropriate(string $license, string $type): bool {
    return in_array($license, array_map('strval', $this->licenseHandler->getLicensesByType($type)));
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
    $type = $component->contentType;

    // Check component-type-specific first.
    if (!empty($licenses['global'][$type]['name'])) {
      return $licenses['global'][$type]['name'];
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
   * Class has a conditional pass if some of its components have an open source license but not a
   * type-appropriate one in which case we inform the user that a type-appropriate license should be used.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   An array of translatable string messages.
   */
  public function getConditionalMessage(): array {
    $messages = [
      $this->t('The following components have an open license but should have a type-appropriate open license:'),
    ];

    $evaluation = $this->evaluate();
    foreach ($evaluation['not-type-appropriate'] as $cid) {
      $component = $this->componentManager->getComponent($cid);
      $messages[] = $this->t($component->name . ' of type ' . $component->contentType);
    }

    return $messages;
  }

  /**
   * Determine if a license is an open source license.
   *
   * @param string $license
   *   The license name.
   *
   * @return bool
   *   TRUE if license is open-source.
   *   FALSE otherwise.
   */
  private function isOpenSourceLicense(string $license): bool {
    return $this->licenseHandler->isFsfApproved($license)
      || $this->licenseHandler->isOpenData($license)
      || $this->licenseHandler->isOsiApproved($license);
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

    $total = 0;
    $included = 0;
    for ($i = 3; $i >= $class; $i--) {
      $required = sizeof($this->componentManager->getRequired($i));
      $total += $required;
      $included = sizeof($evaluate[$i]['components']['included']);
      if ($included < $total) { // stop here if class isn't met
        break;
      }
    }
    // If the lower class isn't met set progress to 0
    if ($i > $class) { 
      return 0;
    }

    // The tech report (cid 11) MAY be omitted if a research paper (cid 21) is provided which
    // means that for Class 1 we have one fewer required component
    // (and not for classes 2 and 3 where either the tech report or the research paper is counted)
    if ($class == 1 && ! array_search(11, $evaluate[1]['components']['included'])
        && array_search(21, $evaluate[1]['components']['included'])) {
        $total--;
    }
    $progress = ($included / $total) * 100;

    // In case both the tech report and the research paper are provided we end up with more than
    // is required so limit reporting value to 100%
    if ($progress > 100) $progress = 100;

    return $progress;
  }

}

