<?php

declare(strict_types=1);

namespace Drupal\mof\EventSubscriber;

use Drupal\mof\Services\GitHubApiHelper;
use Drupal\social_auth\Event\UserEvent;
use Drupal\social_auth\Event\SocialAuthEvents;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for GitHub authentication events.
 */
class GitHubSubscriber implements EventSubscriberInterface {

  /**
   * The GitHub API helper.
   *
   * @var \Drupal\mof\Services\GitHubApiHelper
   */
  protected $githubApiHelper;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a GitHubSubscriber object.
   *
   * @param \Drupal\mof\Services\GitHubApiHelper $github_api_helper
   *   The GitHub API helper.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(GitHubApiHelper $github_api_helper, LoggerInterface $logger) {
    $this->githubApiHelper = $github_api_helper;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[SocialAuthEvents::USER_CREATED] = ['onUserCreated'];
    return $events;
  }

  /**
   * Set Drupal user email address from GitHub user.
   *
   * Handles cases where the email is private on GitHub by fetching
   * from the GitHub API /user/emails endpoint.
   *
   * @param \Drupal\social_auth\Event\UserEvent $event
   *   The user event.
   */
  public function onUserCreated(UserEvent $event) {
    $user = $event->getUser();
    $social_auth_user = $event->getSocialAuthUser();
    $user_data = $social_auth_user->getAdditionalData();

    // Try to get email from resource_owner first
    $email = $user_data['resource_owner'][0]['email'] ?? null;

    // If email is null or empty, fetch from GitHub API
    if (empty($email)) {
      $this->logger->info('Email not found in OAuth response, fetching from GitHub API for user @username', [
        '@username' => $user->getAccountName(),
      ]);
      $email = $this->githubApiHelper->fetchPrimaryEmail($social_auth_user->getToken());
    }

    // If still no email, use GitHub username as fallback
    if (empty($email)) {
      $username = $user_data['resource_owner'][0]['login'] ?? $user->getAccountName();
      $email = $username . '@users.noreply.github.com';
      $this->logger->warning('Could not retrieve email from GitHub, using fallback: @email', [
        '@email' => $email,
      ]);
    }

    // Set email and save user
    $user->setEmail($email)->save();

    $this->logger->info('Set email @email for GitHub user @username', [
      '@email' => $email,
      '@username' => $user->getAccountName(),
    ]);
  }

}

// Made with Bob
