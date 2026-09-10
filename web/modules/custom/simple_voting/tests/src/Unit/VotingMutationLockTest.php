<?php

namespace Drupal\Tests\simple_voting\Unit;

use Drupal\Core\Lock\LockBackendInterface;
use Drupal\simple_voting\Exception\VoteLockUnavailableException;
use Drupal\simple_voting\Service\VotingMutationLock;
use PHPUnit\Framework\TestCase;

/**
 * Tests the shared question mutation lock policy.
 *
 * @coversDefaultClass \Drupal\simple_voting\Service\VotingMutationLock
 */
final class VotingMutationLockTest extends TestCase {

  /**
   * @covers ::acquire
   * @covers ::release
   */
  public function testAcquireRetriesAndReleasesTheDeterministicLock(): void {
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects(self::exactly(2))
      ->method('acquire')
      ->with(
        self::stringStartsWith('simple_voting.question_mutation.'),
        300.0,
      )
      ->willReturnOnConsecutiveCalls(FALSE, TRUE);
    $lock->expects(self::once())
      ->method('wait')
      ->with(self::stringStartsWith('simple_voting.question_mutation.'), 1);
    $lock->expects(self::once())
      ->method('release')
      ->with(self::stringStartsWith('simple_voting.question_mutation.'));

    $mutationLock = new VotingMutationLock($lock);
    $mutationLock->acquire('favorite_color');
    $mutationLock->release('favorite_color');
  }

  /**
   * @covers ::acquireGlobal
   * @covers ::releaseGlobal
   */
  public function testGlobalGateUsesDedicatedConfigurationImportKey(): void {
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects(self::once())
      ->method('acquire')
      ->with('simple_voting.config_import', 300.0)
      ->willReturn(TRUE);
    $lock->expects(self::once())
      ->method('release')
      ->with('simple_voting.config_import');

    $mutationLock = new VotingMutationLock($lock);
    $mutationLock->acquireGlobal();
    $mutationLock->releaseGlobal();
  }

  /**
   * Renewing a held question lock extends its lease.
   *
   * @covers ::renew
   */
  public function testRenewReacquiresTheHeldQuestionLock(): void {
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects(self::once())
      ->method('acquire')
      ->with(self::stringStartsWith('simple_voting.question_mutation.'), 300.0)
      ->willReturn(TRUE);

    (new VotingMutationLock($lock))->renew('favorite_color');
  }

  /**
   * @covers ::acquire
   */
  public function testUnavailableLockProducesDomainFailure(): void {
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects(self::exactly(3))
      ->method('acquire')
      ->with(
        self::stringStartsWith('simple_voting.question_mutation.'),
        300.0,
      )
      ->willReturn(FALSE);
    $lock->expects(self::exactly(2))
      ->method('wait')
      ->with(self::stringStartsWith('simple_voting.question_mutation.'), 1);

    $this->expectException(VoteLockUnavailableException::class);
    (new VotingMutationLock($lock))->acquire('favorite_color');
  }

}
