<?php

namespace Drupal\simple_voting\EventSubscriber;

use Drupal\simple_voting\Exception\DuplicateVoteException;
use Drupal\simple_voting\Exception\InvalidOptionException;
use Drupal\simple_voting\Exception\PersistenceFailureException;
use Drupal\simple_voting\Exception\QuestionClosedException;
use Drupal\simple_voting\Exception\QuestionNotFoundException;
use Drupal\simple_voting\Exception\ResultsAccessDeniedException;
use Drupal\simple_voting\Exception\VoteLockUnavailableException;
use Drupal\simple_voting\Exception\VotingDisabledException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Converts domain/API failures to stable JSON only for API routes.
 */
final class VotingApiExceptionSubscriber implements EventSubscriberInterface {

  public function __construct(private readonly LoggerInterface $logger) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::EXCEPTION => 'onException'];
  }

  /**
   * Handles API exceptions without affecting HTML routes.
   */
  public function onException(ExceptionEvent $event): void {
    $request = $event->getRequest();
    $route = (string) $request->attributes->get('_route');
    if (!str_starts_with($route, 'simple_voting.api.')) {
      return;
    }

    $exception = $event->getThrowable();
    [$status, $code, $message] = $this->mapException($exception);
    $headers = [];
    if ($exception instanceof VoteLockUnavailableException) {
      $headers['Retry-After'] = '2';
    }

    if ($status >= 500) {
      $this->logger->error('Unhandled Simple Voting API exception.', [
        'route' => $route,
        'status' => $status,
        'exception' => $exception,
      ]);
    }
    else {
      $this->logger->notice('Simple Voting API request rejected.', [
        'route' => $route,
        'status' => $status,
        'code' => $code,
      ]);
    }

    $event->setResponse(new JsonResponse([
      'error' => $message,
      'code' => $code,
    ], $status, $headers));
  }

  /**
   * Maps known failures to the public error catalog.
   *
   * @return array{0: int, 1: string, 2: string}
   *   The status code, public error code, and safe message.
   */
  private function mapException(\Throwable $exception): array {
    return match (TRUE) {
      $exception instanceof UnauthorizedHttpException => [
        401,
        'AUTHENTICATION_REQUIRED',
        'Authentication is required.',
      ],
      $exception instanceof AccessDeniedHttpException,
      $exception instanceof ResultsAccessDeniedException => [
        403,
        'ACCESS_DENIED',
        'Access is not permitted.',
      ],
      $exception instanceof NotFoundHttpException,
      $exception instanceof QuestionNotFoundException => [
        404,
        'QUESTION_NOT_FOUND',
        'Question not found.',
      ],
      $exception instanceof DuplicateVoteException => [
        409,
        'DUPLICATE_VOTE',
        'You have already voted on this question.',
      ],
      $exception instanceof BadRequestHttpException => [
        400,
        'INVALID_PAYLOAD',
        'The request data is invalid.',
      ],
      $exception instanceof InvalidOptionException => [
        422,
        'OPTION_NOT_FOUND',
        'The option is invalid for this question.',
      ],
      $exception instanceof QuestionClosedException => [
        422,
        'QUESTION_CLOSED',
        'This question is not open for voting.',
      ],
      $exception instanceof VotingDisabledException => [
        503,
        'VOTING_DISABLED',
        'Voting is temporarily unavailable.',
      ],
      $exception instanceof VoteLockUnavailableException => [
        503,
        'LOCK_UNAVAILABLE',
        'Please try again shortly.',
      ],
      $exception instanceof PersistenceFailureException => [
        500,
        'PERSISTENCE_FAILURE',
        'The operation could not be completed.',
      ],
      $exception instanceof HttpExceptionInterface => $this->mapHttpStatus($exception->getStatusCode()),
      default => [
        500,
        'INTERNAL_ERROR',
        'An internal error occurred.',
      ],
    };
  }

  /**
   * Maps an unclassified HTTP exception without exposing its message.
   *
   * @return array{0: int, 1: string, 2: string}
   *   The status code, public error code, and safe message.
   */
  private function mapHttpStatus(int $status): array {
    return match (TRUE) {
      $status === 400 => [400, 'INVALID_PAYLOAD', 'The request data is invalid.'],
      $status === 401 => [401, 'AUTHENTICATION_REQUIRED', 'Authentication is required.'],
      $status === 403 => [403, 'ACCESS_DENIED', 'Access is not permitted.'],
      $status === 404 => [404, 'QUESTION_NOT_FOUND', 'Question not found.'],
      default => [
        $status >= 500 ? 500 : 400,
        $status >= 500 ? 'INTERNAL_ERROR' : 'INVALID_PAYLOAD',
        $status >= 500 ? 'An internal error occurred.' : 'The request data is invalid.',
      ],
    };
  }

}
