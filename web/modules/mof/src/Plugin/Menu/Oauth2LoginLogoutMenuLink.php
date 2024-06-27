<?php

declare(strict_types=1);

namespace Drupal\mof\Plugin\Menu;

use Drupal\user\Plugin\Menu\LoginLogoutMenuLink;

class Oauth2LoginLogoutMenuLink extends LoginLogoutMenuLink {

  /**
   * {@inheritdoc}
   */
  public function getRouteName() {
    return $this->currentUser->isAuthenticated() ? 'user.logout' : 'social_auth.network.redirect';
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteParameters() {
    return ['network' => 'github'];
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    if ($this->currentUser->isAuthenticated()) {
      return $this->t('Logout (@email)', ['@email' => $this->currentUser->getEmail()]);
    }
    else {
      return $this->t('Log in');
    }
  }

}
