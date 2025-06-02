<?php

/**
 * This script marks all model records older than 2025-01-01 as unapproved to hide them.
 *
 * Usage:
 * Invoke this script with Drush from the project root.
 *
 * Example:
 * vendor/bin/drush scr scripts/list-models.php
 */

use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\RuntimeException;
use Drush\Drush;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\mof\Entity\Model;

$args = Drush::input()->getArguments();

/** @var Drupal\Core\Datetime\DateFormatterInterface $date_formatter */
$date_formatter = \Drupal::service('date.formatter');

$limit = new DrupalDateTime('2025-01-01');
$timestamp = strtotime($limit->format('Y-m-d\TH:i:s'));

foreach (\Drupal::entityTypeManager()
  ->getStorage('model')
  ->loadMultiple() as $model) {

  if ($model->get('changed')->value < $timestamp) {
    print $model->label() . ' marked unapproved' . PHP_EOL;
    $model->setStatus(Model::STATUS_UNAPPROVED);
    $model->save();
  }
}

