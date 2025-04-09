<?php declare(strict_types=1);

namespace Drupal\mof;

interface ModelEvaluatorInterface {

  /**
   * Sets the model to be evaluated.
   *
   * @param \Drupal\mof\ModelInterface $model
   */
  public function setModel(ModelInterface $model): self;

  /**
   * Run the model evaluation and build a report.
   *
   * @return array An array of evaluation results.
   */
  public function evaluate(): array;

}

