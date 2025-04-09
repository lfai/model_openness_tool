<?php

declare(strict_types=1);

namespace Drupal\mof;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Licenses provided by spdx.org/licenses/
 */
final class LicenseHandler implements LicenseHandlerInterface {

  private readonly array $licenses;

  /**
   * Constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $licenses = [];

    foreach ($entity_type_manager
      ->getStorage('license')
      ->loadMultiple() as $license) {
      $licenses[] = $license->toArray();
    }

    $this->licenses = $licenses;
  }

  /**
   * Return a list of licenses for the specified content type.
   *
   * @param string $type
   *   Accepted values: 'code' 'data' or 'document'
   *
   * @return array
   *
   */
  public function getLicensesByType(string $type): array {
    $licenses = array_filter($this->licenses, fn($a) => $a['ContentType'] === $type);
    return [...$licenses, ...$this->getExtraOptions()];
  }

  /**
   * Return a list of OSI-approved licenses.
   */
  public function getOsiApproved(): array {
    return array_filter($this->licenses, fn($a) => $a['isOsiApproved'] === true);
  }

  /**
   * Determines of a specific license is OSI-approved.
   */
  public function isOsiApproved(string $license): bool {
    return in_array($license, array_column($this->getOsiApproved(), 'licenseId'));
  }

  /**
   * Extra licenses are listed for selection; however,
   * they are considered invalid, meaning models will fail evaluation
   * if selected for a component.
   */
  public function getExtraOptions(): array {
    return [[
      'name' => 'Other license',
      'licenseId' => 'Other license',
    ], [
      'name' => 'License not specified',
      'licenseId' => 'License not specified',
    ], [
      'name' => 'Component not included',
      'licenseId' => 'Component not included',
    ], [
      'name' => 'Pending evaluation',
      'licenseId' => 'Pending evaluation',
    ]];
  }

  /**
   * Check if a type-specific license exists for license id.
   */
  public function exists(string $id, string|array $type): bool {
    $type = is_array($type) ? $type : [$type];

    foreach ($type as $t) {
      $licenses = array_filter($this->licenses,
        fn($a) => $a['ContentType'] === $t && $a['licenseId'] === $id);
    }

    return !empty($licenses);
  }

  /**
   * Check if a license is considered open source.
   * A license is open source if ContentType=code
   */
  public function isOpenSource(string $license): bool {
    $key = array_search($license, array_column($this->licenses, 'licenseId'));
    return $key !== FALSE && $this->licenses[$key]['ContentType'] === 'code';
  }

}

