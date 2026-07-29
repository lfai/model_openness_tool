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
    $this->id = (int) $component['id'];
    $this->name = (string) $component['name'];
    $this->description = (string) ($component['description'] ?? '');
    $this->tooltip = (string) ($component['tooltip'] ?? '');
    $this->contentType = (string) $component['content_type'];
    $this->class = (int) $component['class'];
    $this->weight = (int) ($component['weight'] ?? 0);
    $this->required = (bool) ($component['required'] ?? false);
  }

  /**
   * Get licenses for component type.
   */
  public function getLicenses(): array {
    $license_manager = \Drupal::service('license_handler');
    return $license_manager->getLicensesByType($this->contentType);
  }

}

