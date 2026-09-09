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
   *
   * @param \Drupal\simple_voting\Entity\VotingQuestionInterface $question
   *   The question entity to save.
   * @param array<int, array<string, mixed>> $submittedOptions
   *   The normalized form values for the question's options.
   *
   * @return int
   *   The entity save status.
   *
   * @throws \Drupal\simple_voting\Exception\InvalidOptionException
   * @throws \Drupal\simple_voting\Exception\OptionInUseException
   * @throws \Drupal\simple_voting\Exception\PersistenceFailureException
   */
  public function save(VotingQuestionInterface $question, array $submittedOptions): int {
    $questionId = (string) $question->id();
    try {
      $this->mutationLock->acquire($questionId);
    }
    catch (VoteLockUnavailableException $exception) {
      $this->logger->warning('Question mutation lock unavailable.', [
        'question_id' => $questionId,
        'operation' => 'save_question',
        'exception_class' => $exception::class,
      ]);
      throw $exception;
    }

    $transaction = $this->database->startTransaction();
    try {
      $status = $question->save();
      $this->optionStorage->sync($questionId, $submittedOptions, TRUE, FALSE);
      unset($transaction);
    }
    catch (InvalidOptionException | OptionInUseException $exception) {
      $transaction->rollBack();
      throw $exception;
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      $this->logger->error('Question persistence failed.', [
        'question_id' => $questionId,
        'operation' => 'save_question',
        'exception_class' => $exception::class,
      ]);
      throw new PersistenceFailureException(previous: $exception);
    }
    finally {
      $this->mutationLock->release($questionId);
    }

    $this->cacheTagsInvalidator->invalidateTags([
      'config:simple_voting.question.' . $questionId,
      'simple_voting:question:' . $questionId,
      'simple_voting:question-list',
      'config:voting_question_list',
    ]);

    return $status;
  }

}
