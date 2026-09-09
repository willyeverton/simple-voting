<?php

namespace Drupal\simple_voting\Service;

use Drupal\Core\Lock\LockBackendInterface;
use Drupal\simple_voting\Exception\VoteLockUnavailableException;

/**
 * Serializes mutations that can change a question's option/vote relationship.
 */
final class VotingMutationLock {

  /**
   * The number of acquisition attempts for a domain lock.
   */
  private const ATTEMPTS = 3;

  /**
   * The lifetime of the lock while the mutation is in progress.
   */
  private const LOCK_LIFETIME = 30.0;

  /**
   * The maximum wait between acquisition attempts in seconds.
   */
  private const WAIT_SECONDS = 1;

  public function __construct(private readonly LockBackendInterface $lock) {}

  /**
   * Acquires the mutation lock for a question.
   *
   * @throws \Drupal\simple_voting\Exception\VoteLockUnavailableException
   *   When the lock cannot be acquired.
   */
  public function acquire(string $questionId): void {
    $lockKey = $this->getKey($questionId);
    for ($attempt = 0; $attempt < self::ATTEMPTS; $attempt++) {
      if ($this->lock->acquire($lockKey, self::LOCK_LIFETIME)) {
        return;
      }

      if ($attempt < self::ATTEMPTS - 1) {
        $this->lock->wait($lockKey, self::WAIT_SECONDS);
      }
    }

    throw new VoteLockUnavailableException();
  }

  /**
   * Releases the mutation lock for a question.
   */
  public function release(string $questionId): void {
    $this->lock->release($this->getKey($questionId));
  }

  /**
   * Returns a deterministic lock key without exposing the question ID.
   */
  private function getKey(string $questionId): string {
    return 'simple_voting.question_mutation.' . hash('sha256', $questionId);
  }

}
