<?php

/**
 * This script generates models in YAML or JSON format
 * from Drupal model entities.
 *
 * Usage:
 * Invoke this script with Drush from the project root.
 * Pass 'json' or 'yaml' as an argument.
 *
 * Example:
 * vendor/bin/drush scr scripts/generate-models.php json
 * vendor/bin/drush scr scripts/generate-models.php yaml
 */

use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\RuntimeException;
use Drush\Drush;

$args = Drush::input()->getArguments();

if (!isset($args['extra'][1])) {
  throw new InvalidArgumentException('Missing "json" or "yaml" argument.');
}

if ($args['extra'][1] !== 'json' && $args['extra'][1] !== 'yaml') {
  throw new InvalidArgumentException('Invalid argument.');
}

$format = $args['extra'][1];
$serializer = \Drupal::service('model_serializer');
$path = \Drupal::root() . '/../models/';

foreach (\Drupal::entityTypeManager()
  ->getStorage('model')
  ->loadMultiple() as $model) {

  if ($format === 'yaml') {
    $output = $serializer->toYaml($model);
    $ext = '.yml';
  }
  else if ($format === 'json') {
    $output = $serializer->toJson($model);
    $ext = '.json';
  }

  $bytes = file_put_contents($path . $model->label() . $ext, $output);

  if ($bytes === false) {
    throw new RuntimeException('Failed to write model file.');
  }
}

