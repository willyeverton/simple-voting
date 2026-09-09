<?php

namespace Drupal\Tests\simple_voting\Unit;

use Drupal\simple_voting\EventSubscriber\VotingApiExceptionSubscriber;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Tests safe API exception responses and operational correlation context.
 */
final class VotingApiExceptionSubscriberTest extends TestCase {

  /**
   * API errors stay generic and preserve a bounded request correlation ID.
   */
  public function testApiExceptionIsMappedWithoutLoggingTheExceptionObject(): void {
    $request = Request::create('/api/v1/questions');
    $request->attributes->set('_route', 'simple_voting.api.questions');
    $request->headers->set('X-Request-ID', 'request-123');

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())
      ->method('notice')
      ->with(
        'Simple Voting API request rejected.',
        self::callback(static function (array $context): bool {
          return $context['code'] === 'INVALID_PAYLOAD'
            && $context['correlation_id'] === 'request-123'
            && $context['exception_class'] === BadRequestHttpException::class;
        }),
      );

    $event = new ExceptionEvent(
      $this->createMock(HttpKernelInterface::class),
      $request,
      1,
      new BadRequestHttpException(),
    );

    (new VotingApiExceptionSubscriber($logger))->onException($event);

    $response = $event->getResponse();
    self::assertInstanceOf(JsonResponse::class, $response);
    $payload = json_decode($response->getContent() ?: '', TRUE);
    self::assertSame(400, $response->getStatusCode());
    self::assertSame('request-123', $response->headers->get('X-Request-ID'));
    self::assertSame([
      'error' => 'The request data is invalid.',
      'code' => 'INVALID_PAYLOAD',
    ], $payload);
  }

}
