<?php

namespace Drupal\Tests\simple_voting\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Transaction;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\simple_voting\Entity\VotingQuestionInterface;
use Drupal\simple_voting\Exception\InvalidOptionException;
use Drupal\simple_voting\Exception\PersistenceFailureException;
use Drupal\simple_voting\Exception\QuestionClosedException;
use Drupal\simple_voting\Exception\VotingDisabledException;
use Drupal\simple_voting\Service\OptionStorage;
use Drupal\simple_voting\Service\QuestionReadService;
use Drupal\simple_voting\Service\VoteStorage;
use Drupal\simple_voting\Service\VotingMutationLock;
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
  public function testLockIsReleasedWhenVotingIsDisabled(): void {
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects(self::once())->method('acquire')->willReturn(TRUE);
    $lock->expects(self::once())->method('release');

    $service = $this->createService(FALSE, lock: $lock);
    $this->expectException(VotingDisabledException::class);
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
   * An unexpected insert failure becomes a persistence failure.
   */
  public function testUnexpectedInsertFailureBecomesPersistenceFailure(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('voting_enabled')->willReturn(TRUE);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('simple_voting.settings')->willReturn($config);

    $question = $this->createMock(VotingQuestionInterface::class);
    $question->method('isOpen')->willReturn(TRUE);
    $questionRead = $this->createMock(QuestionReadService::class);
    $questionRead->method('requireQuestion')->willReturn($question);

    $optionStorage = $this->createMock(OptionStorage::class);
    $optionStorage->method('getOption')->willReturn(['id' => 1]);

    $voteStorage = new class() extends VoteStorage {

      /**
       * Number of existence checks performed by the service.
       *
       * @var int
       */
      public int $hasVoteCalls = 0;

      /**
       * Creates a storage double without initializing the database boundary.
       */
      public function __construct() {
      }

      /**
       * Tracks vote existence checks and reports no existing vote.
       */
      public function hasVote(string $questionId, int $uid): bool {
        $this->hasVoteCalls++;
        return FALSE;
      }

      /**
       * Simulates an unexpected failure during vote insertion.
       */
      public function insert(
        string $questionId,
        int $optionId,
        int $uid,
        int $timestamp,
      ): int {
        throw new \RuntimeException('database failure');
      }

    };

    $transaction = new class() extends Transaction {

      /**
       * Whether the service rolled back the transaction.
       *
       * @var bool
       */
      public bool $rolledBack = FALSE;

      /**
       * Creates a transaction double without an initialized connection.
       */
      public function __construct() {
      }

      /**
       * Records the rollback call without touching a database connection.
       */
      public function rollBack() {
        $this->rolledBack = TRUE;
      }

      /**
       * Prevents the parent destructor from accessing
       * an uninitialized connection.
       */
      public function __destruct() {
      }

    };
    $database = $this->createMock(Connection::class);
    $database->expects(self::once())
      ->method('startTransaction')
      ->willReturn($transaction);

    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);

    $service = new VotingService(
      $configFactory,
      $database,
      $questionRead,
      $optionStorage,
      $voteStorage,
      new VotingMutationLock($lock),
      $this->createMock(TimeInterface::class),
      $this->createMock(CacheTagsInvalidatorInterface::class),
      $this->createMock(LoggerInterface::class),
    );

    try {
      $service->castVote('question', 1, 10);
      self::fail('The insert failure should be wrapped as a persistence failure.');
    }
    catch (PersistenceFailureException) {
      self::assertTrue($transaction->rolledBack);
      self::assertSame(1, $voteStorage->hasVoteCalls);
    }
  }

  /**
   * Creates a service with no database interaction configured.
   */
  private function createService(
    bool $enabled,
    ?ConfigFactoryInterface $configFactory = NULL,
    ?QuestionReadService $questionRead = NULL,
    ?OptionStorage $optionStorage = NULL,
    ?LockBackendInterface $lock = NULL,
  ): VotingService {
    if ($configFactory === NULL) {
      $config = $this->createMock(ImmutableConfig::class);
      $config->method('get')->with('voting_enabled')->willReturn($enabled);
      $configFactory = $this->createMock(ConfigFactoryInterface::class);
      $configFactory->method('get')->with('simple_voting.settings')->willReturn($config);
    }
    if ($lock === NULL) {
      $lock = $this->createMock(LockBackendInterface::class);
      $lock->method('acquire')->willReturn(TRUE);
    }

    return new VotingService(
      $configFactory,
      $this->createMock(Connection::class),
      $questionRead ?? $this->createMock(QuestionReadService::class),
      $optionStorage ?? $this->createMock(OptionStorage::class),
      $this->createMock(VoteStorage::class),
      new VotingMutationLock($lock),
      $this->createMock(TimeInterface::class),
      $this->createMock(CacheTagsInvalidatorInterface::class),
      $this->createMock(LoggerInterface::class),
    );
  }

}
