<?php declare(strict_types=1);

namespace Drupal\mof;

interface ModelEvaluatorInterface {

  public function setModel(ModelInterface $model): self;

  public function evaluate(): array;

}

