<?php

namespace Drupal\simple_voting\Service;

use Drupal\Core\Session\AccountInterface;
use Drupal\simple_voting\Entity\VotingQuestionInterface;

/**
 * Applies the visibility policy for question results.
 */
final class VotingVisibilityService {

  /**
   * Determines whether an account may view results for a question.
   */
  public function canViewResults(VotingQuestionInterface $question, AccountInterface $account): bool {
    return $question->showsResults() || $account->hasPermission('view voting results');
  }

}
