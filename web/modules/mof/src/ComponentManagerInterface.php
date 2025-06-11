<?php

declare(strict_types=1);

namespace Drupal\mof;

interface ComponentManagerInterface {

  public function getComponents(): array;

  public function getComponent(int $component_id): Component;

  public function getRequired(int $class): array;

  public function getOptional(int $class): array;
}

