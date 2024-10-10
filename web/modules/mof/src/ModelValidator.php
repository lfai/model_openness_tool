<?php declare(strict_types=1);

namespace Drupal\mof;

use Symfony\Component\Yaml\Yaml;
use Opis\JsonSchema\Validator;
use Opis\JsonSchema\Errors\ErrorFormatter;

final class ModelValidator {

  /** @var \Opis\JsonSchema\Validator. */
  private readonly Validator $validator;

  /**
   * Construct a ModelValidator instance.
   */
  public function __construct() {
    $this->validator = new Validator();
  }

  /**
   * Validate a YAML representation of a model.
   * Ensure it adheres to the MOF model schema.
   *
   * @param string $yaml
   *   Path to a model yaml file.
   * @param stdClass $schema
   *   JSON schema converted to a stdClass object via json_decode().
   * @return bool 
   *   TRUE if valid, FALSE if invalid.
   */
  public function validate(string $yaml, \stdClass $schema): bool {
    $yaml_model = Yaml::parseFile($yaml);
    $json_model = $this->convert($yaml_model);
    $result = $this->validator->validate($json_model, $schema);

    if ($result->hasError()) {
      print_r((new ErrorFormatter())->format($result->error()));
    }

    return $result->isValid();
  }

  /**
   * Convert our YAML structure to a stdClass object.
   *
   * @param mixed $yaml
   *   YAML data to convert.
   * @return mixed
   *   The converted data as a stdClass object.
   */
  private function convert($yaml) {
    if (is_array($yaml)) {
      $is_assoc = array_keys($yaml) !== range(0, sizeof($yaml) - 1);
      $result = array_map([$this, __FUNCTION__], $yaml);
      return $is_assoc ? (object) $result : $result;
    }
    return $yaml;
  }

}


