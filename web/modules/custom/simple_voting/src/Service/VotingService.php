<?php

namespace Drupal\simple_voting\Service;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\simple_voting\Exception\DuplicateVoteException;
use Drupal\simple_voting\Exception\InvalidOptionException;
use Drupal\simple_voting\Exception\PersistenceFailureException;
use Drupal\simple_voting\Exception\QuestionClosedException;
use Psr\Log\LoggerInterface;

/**
 * Coordinates vote validation and durable persistence.
 */
class VotingService {

  public function __construct(
    private readonly VotingAvailabilityService $availability,
    private readonly Connection $database,
    private readonly QuestionReadService $questionRead,
    private readonly OptionStorage $optionStorage,
    private readonly VoteStorage $voteStorage,
    private readonly TimeInterface $time,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Registers one vote for an authenticated user.
   *
   * @return array{question_id: int, option_id: int, uid: int}
   *   The stable identifiers of the persisted vote.
   */
  public function castVote(string $machineName, int $optionId, int $uid): array {
    if ($uid <= 0) {
      throw new \InvalidArgumentException('A vote requires an authenticated user.');
    }

    $this->availability->assertAvailable();
    $question = $this->questionRead->requireQuestion($machineName);
    $questionId = (int) $question->id();
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

    $transaction = $this->database->startTransaction();
    try {
      $this->voteStorage->insert(
        $questionId,
        $optionId,
        $uid,
        $this->time->getRequestTime(),
      );
    }
    catch (IntegrityConstraintViolationException $exception) {
      $transaction->rollBack();
      if ($this->voteStorage->hasVote($questionId, $uid)) {
        $this->logger->notice('Vote uniqueness constraint rejected a duplicate.', [
          'uid' => $uid,
          'question_id' => $questionId,
        ]);
        throw new DuplicateVoteException(previous: $exception);
      }

      $this->logger->error('Vote persistence constraint failed.', [
        'uid' => $uid,
        'question_id' => $questionId,
        'exception_class' => $exception::class,
      ]);
      throw new PersistenceFailureException(previous: $exception);
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      $this->logger->error('Unexpected vote persistence failure.', [
        'uid' => $uid,
        'question_id' => $questionId,
        'exception_class' => $exception::class,
      ]);
      throw new PersistenceFailureException(previous: $exception);
    }

    unset($transaction);
    $this->cacheTagsInvalidator->invalidateTags([
      'voting_question:' . $questionId,
      'simple_voting:question:' . $questionId,
      'simple_voting:question-list',
      'voting_question_list',
    ]);

    return [
      'question_id' => $questionId,
      'option_id' => $optionId,
      'uid' => $uid,
    ];
  }

}
