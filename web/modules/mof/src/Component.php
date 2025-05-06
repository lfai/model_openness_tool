<?php declare(strict_types=1);

namespace Drupal\mof;

final class Component implements ComponentInterface {

  public readonly int $id;

  public readonly string $name;

  public readonly string $description;

  public readonly string $tooltip;

  public readonly string|array $contentType;

  public readonly int $class;

  public readonly ?array $extraLicenses;

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

    if (!isset($this->extraLicenses)) {
      $this->extraLicenses = NULL;
    }
  }

  /**
   * Get licenses for component.
   */
  public function getLicenses(): array {
    $licenses = [];
    $license_manager = \Drupal::service('license_handler');

    if ($this->extraLicenses !== NULL) {
      foreach ($this->extraLicenses as $extra) {
        $licenses[] = ['licenseId' => $extra, 'name' => $extra];
      } 
    }

    if (is_array($this->contentType)) {
      foreach ($this->contentType as $type) {
        $licenses = [
          ...$licenses,
          ...$license_manager->getLicensesByType($type)
        ];
      }
    }
    else {
      $licenses = [
        ...$licenses,
        ...$license_manager->getLicensesByType($this->contentType)
      ];
    }

    return array_unique($licenses, SORT_STRING);
  }

}

