<?php

declare(strict_types=1);

namespace Drupal\mof\Routing;

use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\Entity\EntityTypeInterface;

class ModelEntityRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  protected function getAddFormRoute(EntityTypeInterface $entity_type) {
    if ($route = parent::getAddFormRoute($entity_type)) {
      $route->setOption('_admin_route', FALSE);
      $route->setRequirement('_user_is_logged_in', 'TRUE');
      return $route;
    }
  }

}

