<?php

declare(strict_types=1);

namespace Drupal\mof;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a model entity type.
 */
interface ModelInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
