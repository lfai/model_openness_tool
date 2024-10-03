<?php declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

$path = \Drupal::root();
$model_path = $path . '/../models';
$schema_path = $path . '/../schema';

if (is_dir($model_path)) {
  try {
    $updater = \Drupal::service('model_updater');
    $validator = \Drupal::service('model_validator');
    $schema = json_decode(file_get_contents($schema_path . '/mof_schema.json'));

    $it = new \DirectoryIterator($model_path);
    foreach ($it as $file) {
      if ($file->isDot()) continue;
      if (!$file->isFile()) continue;
      if ($file->getExtension() !== 'yml') continue;

      print "Processing {$file->getFilename()}...\n";

      $filepath = $file->getPathname();

      if (!$validator->validate($filepath, $schema)) {
        print "Model failed validation.\n";
        continue;
      }

      $model = Yaml::parseFile($filepath)['release'];
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
  catch (\ValueError $e) {
    print "Invalid value: " . $e->getMessage();
  }
  catch (ParseException $e) {
    print 'Invalid YAML format: ' . $e->getMessage();
  }
}
else {
  print "Models directory does not exist.";
}

