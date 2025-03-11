<?php declare(strict_types=1);

namespace Drupal\mof;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Extension\ModuleExtensionList;
use Psr\Log\LoggerInterface;

final class LicenseImporter {

  /**
   * Constructor.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ModuleExtensionList $moduleExtensionList,
    private readonly LoggerInterface $logger
  ) {}

  /**
   * Update the license entity.
   *
   * @param \Drupal\mof\LicenseInterface $license The existing license entity.
   * @param array $data License data.
   */
  private function setLicenseData(LicenseInterface $license, array $data) {
    try {
      $license
        ->set('name', $data['name'])
        ->set('license_id', $data['licenseId'])
        ->set('reference', $data['reference'])
        ->set('reference_number', $data['referenceNumber'])
        ->set('deprecated_license_id', $data['isDeprecatedLicenseId'])
        ->set('osi_approved', $data['isOsiApproved'] ?? false)
        ->set('fsf_libre', $data['isFsfLibre'] ?? '')
        ->set('details_url', $data['detailsUrl'])
        ->set('see_also', $data['seeAlso'])
        ->set('content_type', $data['ContentType'] ?? '')
        ->save();
    }
    catch (EntityStorageException $e) {
      $message = snprintf(
        'Failed to save license entity "%s" with ID "%s": %s',
        $data['name'], $data['licenseId'], $e->getMessage()
      );

      $this->logger->error($message);
      throw new EntityStorageException($message, $e->getCode(), $e);
    }
  }

  /**
   * Import licenses.
   */
  public function import() {
    $path = $this->moduleExtensionList->getPath('mof');

    foreach (['licenses.json', 'mof-licenses.json'] as $file) {
      $filepath = $path .'/'. $file;

      if (!file_exists($filepath)) {
        continue;
      }

      $json = file_get_contents($filepath);
      $json = json_decode($json, true);

      if (json_last_error() !== JSON_ERROR_NONE) {
        continue;
      }

      $license_storage = $this
        ->entityTypeManager
        ->getStorage('license');

      foreach ($json['licenses'] as $license) {
        $entity = $license_storage->loadByProperties(['license_id' => $license['licenseId']]);
        $entity = !empty($entity) ? reset($entity) : $license_storage->create();
        $this->setLicenseData($entity, $license);
      }
    } 
  }

}
