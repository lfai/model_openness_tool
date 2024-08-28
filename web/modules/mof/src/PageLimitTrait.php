<?php

declare(strict_types=1);

namespace Drupal\mof;

use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

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

  /**
   * Returns a Url with new page number if there are not enough
   * entities to display on the current page. Returns NULL otherwise.
   *
   * @return \Drupal\Core\Url|null
   */
  public function getPageRedirectUrl(): ?Url {
    $request = \Drupal::service('request_stack')->getCurrentRequest();

    $this->setPageLimit();

    $model_count = $this->getEntityCount();
    if ($model_count === 0) {
      return NULL;
    }

    $current_page = (int) $request->get('page', 0);
    $max_page = ceil($model_count / $this->limit);
    if ($current_page <= $max_page - 1) {
      return NULL;
    }

    return Url::fromRoute('<current>', [], ['query' => [
      'page' => max($max_page - 1, 0),
      'limit' => $this->limit,
    ]]);
  }

  /**
   * Redirect to the specified page.
   *
   * @param \Drupal\Core\Url $url URL to redirect to.
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   */
  public function redirectPage(Url $url): RedirectResponse {
    return new RedirectResponse($url->toString());
  }

  /**
   * Count of total entites.
   *
   * @return int Number of entities.
   */
  protected function getEntityCount(): int {
    return $this
      ->getStorage()
      ->getQuery()
      ->accessCheck(TRUE)
      ->count()
      ->execute();
  }

}

