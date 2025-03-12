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
  private $model;

  /**
   * Construct a ModelEvaluator instance.
   */
  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
    private ComponentManagerInterface $componentManager,
    private LicenseHandlerInterface $licenseHandler
  ) {}

  /**
   * Set the model to evaluate.
   */
  public function setModel(ModelInterface $model): self {
    $this->model = $model;
    return $this;
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
   * Build an evaluation report for the model.
   *
   * @return array
   *   An array containing 'missing' and 'invalid' licenses for component ids.
   */
  public function evaluate(): array {
    if (empty($this->model)) {
      // @todo Implement ModelEvaluatorException.
      throw new \Exception($this->t('Cannot evaluate. No model set.'));
    }

    $evals = [];

    // Return an empty evaluation if model is pending.
    if ($this->model->isPending()) {
      return $evals;
    }

    $required = $this->getRequiredComponents();

    for ($i = 3; $i >= 1; $i--) {
      $components = [];

      for ($j = $i; $j <= 3; $j++) {
        $components = array_merge($components, $required[$j]);
      }

      $evals[$i] = [
        'missing' => $this->getMissing($components),
        'invalid' => $this->getInvalid($components),
        'included' => $this->getIncluded($components),
        'conditional' => $i === 3 ? $this->hasConditionalPass() : FALSE,
      ];
    }

    return $evals;
  }

  /**
   * Get the model's final classification.
   */
  public function getClassification(bool $label = TRUE): TranslatableMarkup|int {
    $evals = $this->evaluate();
    $class = 0;

    if (empty($evals)) {
      $class = -1;
    }
    else if (empty($evals[1]['missing']) && empty($evals[1]['invalid'])) {
      $class = 1;
    }
    else if (empty($evals[2]['missing']) && empty($evals[2]['invalid'])) {
      $class = 2;
    }
    else if ((empty($evals[3]['missing']) && empty($evals[3]['invalid'])) || $evals[3]['conditional']) {
      $class = 3;
    }

    return $label ? $this->getClassLabel($class) : $class;
  }

  /**
   * Generate badges for each classification.
   *
   * @param bool $mini
   *   When true, only include earned badges (i.e., conditional or qualified)
   *   with the highest qualified and lowest in progress.
   *   This is used for the ModelList page.
   *
   * @todo Move badging related functions to its own class.
   */
  public function generateBadge(bool $mini = FALSE): array {
    $build = [];

    // Do not generate badges if model is pending evaluation.
    if ($this->model->isPending()) {
      return $build;
    }

    $evals = $this->evaluate();
    $qualified = $in_progress = FALSE;

    for ($i = 3, $j = 3; $i >= 1; $i--, $j--) {
      $progress = $this->getProgress($i);

      // under MOF 1.1 Conditional is a Pass
      if ($progress === 100.00 || $evals[$i]['conditional'] === TRUE) {
        $status = $this->t('Qualified');
        $text_color = '#fff';
        $background_color = '#4c1';
        if ($mini && $qualified) {
          // replace previous one to only keep the highest one
          $j++;
        }
        $qualified = TRUE;
      }
      else if ($progress == 0) {
        if ($mini) {
          // do not include classes that are not met
          continue;
        }
        $status = $this->t('Not met');
        $text_color = '#fff';
        $background_color = '#9ba0a2';
      }
      else {
        if ($mini && $in_progress) {
          // only include the first in progress
          continue;
        }
        $status = $this->t('In progress (@progress%)', ['@progress' => round($progress)]);
        $text_color = '#fff';
        $background_color = '#76b1c9';
        $in_progress = TRUE;
      }

      $build[$j] = [
        '#theme' => 'badge',
        '#status' => $status,
        '#label' => $this->getClassLabel($i),
        '#text_color' => $text_color,
        '#background_color' => $background_color,
        '#weight' => $i,
      ];
    }

    return $build;
  }

  /**
   * Get the model's total progress.
   */
  public function getTotalProgress(): float {
    if ($this->model->isPending()) {
      return -1;
    }

    $total = 0;
    for ($i = 1; $i <= 3; $i++) {
      $total += $this->getProgress($i);
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
   * @return array
   *   An array of translatable strings.
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

    foreach (self::CLASS_3_CIDS as $cid) {
      if (isset($component_messages[$cid]) && $this->isOpenSourceLicense($cid)) {
        $messages[] = $component_messages[$cid];
      }
    }

    return $messages;
  }

  /**
   * Determine if a model has a conditional pass.
   * @return bool
   */
  private function hasConditionalPass(): bool {
    $pass = array_filter(self::CLASS_3_CIDS, fn($cid) => $this->isOpenSourceLicense($cid));
    return !empty($pass);
  }

  /**
   * Determine if a component is using an open source license.
   *
   * @param int $cid Component ID.
   * @return bool
   */
  private function isOpenSourceLicense(int $cid): bool {
    $licenses = $this->model->getLicenses();
    return isset($licenses[$cid]) && $this->licenseHandler->isOpenSource($licenses[$cid]['license']);
  }

  /**
   * Returns a percentage indicating the models progress for specified class.
   *
   * @param int $class
   *   Class 1, 2, or 3
   *
   * @return float
   *   Progress percentage
   */
  public function getProgress(int $class): float {
    $required = $this->getRequiredComponents();
    $evaluate = $this->evaluate();

    if (empty($evaluate)) {
      return 0;
    }

    if ($class === 3 && $evaluate[3]['conditional'] === true) {
      return 100;
    }

    $total = 0;
    for ($i = 3; $i >= $class; $i--) {
      $total += sizeof($required[$i]);
    }

    // discount components with an invalid license or missing entirely
    $intersect = array_intersect($evaluate[$class]['invalid'], $evaluate[$class]['included']);
    $invalid = sizeof($evaluate[$class]['missing']) + sizeof($intersect);

    return ($total - $invalid) / $total * 100;
  }

  /**
   * Return an array with required components keyed by class number.
   */
  private function getRequiredComponents(): array {
    return [
      3 => $this->componentManager->getRequired(3),
      2 => $this->componentManager->getRequired(2),
      1 => $this->componentManager->getRequired(1),
    ];
  }

  /**
   * Return a flattened array of excluded license IDs.
   */
  private function getExtraLicenses(): array {
    $extra = $this->licenseHandler->getExtraOptions();
    return array_column($extra, 'licenseId');
  }

  /**
   * Filter a list of completed components.
   * Remove any invalid components from the array.
   */
  private function filterCompleted(): array {
    $excluded = $this->getExtraLicenses();
    $completed = $this->model->getCompletedComponents();
    $licenses = $this->model->getLicenses();

    foreach ($this->model->getCompletedComponents() as $key => $cid) {
      if (array_key_exists($cid, $licenses) && in_array($licenses[$cid]['license'], $excluded)) {
        unset($completed[$key]);
      }
    }

    return array_values($completed);
  }

  /**
   * Check which components are included and valid.
   *
   * @param array $required Required components.
   *
   * @return array Included components with a valid license.
   */
  private function getIncluded(array $required): array {
    return array_intersect($required, $this->filterCompleted());
  }

  /**
   * Check which required components are missing/ no license selected.
   *
   * @param array $required Required components.
   *
   * @return array Incomplete/ missing components.
   */
  private function getMissing(array $required): array {
    return array_diff($required, $this->filterCompleted());
  }

  /**
   * Check if required components have a valid license.
   *
   * @param array $required Required components.
   *
   * @return array Invalid components.
   */
  private function getInvalid(array $required): array {
    $invalid = [];
    $licenses = $this->model->getLicenses();
    $excluded = $this->getExtraLicenses();

    // Remove `Component not included`
    // It does not mean the component is invalid.
    unset($excluded[2]);

    foreach ($this->model->getCompletedComponents() as $cid) {
      if (in_array($cid, $required)) {
        $cid = (int)$cid;

        // Special case for class 3 components.
        // Conditional passes are valid.
        if (in_array($cid, self::CLASS_3_CIDS) && $this->hasConditionalPass()) {
          continue;
        }

        // License is invalid if it's in $excluded.
        if (in_array($licenses[$cid]['license'], $excluded)) {
          $invalid[] = $cid;
        }

        // License is invalid if it doesn't belong to a component specific license.
        $component_licenses = $this->componentManager->getComponent($cid)->getLicenses();
        if (!in_array($licenses[$cid]['license'], array_column($component_licenses, 'licenseId'))) {
          $invalid[] = $cid;
        }
      }
    }

    return $invalid;
  }

}

