<?php declare(strict_types=1);

namespace Drupal\mof\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Subscribe to exception events and return JSON responses for API errors.
 */
class ApiExceptionSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [KernelEvents::EXCEPTION => 'onException'];
  }

  /**
   * Handles the exception event.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The exception event.
   */
  public function onException(ExceptionEvent $event) {
    $request = $event->getRequest();

    // Only handle exceptions for API hits.
    if (strpos($request->getPathInfo(), '/api') === 0) {
      $exception = $event->getThrowable();

      $json = [
        'error' => [
          'code' => $exception->getStatusCode(),
          'message' => $exception->getMessage(),
        ]];

      $response = new JsonResponse($json, $exception->getStatusCode());
      $event->setResponse($response);
    }
  }

}

