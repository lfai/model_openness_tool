<?php

declare(strict_types=1);

namespace Drupal\mof\Services;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

/**
 * Service to fork, create branches, commit files & create PRs on GitHub.
 */
class GitHubPullRequestManager {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a GitHubPullRequestManager object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    ClientInterface $http_client,
    EntityTypeManagerInterface $entity_type_manager,
    AccountProxyInterface $current_user,
    LoggerInterface $logger
  ) {
    $this->httpClient = $http_client;
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->logger = $logger;
  }

  /**
   * Get the social_auth entity for the current user and GitHub provider.
   *
   * @return \Drupal\social_auth\Entity\SocialAuth|null
   *   The social auth entity or NULL if not found.
   */
  protected function getSocialAuthEntity(): ?object {
    try {
      $storage = $this->entityTypeManager->getStorage('social_auth');
      $entities = $storage->loadByProperties([
        'user_id' => $this->currentUser->id(),
        'plugin_id' => 'social_auth_github',
      ]);

      return $entities ? reset($entities) : NULL;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to load social_auth entity: @message', [
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Get the GitHub access token for the current user.
   *
   * @return string|null
   *   The access token or NULL if not available.
   */
  protected function getAccessToken(): ?string {
    $entity = $this->getSocialAuthEntity();
    if ($entity) {
      try {
        return $entity->getToken();
      }
      catch (\Exception $e) {
        $this->logger->error('Failed to retrieve GitHub access token: @message', [
          '@message' => $e->getMessage(),
        ]);
      }
    }
    return NULL;
  }

  /**
   * Get the GitHub username for the current user.
   *
   * @return string|null
   *   The GitHub username or NULL if not available.
   */
  public function getGitHubUsername(): ?string {
    $entity = $this->getSocialAuthEntity();
    if ($entity) {
      try {
        $additional_data = $entity->getAdditionalData();
        
        // Try multiple possible locations for the username
        // Check resource_owner array
        if (isset($additional_data['resource_owner'][0]['login'])) {
          return $additional_data['resource_owner'][0]['login'];
        }
        
        // Check direct login field
        if (isset($additional_data['login'])) {
          return $additional_data['login'];
        }
        
        // Check if it's in the root of resource_owner (not array)
        if (isset($additional_data['resource_owner']['login'])) {
          return $additional_data['resource_owner']['login'];
        }
        
        // If not found in stored data, fetch from GitHub API
        $this->logger->info('Username not found in stored data, fetching from GitHub API');
        return $this->fetchGitHubUsername();
      }
      catch (\Exception $e) {
        $this->logger->error('Failed to retrieve GitHub username: @message', [
          '@message' => $e->getMessage(),
        ]);
      }
    }
    return NULL;
  }

  /**
   * Fetch the GitHub username from the GitHub API.
   *
   * @return string|null
   *   The GitHub username or NULL if not available.
   */
  protected function fetchGitHubUsername(): ?string {
    try {
      $user_data = $this->request('GET', '/user');
      if (isset($user_data['login'])) {
        $this->logger->info('Retrieved GitHub username from API: @username', [
          '@username' => $user_data['login'],
        ]);
        return $user_data['login'];
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to fetch GitHub username from API: @message', [
        '@message' => $e->getMessage(),
      ]);
    }
    return NULL;
  }

  /**
   * Check if the current user is authenticated with GitHub.
   *
   * @return bool
   *   TRUE if authenticated, FALSE otherwise.
   */
  public function isAuthenticated(): bool {
    return $this->getAccessToken() !== NULL;
  }

  /**
   * Make an authenticated request to the GitHub API.
   *
   * @param string $method
   *   The HTTP method (GET, POST, PUT, etc.).
   * @param string $endpoint
   *   The API endpoint (e.g., '/repos/owner/repo').
   * @param array $options
   *   Additional request options.
   *
   * @return array|null
   *   The decoded JSON response or NULL on failure.
   *
   * @throws \GuzzleHttp\Exception\RequestException
   */
  protected function request(string $method, string $endpoint, array $options = []): ?array {
    $token = $this->getAccessToken();
    if (!$token) {
      throw new \RuntimeException('GitHub access token not available. User must authenticate with GitHub.');
    }

    $options['headers'] = array_merge($options['headers'] ?? [], [
      'Authorization' => 'Bearer ' . $token,
      'Accept' => 'application/vnd.github+json',
      'X-GitHub-Api-Version' => '2022-11-28',
    ]);

    try {
      $response = $this->httpClient->request($method, 'https://api.github.com' . $endpoint, $options);
      return json_decode($response->getBody()->getContents(), TRUE);
    }
    catch (RequestException $e) {
      $this->logger->error('GitHub API request failed: @method @endpoint - @message', [
        '@method' => $method,
        '@endpoint' => $endpoint,
        '@message' => $e->getMessage(),
      ]);
      throw $e;
    }
  }

  /**
   * Ensure the user has a fork of the repo.
   *
   * @param string $sourceOwner
   *   The owner of the source repository.
   * @param string $repo
   *   The repository name.
   *
   * @return array|null
   *   The fork data or NULL on failure.
   */
  public function ensureFork(string $sourceOwner, string $repo): ?array {
    $username = $this->getGitHubUsername();
    if (!$username) {
      throw new \RuntimeException('GitHub username not available.');
    }

    try {
      // Check if fork exists
      return $this->request('GET', "/repos/$username/$repo");
    }
    catch (RequestException $e) {
      // Fork doesn't exist, create it
      if ($e->getResponse() && $e->getResponse()->getStatusCode() === 404) {
        $this->logger->info('Creating fork of @owner/@repo for user @user', [
          '@owner' => $sourceOwner,
          '@repo' => $repo,
          '@user' => $username,
        ]);
        
        // Create the fork and wait a moment for GitHub to process it
        $fork = $this->request('POST', "/repos/$sourceOwner/$repo/forks");
        
        // GitHub needs time to create the fork, so we'll return the fork data
        // The caller should handle any timing issues
        return $fork;
      }
      throw $e;
    }
  }

  /**
   * Create a new branch in the fork.
   *
   * @param string $repo
   *   The repository name.
   * @param string $branch
   *   The new branch name.
   * @param string $base
   *   The base branch (default: 'main').
   *
   * @return array|null
   *   The branch data or NULL on failure.
   */
  public function createBranch(string $repo, string $branch, string $base = 'main'): ?array {
    $username = $this->getGitHubUsername();
    if (!$username) {
      throw new \RuntimeException('GitHub username not available.');
    }

    // Get SHA of base branch
    $ref_data = $this->request('GET', "/repos/$username/$repo/git/ref/heads/$base");
    $sha = $ref_data['object']['sha'];

    // Create new branch reference
    return $this->request('POST', "/repos/$username/$repo/git/refs", [
      'json' => [
        'ref' => "refs/heads/$branch",
        'sha' => $sha,
      ],
    ]);
  }

  /**
   * Create/update a file in the forked repo using the Contents API.
   *
   * @param string $repo
   *   The repository name.
   * @param string $branch
   *   The branch name.
   * @param string $path
   *   The file path in the repository.
   * @param string $content
   *   The file content.
   * @param string $message
   *   The commit message.
   *
   * @return array|null
   *   The commit data or NULL on failure.
   */
  public function commitFile(string $repo, string $branch, string $path, string $content, string $message): ?array {
    $username = $this->getGitHubUsername();
    if (!$username) {
      throw new \RuntimeException('GitHub username not available.');
    }

    $sha = NULL;

    // Try to fetch existing file to get its SHA
    try {
      $file_data = $this->request('GET', "/repos/$username/$repo/contents/$path?ref=$branch");
      $sha = $file_data['sha'] ?? NULL;
    }
    catch (RequestException $e) {
      // File doesn't exist, which is fine for new files
      if ($e->getResponse() && $e->getResponse()->getStatusCode() !== 404) {
        throw $e;
      }
    }

    // Commit content
    $payload = [
      'message' => $message,
      'content' => base64_encode($content),
      'branch' => $branch,
    ];

    if ($sha) {
      $payload['sha'] = $sha;
    }

    return $this->request('PUT', "/repos/$username/$repo/contents/$path", [
      'json' => $payload,
    ]);
  }

  /**
   * Create a pull request from the fork to the upstream repository.
   *
   * @param string $sourceOwner
   *   The owner of the source repository.
   * @param string $repo
   *   The repository name.
   * @param string $branch
   *   The branch name in the fork.
   * @param string $title
   *   The PR title.
   * @param string $body
   *   The PR description.
   * @param string $base
   *   The base branch (default: 'main').
   *
   * @return array|null
   *   The PR data or NULL on failure.
   */
  public function createPullRequest(
    string $sourceOwner,
    string $repo,
    string $branch,
    string $title,
    string $body = '',
    string $base = 'main'
  ): ?array {
    $username = $this->getGitHubUsername();
    if (!$username) {
      throw new \RuntimeException('GitHub username not available.');
    }

    return $this->request('POST', "/repos/$sourceOwner/$repo/pulls", [
      'json' => [
        'title' => $title,
        'body' => $body,
        'head' => "$username:$branch",
        'base' => $base,
      ],
    ]);
  }

}

// Made with Bob
