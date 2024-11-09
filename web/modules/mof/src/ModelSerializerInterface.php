<?php declare(strict_types=1);

namespace Drupal\mof;

interface ModelSerializerInterface {

  /**
   * Transform model to an array for serialization.
   *
   * @param \Drupal\mof\ModelInterface $model
   *   The model to process.
   * @return array
   *   An array representing the model.
   */
  public function normalize(ModelInterface $model): array;

  /**
   * Return a YAML representation of the model.
   *
   * @param \Drupal\mof\ModelInterface $model
   *   The model to convert to YAML.
   * @return string
   *   A formatted string representing the model in YAML.
   */
  public function toYaml(ModelInterface $model): string;

  /**
   * Return a JSON representation of the model.
   *
   * @param \Drupal\mof\ModelInterface $model
   *   The model to convert to JSON.
   * @return string
   *   A formatted string representing the model in JSON.
   */
  public function toJson(ModelInterface $model): string;

}
