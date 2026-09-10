<?php

namespace Drupal\simple_voting\Service;

use Drupal\Core\Session\AccountInterface;
use Drupal\simple_voting\Entity\VotingQuestionInterface;

/**
 * Applies the visibility policy for question results.
 */
final class VotingVisibilityService {

  public function __construct(private readonly VoteStorage $voteStorage) {}

  /**
   * Determines whether an account may view results for a question.
   */
  public function canViewResults(VotingQuestionInterface $question, AccountInterface $account): bool {
    return $account->hasPermission('view voting results')
      || ($question->showsResults()
        && !$account->isAnonymous()
        && $this->voteStorage->hasVote((int) $question->id(), (int) $account->id()));
  }

}
