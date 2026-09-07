<?php

namespace Drupal\Tests\simple_voting\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\simple_voting\Entity\VotingQuestionInterface;
use Drupal\simple_voting\Exception\InvalidOptionException;
use Drupal\simple_voting\Exception\QuestionClosedException;
use Drupal\simple_voting\Exception\VotingDisabledException;
use Drupal\simple_voting\Service\OptionStorage;
use Drupal\simple_voting\Service\QuestionReadService;
use Drupal\simple_voting\Service\VoteStorage;
use Drupal\simple_voting\Service\VotingService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests vote write guards before any database mutation.
 *
 * @coversDefaultClass \Drupal\simple_voting\Service\VotingService
 */
final class VotingServiceGuardTest extends TestCase {

  /**
   * @covers ::castVote
   */
  public function testGlobalDisablePreventsPersistence(): void {
    $service = $this->createService(FALSE);
    $this->expectException(VotingDisabledException::class);
    $service->castVote('question', 1, 10);
  }

  /**
   * @covers ::castVote
   */
  public function testClosedQuestionPreventsPersistence(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('voting_enabled')->willReturn(TRUE);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('simple_voting.settings')->willReturn($config);

    $question = $this->createMock(VotingQuestionInterface::class);
    $question->method('isOpen')->willReturn(FALSE);
    $questionRead = $this->createMock(QuestionReadService::class);
    $questionRead->method('requireQuestion')->willReturn($question);

    $optionStorage = $this->createMock(OptionStorage::class);
    $optionStorage->expects(self::never())->method('getOption');
    $service = $this->createService(TRUE, $configFactory, $questionRead, $optionStorage);

    $this->expectException(QuestionClosedException::class);
    $service->castVote('question', 1, 10);
  }

  /**
   * @covers ::castVote
   */
  public function testOptionMustBelongToQuestion(): void {
    $question = $this->createMock(VotingQuestionInterface::class);
    $question->method('isOpen')->willReturn(TRUE);
    $questionRead = $this->createMock(QuestionReadService::class);
    $questionRead->method('requireQuestion')->willReturn($question);
    $optionStorage = $this->createMock(OptionStorage::class);
    $optionStorage->method('getOption')->willReturn(NULL);
    $service = $this->createService(TRUE, NULL, $questionRead, $optionStorage);

    $this->expectException(InvalidOptionException::class);
    $service->castVote('question', 999, 10);
  }

  /**
   * Creates a service with no database interaction configured.
   */
  private function createService(
    bool $enabled,
    ?ConfigFactoryInterface $configFactory = NULL,
    ?QuestionReadService $questionRead = NULL,
    ?OptionStorage $optionStorage = NULL,
  ): VotingService {
    if ($configFactory === NULL) {
      $config = $this->createMock(ImmutableConfig::class);
      $config->method('get')->with('voting_enabled')->willReturn($enabled);
      $configFactory = $this->createMock(ConfigFactoryInterface::class);
      $configFactory->method('get')->with('simple_voting.settings')->willReturn($config);
    }
    return new VotingService(
      $configFactory,
      $this->createMock(Connection::class),
      $questionRead ?? $this->createMock(QuestionReadService::class),
      $optionStorage ?? $this->createMock(OptionStorage::class),
      $this->createMock(VoteStorage::class),
      $this->createMock(LockBackendInterface::class),
      $this->createMock(TimeInterface::class),
      $this->createMock(CacheTagsInvalidatorInterface::class),
      $this->createMock(LoggerInterface::class),
    );
  }

}
