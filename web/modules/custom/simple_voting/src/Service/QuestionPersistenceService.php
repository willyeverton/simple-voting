<?php

namespace Drupal\simple_voting\Service;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\simple_voting\Entity\VotingQuestionInterface;
use Drupal\simple_voting\Exception\InvalidOptionException;
use Drupal\simple_voting\Exception\OptionInUseException;
use Drupal\simple_voting\Exception\PersistenceFailureException;
use Drupal\simple_voting\Exception\VoteLockUnavailableException;
use Psr\Log\LoggerInterface;

/**
 * Persists a question and its owned options as one database operation.
 */
final class QuestionPersistenceService {

  public function __construct(
    private readonly Connection $database,
    private readonly OptionStorage $optionStorage,
    private readonly VotingMutationLock $mutationLock,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Saves the question and synchronizes its options.
   */
  public function save(VotingQuestionInterface $question, array $submittedOptions): int {
    $isNew = $question->isNew();
    $questionId = $isNew ? NULL : (int) $question->id();
    $globalLockAcquired = FALSE;
    $questionLockAcquired = FALSE;
    $transaction = NULL;

    try {
      $this->mutationLock->acquireGlobal();
      $globalLockAcquired = TRUE;
      if ($questionId !== NULL) {
        $this->mutationLock->acquire((string) $questionId);
        $questionLockAcquired = TRUE;
      }

      if ($questionId !== NULL) {
        $this->optionStorage->assertCanSync($questionId, $submittedOptions);
      }
      $transaction = $this->database->startTransaction();
      if (!$isNew) {
        $this->optionStorage->sync($questionId, $submittedOptions, TRUE, FALSE, TRUE);
      }
      $status = $question->save();
      $questionId = (int) $question->id();
      if (!$questionLockAcquired) {
        $this->mutationLock->acquire((string) $questionId);
        $questionLockAcquired = TRUE;
      }
      if ($isNew) {
        $this->optionStorage->sync($questionId, $submittedOptions, TRUE, FALSE, TRUE);
      }
      unset($transaction);
    }
    catch (InvalidOptionException | OptionInUseException | VoteLockUnavailableException $exception) {
      if ($transaction !== NULL) {
        $transaction->rollBack();
      }
      throw $exception;
    }
    catch (\Throwable $exception) {
      if ($transaction !== NULL) {
        $transaction->rollBack();
      }
      $this->logger->error('Question persistence failed with @exception.', [
        '@exception' => $exception::class,
        'question_id' => $questionId,
        'operation' => 'save_question',
      ]);
      throw new PersistenceFailureException(previous: $exception);
    }
    finally {
      if ($questionLockAcquired) {
        $this->mutationLock->release((string) $questionId);
      }
      if ($globalLockAcquired) {
        $this->mutationLock->releaseGlobal();
      }
    }

    $this->cacheTagsInvalidator->invalidateTags([
      'voting_question:' . $questionId,
      'simple_voting:question:' . $questionId,
      'simple_voting:question-list',
      'voting_question_list',
    ]);

    return $status;
  }

}
