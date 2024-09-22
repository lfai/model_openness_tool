<?php declare(strict_types=1);

use \Drupal\Component\Serialization\Yaml;
use \Drupal\Component\Serialization\Exception\InvalidDataTypeException;

$path = \Drupal::root() . '/../models';
$updater = \Drupal::service('model_updater');

if (is_dir($path)) {
  try {
    $it = new \DirectoryIterator($path);

    foreach ($it as $file) {
      if ($file->isDot()) continue;
      if (!$file->isFile()) continue;
      if ($file->getExtension() !== 'yml') continue;

      $filepath = $file->getPathname();
      $model = file_get_contents($filepath);
      $model = Yaml::decode($model)['release'];

      print "Processing model {$model['name']}\n";

      if (($entity = $updater->exists($model)) !== NULL) {
        $rc = $updater->update($entity, $model);
      }
      else {
        $rc = $updater->create($model);
      }

      if ($rc === SAVED_NEW || $rc === SAVED_UPDATED) {
        if ($rc === SAVED_NEW) {
          print "Created model {$model['name']}\n";
        }
        else if ($rc === SAVED_UPDATED) {
          print "Updated model {$model['name']}\n";
        }

        rename($filepath, $filepath . '-');
      }
    }
  }
  catch (\UnexpectedValueException $e) {
    print 'Failed opening models directory: ' . $e->getMessage();
  }
  catch (InvalidDataTypeException $e) {
    print 'Invalid YAML format: ' . $e->getMessage();
  }
}
else {
  print "Models directory does not exist.";
}

