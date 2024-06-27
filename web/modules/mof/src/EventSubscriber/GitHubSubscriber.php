<?php

declare(strict_types=1);

namespace Drupal\mof\EventSubscriber;

use Drupal\social_auth\Event\UserEvent;
use Drupal\social_auth\Event\SocialAuthEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class GitHubSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[SocialAuthEvents::USER_CREATED] = ['onUserCreated'];
    return $events;
  }

  /**
   * Set Drupal user email address from github user.
   */
  public function onUserCreated(UserEvent $event) {
    $user = $event->getUser();
    $user_data = $event->getSocialAuthUser()->getAdditionalData();
    $user->setEmail($user_data['resource_owner'][0]['email'])->save();
  } 

}
