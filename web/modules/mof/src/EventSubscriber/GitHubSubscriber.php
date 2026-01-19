<?php

declare(strict_types=1);

namespace Drupal\mof\EventSubscriber;

use Drupal\social_auth\Event\UserEvent;
use Drupal\social_auth\Event\SocialAuthEvents;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for GitHub authentication events.
 */
class GitHubSubscriber implements EventSubscriberInterface {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a GitHubSubscriber object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(ClientInterface $http_client, LoggerInterface $logger) {
    $this->httpClient = $http_client;
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
      $email = $this->fetchPrimaryEmail($social_auth_user->getToken());
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

  /**
   * Fetch the user's primary email from GitHub API.
   *
   * @param string $token
   *   The OAuth access token.
   *
   * @return string|null
   *   The primary email address or null if not found.
   */
  private function fetchPrimaryEmail(string $token): ?string {
    try {
      $response = $this->httpClient->request('GET', 'https://api.github.com/user/emails', [
        'headers' => [
          'Authorization' => 'Bearer ' . $token,
          'Accept' => 'application/vnd.github+json',
          'X-GitHub-Api-Version' => '2022-11-28',
        ],
      ]);
      
      $emails = json_decode($response->getBody()->getContents(), TRUE);
      
      if (!is_array($emails)) {
        $this->logger->error('GitHub API returned invalid email data');
        return NULL;
      }
      
      // Find the primary verified email
      foreach ($emails as $email_data) {
        if (isset($email_data['primary']) && $email_data['primary'] && 
            isset($email_data['verified']) && $email_data['verified']) {
          $this->logger->info('Found primary verified email from GitHub API');
          return $email_data['email'];
        }
      }
      
      // If no primary email, return the first verified email
      foreach ($emails as $email_data) {
        if (isset($email_data['verified']) && $email_data['verified']) {
          $this->logger->info('Found verified email from GitHub API (not primary)');
          return $email_data['email'];
        }
      }
      
      $this->logger->warning('No verified email found in GitHub API response');
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to fetch email from GitHub API: @message', [
        '@message' => $e->getMessage(),
      ]);
    }
    
    return NULL;
  }

}

// Made with Bob
