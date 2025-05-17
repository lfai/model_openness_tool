<?php declare(strict_types=1);

namespace Drupal\mof;

final class Component implements ComponentInterface {

  public readonly int $id;

  public readonly string $name;

  public readonly string $description;

  public readonly string $tooltip;

  public readonly string $contentType;

  public readonly int $class;

  public readonly int $weight;

  public readonly bool $required;

  /**
   * Construct a component instance.
   */
  public function __construct(array $component) {
    foreach ($component as $k => $v) {
      $k = lcfirst(implode('', array_map('ucfirst', explode('_', $k))));
      if (property_exists($this, $k)) $this->$k = $v;
    }
  }

  /**
   * Get licenses for component type.
   */
  public function getLicenses(): array {
    $license_manager = \Drupal::service('license_handler');
    return $license_manager->getLicensesByType($this->contentType);
  }

}

