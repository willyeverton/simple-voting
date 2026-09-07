<?php

namespace Drupal\simple_voting\Service;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\simple_voting\Entity\VotingQuestionInterface;
use Drupal\simple_voting\Exception\QuestionHasVotesException;

/**
 * Applies the safe deletion policy for questions.
 */
final class QuestionDeletionService {

  public function __construct(
    private readonly OptionStorage $optionStorage,
    private readonly VoteStorage $voteStorage,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
  ) {}

  /**
   * Deletes a question only when it has no transactional votes.
   */
  public function delete(VotingQuestionInterface $question): void {
    $questionId = (string) $question->id();
    if ($this->voteStorage->hasVotesForQuestion($questionId)) {
      throw new QuestionHasVotesException();
    }

    $this->optionStorage->deleteForQuestion($questionId);
    $question->delete();
    $this->cacheTagsInvalidator->invalidateTags([
      'config:simple_voting.question.' . $questionId,
      'simple_voting:question:' . $questionId,
      'simple_voting:question-list',
    ]);
  }

}
