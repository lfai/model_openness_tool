<?php

$url = 'https://github.com/filamentgroup/tablesaw/archive/refs/tags/v3.1.0.zip';
$temp_file = 'tablesaw.zip';
$libraries_dir = __DIR__ . '/../web/libraries';
$tablesaw_dir = $libraries_dir . '/tablesaw';

// Do nothing if tablesaw already exists.
if (file_exists($tablesaw_dir)) {
  return;
}

// Download the ZIP file
file_put_contents($temp_file, fopen($url, 'r'));

// Create the libraries directory if it doesn't exist
if (!file_exists($libraries_dir)) {
  mkdir($libraries_dir, 0755, true);
}

// Extract the ZIP file
$zip = new ZipArchive;
if ($zip->open($temp_file) === TRUE) {
  $zip->extractTo($libraries_dir);
  $zip->close();
}

// Clean up
unlink($temp_file);

// Rename extracted folder to 'tablesaw'
$extracted_folder = $libraries_dir . '/tablesaw-3.1.0';
if (file_exists($extracted_folder)) {
  rename($extracted_folder, $tablesaw_dir);
}

