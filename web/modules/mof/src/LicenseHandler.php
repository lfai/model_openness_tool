<?php

declare(strict_types=1);

namespace Drupal\mof;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Licenses provided by spdx.org/licenses/
 */
final class LicenseHandler implements LicenseHandlerInterface {

  public readonly array $licenses;

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
    return array_filter($this->licenses, fn($a) => $a['ContentType'] === $type);
  }

  /**
   * Return a list of OSI-approved licenses.
   *
   * @return array List of open/ OSI-approved licenses.
   */
  public function getOsiApproved(): array {
    return array_filter($this->licenses, fn($a) => $a['isOsiApproved'] === true);
  }

  /**
   * Determines of a specific license is open/ OSI-approved.
   *
   * @param string $license
   *   The license ID to check if it is open.
   *
   * @return bool TRUE if open, FALSE otherwise.
   */
  public function isOsiApproved(string $license): bool {
    return in_array($license, array_column($this->getOsiApproved(), 'licenseId'));
  }

  /**
   * Check if a type-specific license exists for license id.
   *
   * @param string $id
   *   The license ID to check.
   * @param string|array $type
   *   A single type or an array of types.
   *   Types include: code, document, or data
   *
   * @return bool
   *   TRUE if license belongs to $type.
   *   FALSE otherwise.
   */
  public function exists(string $id, string|array $type): bool {
    $type = is_array($type) ? $type : [$type];

    foreach ($type as $t) {
      $licenses = array_filter($this->licenses,
        fn($a) => $a['ContentType'] === $t && $a['licenseId'] === $id);
    }

    return !empty($licenses);
  }

}

