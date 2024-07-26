<?php declare(strict_types=1);

namespace Drupal\mof;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;

class LicenseImporter {

  /**
   * Constructor.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ModuleExtensionList $moduleExtensionList
  ) {}

  /**
   * Import licenses.
   */
  public function import() {
    $path = $this->moduleExtensionList->getPath('mof');

    foreach (['mof-licenses.json', 'licenses.json'] as $file) {
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
        $license_id = $license['licenseId'];

        $entity = $license_storage
          ->loadByProperties(['license_id' => $license_id]);

        if (!empty($entity)) {
          continue;
        }

        $entity = $license_storage->create([
          'name' => $license['name'],
          'license_id' => $license_id,
          'reference' => $license['reference'],
          'reference_number' => $license['referenceNumber'],
          'deprecated_license_id' => $license['isDeprecatedLicenseId'],
          'osi_approved' => $license['isOsiApproved'] ?? false,
          'fsf_libre' => $license['isFsfLibre'] ?? '',
          'details_url' => $license['detailsUrl'],
          'see_also' => $license['seeAlso'],
          'content_type' => $license['ContentType'] ?? '',
        ]);

        $entity->save();
      }
    } 
  }

}
