<?php

namespace Drupal\simple_voting\Service;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\simple_voting\Entity\VotingQuestionInterface;
use Drupal\simple_voting\Exception\PersistenceFailureException;
use Drupal\simple_voting\Exception\QuestionHasVotesException;
use Drupal\simple_voting\Exception\VoteLockUnavailableException;
use Psr\Log\LoggerInterface;

/**
 * Applies the safe deletion policy for questions.
 */
final class QuestionDeletionService {

  public function __construct(
    private readonly Connection $database,
    private readonly OptionStorage $optionStorage,
    private readonly VoteStorage $voteStorage,
    private readonly VotingMutationLock $mutationLock,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Deletes a question only when it has no transactional votes.
   */
  public function delete(VotingQuestionInterface $question): void {
    $questionId = (int) $question->id();
    $globalLockAcquired = FALSE;
    try {
      $this->mutationLock->acquireGlobal();
      $globalLockAcquired = TRUE;
      $this->mutationLock->acquire((string) $questionId);
    }
    catch (VoteLockUnavailableException $exception) {
      $this->logger->warning('Question mutation lock unavailable.', [
        'question_id' => $questionId,
        'operation' => 'delete_question',
        'exception_class' => $exception::class,
      ]);
      if ($globalLockAcquired) {
        $this->mutationLock->releaseGlobal();
      }
      throw $exception;
    }

    $transaction = NULL;
    try {
      $transaction = $this->database->startTransaction();
      if ($this->voteStorage->hasVotesForQuestion($questionId)) {
        throw new QuestionHasVotesException();
      }

      $this->optionStorage->deleteForQuestion($questionId, TRUE, TRUE);
      $question->delete();
      unset($transaction);
    }
    catch (QuestionHasVotesException $exception) {
      if ($transaction !== NULL) {
        $transaction->rollBack();
      }
      throw $exception;
    }
    catch (VoteLockUnavailableException $exception) {
      if ($transaction !== NULL) {
        $transaction->rollBack();
      }
      throw $exception;
    }
    catch (\Throwable $exception) {
      if ($transaction !== NULL) {
        $transaction->rollBack();
      }
      $this->logger->error('Question deletion failed.', [
        'question_id' => $questionId,
        'operation' => 'delete_question',
        'exception_class' => $exception::class,
      ]);
      throw new PersistenceFailureException(previous: $exception);
    }
    finally {
      try {
        $this->mutationLock->release((string) $questionId);
      }
      finally {
        $this->mutationLock->releaseGlobal();
      }
    }

    $this->cacheTagsInvalidator->invalidateTags([
      'voting_question:' . $questionId,
      'simple_voting:question:' . $questionId,
      'simple_voting:question-list',
      'voting_question_list',
    ]);
  }

}
