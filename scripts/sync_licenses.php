<?php declare(strict_types=1);

try {
  print "Syncing licenses..." . PHP_EOL;
  \Drupal::service('license_importer')->import();
  print "Done." . PHP_EOL;
}
catch (\Drupal\Core\Entity\EntityStorageException $e) {
  print $e->getMessage() . PHP_EOL;
}

