<?php declare(strict_types=1);

namespace Drupal\mof;

use Drupal\Core\StringTranslation\StringTranslationTrait;

final class BadgeGenerator implements BadgeGeneratorInterface {

  use StringTranslationTrait;

  public function __construct(
    private readonly ModelEvaluatorInterface $modelEvaluator
  ) {}

  /**
   * {@inheritdoc}
   */
  public function generate(ModelInterface $model, bool $mini = FALSE): array {
    $build = [];

    $evals = $this->modelEvaluator->setModel($model)->evaluate();
    $qualified = $in_progress = FALSE;

    for ($i = 3, $j = 3; $i >= 1; $i--, $j--) {
      $progress = $this->modelEvaluator->getProgress($i);

      // Conditional is a Pass
      if ($progress === 100.00 || ($i === 3 && $evals[$i]['conditional'] === TRUE && $evals[$i]['components']['missing'] == null && $evals[$i]['components']['invalid'] == null)) {
        $status = $this->t('Qualified');
        $text_color = '#fff';
        $background_color = '#4c1';
        if ($mini && $qualified) {
          // replace previous one to only keep the highest one
          $j++;
        }
        $qualified = TRUE;
      }
      else if ($progress == 0) {
        if ($mini) {
          // do not include classes that are not met
          continue;
        }
        $status = $this->t('Not met');
        $text_color = '#fff';
        $background_color = '#9ba0a2';
      }
      else {
        if ($mini && $in_progress) {
          // only include the first in progress
          continue;
        }
        $status = $this->t('In progress (@progress%)', ['@progress' => round($progress)]);
        $text_color = '#fff';
        $background_color = '#76b1c9';
        $in_progress = TRUE;
      }

      $build[$j] = [
        '#theme' => 'badge',
        '#status' => $status,
        '#label' => $this->modelEvaluator->getClassLabel($i),
        '#text_color' => $text_color,
        '#background_color' => $background_color,
        '#weight' => $i,
      ];
    }

    return $build;
  }
}

