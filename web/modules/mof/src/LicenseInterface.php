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
   * Get license content type.
   *
   * @return string Accepted values: 'code', 'document', and 'data' or NULL if not set
   */
  public function getContentType(): ?string;

  /**
   * Transform license entity to an array.
   *
   * @return array Array representation of the license entity.
   */
  public function toArray(): array;

}
