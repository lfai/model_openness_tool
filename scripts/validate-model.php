<?php declare(strict_types=1);

include 'web/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Opis\JsonSchema\Validator;
use Opis\JsonSchema\Errors\ErrorFormatter;

if (!isset($argv[1])) {
  fwrite(STDERR, 'Missing model argument.' . PHP_EOL);
  exit(1);
}

if (($yaml = file_get_contents($argv[1])) === false) {
  fwrite(STDERR, 'Failed opening models yml file.' . PHP_EOL);
  exit(1);
}

if (($schema = file_get_contents('schema/mof_schema.json')) === false) {
  fwrite(STDERR, 'Failed opening schema file.' . PHP_EOL);
  exit(1);
}

try {
  $schema = json_decode($schema);

  // Convert YAML-parsed array to stdClass object via JSON round-trip.
  $yaml = json_decode(json_encode(Yaml::parse($yaml)));

  $validator = new Validator();
  $result = $validator->validate($yaml, $schema);

  print $result->isValid() ? 'Model is valid.' : 'Model failed validation.';
  print PHP_EOL;

  if ($result->hasError()) {
    print_r((new ErrorFormatter())->formatKeyed($result->error()));
    exit(1);
  }
}
catch (\Symfony\Component\Yaml\Exception\ParseException $e) {
  fwrite(STDERR, $e->getMessage() . PHP_EOL);
  exit(1);
}
catch (\Opis\JsonSchema\Exceptions\SchemaException $e) {
  fwrite(STDERR, $e->getMessage() . PHP_EOL);
  exit(1);
}
catch (\ValueError $e) {
  fwrite(STDERR, $e->getMessage() . PHP_EOL);
  exit(1);
}

exit;

