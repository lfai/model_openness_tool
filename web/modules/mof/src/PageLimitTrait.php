<?php

declare(strict_types=1);

namespace Drupal\mof;

trait PageLimitTrait {

  /**
   * Set number of entities per page for an entity list builder.
   */
  public function setPageLimit(): void {
    $request = \Drupal::service('request_stack')->getCurrentRequest();
    if (($limit = $request->get('limit')) !== NULL && intval($limit) > 0) {
      $this->limit = $limit;
    }
  }

}

