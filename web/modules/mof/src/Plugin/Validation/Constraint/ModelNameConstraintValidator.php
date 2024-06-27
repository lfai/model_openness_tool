<?php declare(strict_types=1);

namespace Drupal\mof\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides validation for the ModelNameConstraint.
 */
final class ModelNameConstraintValidator extends ConstraintValidator
  implements ContainerInjectionInterface {

  /**
   * Construct
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * Checks if a model by the same name already exists.
   */
  public function validate($field, Constraint $constraint) {
    // Do nothing if nothing is set.
    if (!$field->value) return;

    $storage = $this->entityTypeManager->getStorage('model');
    $entity = $field->getEntity();

    if (!$entity->isNew()) {
      // Load the original entity and compare labels.
      // If the label has changed we need to run validation.
      $original = $storage->load($entity->id());
    }

    if ((isset($original) && $original->label() !== $field->value) || $entity->isNew()) {
      $value = trim($field->value);

      $query = $storage
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('label', $value)
        ->range(0, 1)
        ->execute();
   
      if (!empty($query)) {
        $this
          ->context
          ->buildViolation($constraint->errorMessage)
          ->setParameter('%value', $value)
          ->atPath('label')
          ->addViolation();
      } 
    }
  }

}

