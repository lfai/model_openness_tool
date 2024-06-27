<?php declare(strict_types=1);

namespace Drupal\mof\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Provides a model name constraint.
 *
 * @Constraint(
 *   id = "ModelNameConstraint",
 *   label = @Translation("ModelNameConstraint", context = "Validation"),
 * )
 */
class ModelNameConstraint extends Constraint {

  public $errorMessage = 'Model name (%value) already exists.';

}
