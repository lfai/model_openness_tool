<?php declare(strict_types=1);

namespace Drupal\mof;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface defining a license entity.
 */
interface LicenseInterface extends ContentEntityInterface {

  /**
   * Retrieve license name.
   *
   * @return string License name.
   */
  public function getName(): string;

  /**
   * Retrieve license id.
   *
   * @return string License id.
   */
  public function getLicenseId(): string;

  /**
   * Determine if license is OSI approved.
   *
   * @return bool TRUE if OSI approved; FALSE otherwise.
   */
  public function isOsiApproved(): bool;

  /**
   * Determine if a license is FSF approved.
   *
   * @return bool TRUE if FSFLibre approved; FALSE otherwise.
   */
  public function isFsfApproved(): bool;

  /**
   * Determine if a license is deprecated.
   *
   * @return bool TRUE if license is deprecated, FALSE otherwise.
   */
  public function isDeprecated(): bool;

  /**
   * Get license content type.
   *
   * @return string An array of values which may include 'code', 'document', and 'data'
   */
  public function getContentType(): array;

  /**
   * Transform license entity to an array.
   *
   * @return array Array representation of the license entity.
   */
  public function toArray(): array;

}
