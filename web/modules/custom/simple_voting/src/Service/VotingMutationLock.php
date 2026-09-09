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

  /**
   * The lock key shared by configuration imports and domain mutations.
   */
  private const IMPORT_LOCK_KEY = 'simple_voting.config_import';

  public function __construct(private readonly LockBackendInterface $lock) {}

  /**
   * Acquires the global mutation gate.
   *
   * Configuration imports hold this gate for their complete import lifecycle;
   * votes and administrative mutations acquire it before their question lock.
   *
   * @throws \Drupal\simple_voting\Exception\VoteLockUnavailableException
   *   When the lock cannot be acquired.
   */
  public function acquireGlobal(): void {
    $this->acquireKey(self::IMPORT_LOCK_KEY);
  }

  /**
   * Releases the global mutation gate.
   */
  public function releaseGlobal(): void {
    $this->lock->release(self::IMPORT_LOCK_KEY);
  }

  /**
   * Acquires the mutation lock for a question.
   *
   * @throws \Drupal\simple_voting\Exception\VoteLockUnavailableException
   *   When the lock cannot be acquired.
   */
  public function acquire(string $questionId): void {
    $this->acquireKey($this->getKey($questionId));
  }

  /**
   * Releases the mutation lock for a question.
   */
  public function release(string $questionId): void {
    $this->lock->release($this->getKey($questionId));
  }

  /**
   * Acquires a deterministic lock key with the shared retry policy.
   */
  private function acquireKey(string $lockKey): void {
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
   * Returns a deterministic lock key without exposing the question ID.
   */
  private function getKey(string $questionId): string {
    return 'simple_voting.question_mutation.' . hash('sha256', $questionId);
  }

}
