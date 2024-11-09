<?php declare(strict_types=1);

namespace Drupal\mof;

use Drupal\Core\Cache\CacheBackendInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Middleware to rate-limit API requests by IP address.
 */
final class RateLimitMiddleware implements HttpKernelInterface {

  private const LIMIT = 10000;   // 10,000 requests.
  private const INTERVAL = 3600; // per 1 hour.

  /**
   * Constructor.
   *
   * @param \Symfony\Component\HttpFoundation\HttpKernelInterface $http
   *   The main HTTP kernel interface.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend for storing request counts.
   */
  public function __construct(
    private readonly HttpKernelInterface $http,
    private readonly CacheBackendInterface $cache
  ) {}

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = TRUE): Response {
    if (!$this->isApiRequest($request)) {
      return $this->http->handle($request, $type, $catch);
    }
    return $this->rateLimit($request) ?: $this->http->handle($request, $type, $catch);
  }

  /**
   * Checks if the request path is for an API endpoint.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   *
   * @return bool
   *   TRUE if the request path begins with "/api", FALSE otherwise.
   */
  private function isApiRequest(Request $request): bool {
    return strpos($request->getPathInfo(), '/api') === 0;
  }

  /**
   * Applies rate limiting based on client IP address.
   *
   * Checks the number of requests made by the IP in the last interval.
   * If the count exceeds the limit, returns a JSON error response.
   * Otherwise, increments the request count and stores it in the cache.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse|null
   *   A JSON response with an error if the rate limit is exceeded, or NULL if allowed.
   */
  private function rateLimit(Request $request): ?JsonResponse {
    $cache_key = 'rate_limit:' . $request->getClientIp();
    $request_count = $this->cache->get($cache_key)->data ?? 1;

    if ($request_count > self::LIMIT) {
      return new JsonResponse(['error' => 'Rate limit exceeded. Try again later.'], Response::HTTP_TOO_MANY_REQUESTS);
    }

    $this->cache->set($cache_key, $request_count + 1, time() + self::INTERVAL);
    return NULL;
  }

}

