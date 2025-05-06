<?php declare(strict_types=1);

namespace Drupal\mof;

interface BadgeGeneratorInterface {

  /**
   * Generate class badges for the specified model.
   *
   * @param \Drupal\mof\ModelInterface $model
   *   The model for which we are generating the badge.
   *
   * @param bool $mini
   *   When true, only include earned badges (i.e., conditional or qualified)
   *   with the highest qualified and lowest in progress.
   *   This is used for the ModelList page.
   *
   * @return array
   *   A Drupal render array of badges for the model.
   */
  public function generate(ModelInterface $model, bool $mini = FALSE): array;

}

