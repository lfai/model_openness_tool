<?php

declare(strict_types=1);

namespace Drupal\mof\Services;

use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Helper service for GitHub API operations.
 */
class GitHubApiHelper {

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
   * Constructs a GitHubApiHelper object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    ClientInterface $http_client,
    LoggerInterface $logger
  ) {
    $this->httpClient = $http_client;
    $this->logger = $logger;
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
  public function fetchPrimaryEmail(string $token): ?string {
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

  /**
   * Fetch the user's name from GitHub API.
   *
   * @param string $token
   *   The OAuth access token.
   *
   * @return string|null
   *   The user's name or null if not found.
   */
  public function fetchUserName(string $token): ?string {
    try {
      $response = $this->httpClient->request('GET', 'https://api.github.com/user', [
        'headers' => [
          'Authorization' => 'Bearer ' . $token,
          'Accept' => 'application/vnd.github+json',
          'X-GitHub-Api-Version' => '2022-11-28',
        ],
      ]);

      $user_data = json_decode($response->getBody()->getContents(), TRUE);

      if (!is_array($user_data)) {
        $this->logger->error('GitHub API returned invalid user data');
        return NULL;
      }

      // Return the name if available
      if (!empty($user_data['name'])) {
        $this->logger->info('Found user name from GitHub API');
        return $user_data['name'];
      }

      $this->logger->warning('No name found in GitHub API response');
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to fetch user name from GitHub API: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    return NULL;
  }

}

// Made with Bob