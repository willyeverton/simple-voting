<?php

namespace Drupal\simple_voting\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Enforces authentication before API permission checks are evaluated.
 */
final class VotingApiAccessCheck implements AccessInterface {

  /**
   * Checks authentication and API permission.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException
   *   When the request has no authenticated Drupal account.
   */
  public function access(AccountInterface $account): AccessResult {
    if ($account->isAnonymous()) {
      throw new UnauthorizedHttpException(
        'Basic',
        'Authentication is required.',
      );
    }

    return AccessResult::allowedIfHasPermission($account, 'access simple voting API')
      ->addCacheContexts(['user', 'user.permissions']);
  }

}
