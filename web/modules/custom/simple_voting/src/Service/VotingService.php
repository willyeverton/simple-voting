<?php

namespace Drupal\simple_voting\Service;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\simple_voting\Exception\DuplicateVoteException;
use Drupal\simple_voting\Exception\InvalidOptionException;
use Drupal\simple_voting\Exception\PersistenceFailureException;
use Drupal\simple_voting\Exception\QuestionClosedException;
use Drupal\simple_voting\Exception\VoteLockUnavailableException;
use Drupal\simple_voting\Exception\VotingDisabledException;
use Psr\Log\LoggerInterface;

/**
 * Coordinates vote validation and durable persistence.
 */
class VotingService {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly Connection $database,
    private readonly QuestionReadService $questionRead,
    private readonly OptionStorage $optionStorage,
    private readonly VoteStorage $voteStorage,
    private readonly LockBackendInterface $lock,
    private readonly TimeInterface $time,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Registers one vote for an authenticated user.
   *
   * @return array{question_id: string, option_id: int, uid: int}
   *   The stable identifiers of the persisted vote.
   */
  public function castVote(string $questionId, int $optionId, int $uid): array {
    if ($uid <= 0) {
      throw new \InvalidArgumentException('A vote requires an authenticated user.');
    }

    if (!(bool) $this->configFactory->get('simple_voting.settings')->get('voting_enabled')) {
      $this->logger->notice('Vote blocked because global voting is disabled.', [
        'uid' => $uid,
        'question_id' => $questionId,
      ]);
      throw new VotingDisabledException();
    }

    $question = $this->questionRead->requireQuestion($questionId);
    if (!$question->isOpen()) {
      $this->logger->notice('Vote blocked because the question is closed.', [
        'uid' => $uid,
        'question_id' => $questionId,
      ]);
      throw new QuestionClosedException();
    }

    if ($this->optionStorage->getOption($questionId, $optionId) === NULL) {
      $this->logger->warning('Vote blocked because the option does not belong to the question.', [
        'uid' => $uid,
        'question_id' => $questionId,
        'option_id' => $optionId,
      ]);
      throw new InvalidOptionException();
    }

    $lockKey = 'simple_voting.vote.' . hash('sha256', $questionId . ':' . $uid);
    $acquired = FALSE;
    $attempts = 2;
    $timeout = 1.0;
    for ($attempt = 0; $attempt < max(1, $attempts); $attempt++) {
      if ($this->lock->acquire($lockKey, $timeout)) {
        $acquired = TRUE;
        break;
      }
    }

    if (!$acquired) {
      $this->logger->warning('Vote lock unavailable.', [
        'uid' => $uid,
        'question_id' => $questionId,
      ]);
      throw new VoteLockUnavailableException();
    }

    try {
      $transaction = $this->database->startTransaction();
      try {
        if ($this->voteStorage->hasVote($questionId, $uid)) {
          throw new DuplicateVoteException();
        }

        $this->voteStorage->insert(
          $questionId,
          $optionId,
          $uid,
          $this->time->getRequestTime(),
        );
      }
      catch (DuplicateVoteException $exception) {
        $transaction->rollBack();
        $this->logger->notice('Duplicate vote rejected.', [
          'uid' => $uid,
          'question_id' => $questionId,
        ]);
        throw $exception;
      }
      catch (IntegrityConstraintViolationException $exception) {
        $transaction->rollBack();
        $this->logger->notice('Vote uniqueness constraint rejected a duplicate.', [
          'uid' => $uid,
          'question_id' => $questionId,
        ]);
        throw new DuplicateVoteException(previous: $exception);
      }
      catch (\Throwable $exception) {
        $transaction->rollBack();
        $this->logger->error('Unexpected vote persistence failure.', [
          'uid' => $uid,
          'question_id' => $questionId,
          'exception' => $exception,
        ]);
        throw new PersistenceFailureException(previous: $exception);
      }

      $this->cacheTagsInvalidator->invalidateTags([
        'config:simple_voting.question.' . $questionId,
        'simple_voting:question:' . $questionId,
        'simple_voting:question-list',
      ]);

      return [
        'question_id' => $questionId,
        'option_id' => $optionId,
        'uid' => $uid,
      ];
    }
    finally {
      $this->lock->release($lockKey);
    }
  }

}
