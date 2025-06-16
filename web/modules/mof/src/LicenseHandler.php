<?php declare(strict_types=1);

namespace Drupal\mof;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Licenses provided by spdx.org/licenses/
 */
final class LicenseHandler implements LicenseHandlerInterface {

  // These license IDs are considered open for data component types.
  const OPEN_DATA_LICENSES = [
    'CC0-1.0',
    'CC-BY-1.0',
    'CC-BY-2.0',
    'CC-BY-2.5',
    'CC-BY-2.5-AU',
    'CC-BY-3.0',
    'CC-BY-3.0-AT',
    'CC-BY-3.0-AU',
    'CC-BY-3.0-DE',
    'CC-BY-3.0-IGO',
    'CC-BY-3.0-NL',
    'CC-BY-3.0-US',
    'CC-BY-4.0',
    'CC-BY-SA-1.0',
    'CC-BY-SA-2.0',
    'CC-BY-SA-2.0-UK',
    'CC-BY-SA-2.1-JP',
    'CC-BY-SA-2.5',
    'CC-BY-SA-3.0',
    'CC-BY-SA-3.0-AT',
    'CC-BY-SA-4.0',
    'CDLA-Permissive-1.0',
    'CDLA-Permissive-2.0',
    'CDLA-Sharing-1.0',
    'ODC-PDDL-1.0',
    'ODC-By-1.0',
    'ODbL-1.0',
    'GFDL-1.3',
    'OGL-Canada-2.0',
    'OGL-UK-2.0',
    'OGL-UK-3.0',
  ];

  // All licenses.
  public readonly array $licenses;

  /**
   * Constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->licenses = $entity_type_manager->getStorage('license')->loadMultiple();
  }

  /**
   * Return a list of FSF-approved licenses.
   *
   * @return array List of isFsfLibre licesnses.
   */
  public function getFsfApproved(): array {
    return array_filter($this->licenses, fn($l) => $l->isFsfApproved());
  }

  /**
   * Return a list of deprecated licenses.
   *
   * @return array List of deprecated licenses.
   */
  private function getDeprecated(): array {
    return array_filter($this->licenses, fn($l) => $l->isDeprecated());
  }

  /**
   * Return a list of OSI-approved licenses.
   *
   * @return array List of open/ OSI-approved licenses.
   */
  public function getOsiApproved(): array {
    return array_filter($this->licenses, fn($l) => $l->isOsiApproved());
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
    return array_filter($this->licenses, fn($l) => in_array($type, $l->getContentType()));
  }

  /**
   * Determines if a specific license is open/ OSI-approved.
   *
   * @param string $license
   *   The license ID to check if it is open.
   *
   * @return bool TRUE if open, FALSE otherwise.
   */
  public function isOsiApproved(string $license): bool {
    return in_array($license, array_map(fn($l) => $l->getLicenseId(), $this->getOsiApproved()));
  }

  /**
   * Determines if a specific license is FSF-approved.
   *
   * @param string $license
   *   The license ID to check if it is FSF.
   *
   * @return bool TRUE if open, FALSE otherwise.
   */
  public function isFsfApproved(string $license): bool {
    return in_array($license, array_map(fn($l) => $l->getLicenseId(), $this->getFsfApproved()));
  }

  /**
   * Determines if a specific license is deprecated.
   *
   * @param string $license
   *   The license ID to check if it is deprecated.
   *
   * @return @bool TRUE if deprecated, FALSE otherwise.
   */
  public function isDeprecated(string $license): bool {
    return in_array($license, array_map(fn($l) => $l->getLicenseId(), $this->getDeprecated()));
  }

  /**
   * Determines if a specific license is open data.
   *
   * @param string $license
   *   The license ID to check if it is open.
   *
   * @return bool TRUE if open, FALSE otherwise.
   */
  public function isOpenData(string $license): bool {
    return in_array($license, self::OPEN_DATA_LICENSES);
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
  public function isOpenSourceLicense(string $license): bool {
    return $this->isFsfApproved($license)
      || $this->isOpenData($license)
      || $this->isOsiApproved($license);
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
  public function isTypeAppropriate(string $license, string $type): bool {
    return in_array($license, array_map('strval', $this->getLicensesByType($type)));
  }
}
