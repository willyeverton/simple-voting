<?php

namespace Drupal\simple_voting\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Allows the results page to be used by voters or privileged result viewers.
 */
final class VotingResultsAccessCheck implements AccessInterface {

  /**
   * Checks the permissions needed to reach the results controller.
   */
  public function access(AccountInterface $account): AccessResult {
    if ($account->isAnonymous()) {
      return AccessResult::forbidden()->addCacheContexts(['user', 'user.permissions']);
    }

    $allowed = $account->hasPermission('vote in polls')
      || $account->hasPermission('view voting results');

    return AccessResult::allowedIf($allowed)
      ->addCacheContexts(['user', 'user.permissions']);
  }

}
