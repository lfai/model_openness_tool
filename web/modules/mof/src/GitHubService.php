<?php

declare(strict_types=1);

namespace Drupal\mof;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Url;
use Drupal\social_auth\Entity\SocialAuth;

class GitHubService {

  const GITHUB_API_URL = 'https://api.github.com';

  protected ?string $token = NULL;

  protected ?SocialAuth $auth;

  protected readonly AccountProxyInterface $account;

  protected readonly LoggerChannel $logger;

  protected readonly SqlEntityStorageInterface $storage;

  /**
   * Construct the GitHubService.
   */
  public function __construct(
    protected Client $httpClient,
    AccountProxyInterface $account,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger
  ) {
    $storage = $entity_type_manager->getStorage('social_auth');

    $social_auth = $storage->loadByProperties(['user_id' => $account->id()]);
    if (!empty($social_auth) && $social_auth = reset($social_auth)) {
      $this->auth = $social_auth;
      $this->token = $social_auth->getToken();
    }

    $this->account = $account;
    $this->storage = $storage;
    $this->logger = $logger->get('mof');
  }

  /**
   * Get a list of repositories available to the authenticated user.
   */
  public function getRepoList(): array {
    $response = $this->execute('/user/repos');

    if (!$response) {
      return [];
    }

    $repos = [];
    foreach (json_decode($response->getBody()->getContents()) as $repo) {
       $repos[$repo->full_name] = $repo->full_name;
    }

    return $repos;
  }

  /**
   * Get the specified repository details.
   *
   * @todo Implement a simple repository interface/ class to replace stdClass.
   */
  public function getRepo(string $repo_name): \stdClass {
    $repo = $this->execute('/repos/' . $repo_name)->getBody()->getContents();
    return json_decode($repo);
  }

  /**
   * Build a tree of file paths.
   *
   * @todo Consider implementing StreamedJsonResponse for larger datasets(?)
   * @todo Implement handling if truncated is true in the response.
   */
  public function getTree(string $repo_name, string $branch): \stdClass {
    $endpoint = '/repos/' . $repo_name . '/git/trees/' . $branch;
    $tree = $this->execute($endpoint, ['recursive' => 1])->getBody()->getContents();
    return json_decode($tree);
  }

  /**
   * Check if we have an authentication token.
   */
  public function isAuthenticated(): bool {
    return $this->token !== NULL;
  }

  /**
   * Execute a github api call.
   */
  final protected function execute(
    string $endpoint,
    ?array $query = NULL,
    ?array $headers = NULL): ?Response {

    try {
      $headers ??= $this->setDefaultHeaders();

      $response = $this
        ->httpClient
        ->get(static::GITHUB_API_URL . $endpoint, [
          'headers' => $headers,
          'query' => $query !== NULL ? http_build_query($query) : NULL,
        ]);

      return $response->getStatusCode() === 200 ? $response : NULL;
    }
    catch (ClientException $e) {
      $this->logger->notice($e->getMessage());
      if ($e->getCode() === 401) {
        // Try to reauthorize the user.
        $this->auth->delete();
        $this->storage->resetCache([$this->account->id()]);
        $url = Url::fromRoute('social_auth.network.redirect', ['network' => 'github']);
        $redirect = new RedirectResponse($url->toString());
        $redirect->send();
      }
    }
    catch (RuntimeException) {
      $this->logger->notice($e->getMessage());
    }

    return NULL;
  }

  /**
   * Return the default headers.
   */
  final protected function setDefaultHeaders(): array {
    return [
      'Authorization' => 'Bearer ' . $this->token,
      'X-GitHub-Api-Version' => '2022-11-28',
      'Accept' => 'application/vnd.github+json',
    ];
  }

}

