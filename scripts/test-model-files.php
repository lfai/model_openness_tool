<?php

use Symfony\Component\Yaml\Yaml;

function parseExpectedFromFileName(string $file): array {
    $basename = basename($file, '.yml');
    $pattern = '/_C1_(\d+)%\-C2_(\d+)%\-C3_(\d+)%_(\d+)C_(\d+)G_(\d+)L_(\d+)V_(\d+)I_(\d+)T/';
    if (preg_match($pattern, $basename, $matches)) {
        return [
            'class_1_progress' => (int)$matches[1],
            'class_2_progress' => (int)$matches[2],
            'class_3_progress' => (int)$matches[3],
            'num_components' => (int)$matches[4],
            'num_global_licenses' => (int)$matches[5],
            'num_component_licenses' => (int)$matches[6],
            'num_valid_licenses' => (int)$matches[7],
            'num_invalid_licenses' => (int)$matches[8],
            'num_type_appropriate_licenses' => (int)$matches[9],
        ];
    }
    return [];
}

$dir = __DIR__ . '/../Test_Data/';
$componentManager = \Drupal::service('component.manager');
$modelUpdater = \Drupal::service('model_updater');
$licenseHandler = \Drupal::service('license_handler');
$modelValidator = \Drupal::service('model_validator');
$evaluator = \Drupal::service('model_evaluator');
$modelStorage = \Drupal::entityTypeManager()->getStorage('model');

$total = 0;
$fail = 0;

foreach (glob($dir . '*.yml') as $file) {
    $yaml = Yaml::parseFile($file);
    $modelData = $yaml['release'] ?? $yaml;

    // Create or update the model entity
    if (($entity = $modelUpdater->exists($modelData)) !== NULL) {
        $modelUpdater->update($entity, $modelData);
        $model_id = $entity->id();
    } else {
        $model_id = $modelUpdater->create($modelData);
    }

    // Load the created/updated model entity
    $modelEntity = $modelStorage->load($model_id);

    // Evaluate
    $evaluator->setModel($modelEntity);

    $actual = [
        'class_1_progress' => round($evaluator->getProgress(1)),
        'class_2_progress' => round($evaluator->getProgress(2)),
        'class_3_progress' => round($evaluator->getProgress(3)),
    ];

    $expected = parseExpectedFromFileName($file);

    $ok = $actual == array_intersect_key($expected, $actual);
    echo basename($file) . ': ' . ($ok ? "PASS" : "FAIL") . PHP_EOL;
    if (!$ok) {
        echo "  Expected: " . json_encode($expected) . PHP_EOL;
        echo "  Actual:   " . json_encode($actual) . PHP_EOL;
        $fail++;
    }
    $total++;
}