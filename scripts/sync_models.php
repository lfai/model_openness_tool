<?php declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;
use Drush\Drush;

$args = Drush::input()->getArguments();
$path = \Drupal::root();
// Get model path from command line argument or use default.
$model_path = $args['extra'][1] ?? $path . '/../models';
print 'Loading models from ' . $model_path . PHP_EOL;
$schema_path = $path . '/../schema';

if (is_dir($model_path)) {
  try {
    $updater = \Drupal::service('model_updater');
    $validator = \Drupal::service('model_validator');
    $schema = json_decode(file_get_contents($schema_path . '/mof_schema.json'));

    if (!is_dir($model_path . '/.processed')) {
      if (!mkdir($model_path . '/.processed', 0755)) {
        print 'Failed to create processed directory.' . PHP_EOL;
      }
    }

    $it = new \DirectoryIterator($model_path);
    foreach ($it as $file) {
      if ($file->isDot()) continue;
      if (!$file->isFile()) continue;
      if ($file->getExtension() !== 'yml') continue;

      $filename = $file->getFilename();
      $filepath = $file->getPathname();

      $yamlstr = file_get_contents($filepath);

      if (file_exists($model_path . '/.processed/' . $filename)) {
        $processed = file_get_contents($model_path . '/.processed/' . $filename);
        if ($yamlstr === $processed) continue;
      }

      print "Processing {$filename}" . PHP_EOL;

      if (!$validator->validate($filepath, $schema)) {
        print 'Model failed validation.' . PHP_EOL;
        continue;
      }

      $model = Yaml::parse($yamlstr)['release'];
      if (($entity = $updater->exists($model)) !== NULL) {
        $rc = $updater->update($entity, $model);
      }
      else {
        $rc = $updater->create($model);
      }

      if ($rc === SAVED_NEW || $rc === SAVED_UPDATED) {
        if ($rc === SAVED_NEW) {
          print "Created model {$model['name']}" . PHP_EOL;
        }
        else if ($rc === SAVED_UPDATED) {
          print "Updated model {$model['name']}" . PHP_EOL;
        }

        copy($filepath, $model_path . '/.processed/' . $filename);
      }
    }
  }
  catch (\UnexpectedValueException $e) {
    print 'Failed opening models directory: ' . $e->getMessage() . PHP_EOL;
  }
  catch (\ValueError $e) {
    print "Invalid value: " . $e->getMessage() . PHP_EOL;
  }
  catch (ParseException $e) {
    print 'Invalid YAML format: ' . $e->getMessage() . PHP_EOL;
  }
}
else {
  print "Models directory does not exist." . PHP_EOL;
}

