<?php

namespace Drupal\Tests\simple_voting\Unit;

use Drupal\Core\Session\AccountInterface;
use Drupal\simple_voting\Entity\VotingQuestionInterface;
use Drupal\simple_voting\Service\VoteStorage;
use Drupal\simple_voting\Service\VotingVisibilityService;
use PHPUnit\Framework\TestCase;

/**
 * Tests result visibility rules independently of HTTP or database.
 *
 * @coversDefaultClass \Drupal\simple_voting\Service\VotingVisibilityService
 */
final class VotingVisibilityServiceTest extends TestCase {

  /**
   * @covers ::canViewResults
   */
  public function testCommonResultsRequireAnExistingVote(): void {
    $question = $this->question(TRUE, 42);
    $account = $this->account(10);
    $voteStorage = $this->createMock(VoteStorage::class);
    $voteStorage->expects(self::once())->method('hasVote')->with(42, 10)->willReturn(FALSE);

    self::assertFalse((new VotingVisibilityService($voteStorage))->canViewResults($question, $account));
  }

  /**
   * @covers ::canViewResults
   */
  public function testCommonResultsAreVisibleAfterVoting(): void {
    $question = $this->question(TRUE, 42);
    $account = $this->account(10);
    $voteStorage = $this->createMock(VoteStorage::class);
    $voteStorage->expects(self::once())->method('hasVote')->with(42, 10)->willReturn(TRUE);

    self::assertTrue((new VotingVisibilityService($voteStorage))->canViewResults($question, $account));
  }

  /**
   * @covers ::canViewResults
   */
  public function testAnonymousAccountCannotViewCommonResults(): void {
    $question = $this->question(TRUE, 42);
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturn(FALSE);
    $account->method('isAnonymous')->willReturn(TRUE);
    $voteStorage = $this->createMock(VoteStorage::class);
    $voteStorage->expects(self::never())->method('hasVote');

    self::assertFalse((new VotingVisibilityService($voteStorage))->canViewResults($question, $account));
  }

  /**
   * @covers ::canViewResults
   */
  public function testPermissionCanViewHiddenResultsWithoutVote(): void {
    $question = $this->question(FALSE, 42);
    $account = $this->account(10, TRUE);
    $voteStorage = $this->createMock(VoteStorage::class);
    $voteStorage->expects(self::never())->method('hasVote');

    self::assertTrue((new VotingVisibilityService($voteStorage))->canViewResults($question, $account));
  }

  /**
   * Creates a mocked voting question.
   *
   * @param bool $showResults
   *   Whether results are visible.
   * @param int $id
   *   The question ID.
   *
   * @return \Drupal\simple_voting\Entity\VotingQuestionInterface
   *   The mocked question.
   */
  private function question(bool $showResults, int $id): VotingQuestionInterface {
    $question = $this->createMock(VotingQuestionInterface::class);
    $question->method('showsResults')->willReturn($showResults);
    $question->method('id')->willReturn($id);
    return $question;
  }

  /**
   * Creates a mocked user account.
   *
   * @param int $id
   *   The account ID.
   * @param bool $canViewHidden
   *   Whether the account can view hidden results.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   The mocked account.
   */
  private function account(int $id, bool $canViewHidden = FALSE): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->with('view voting results')->willReturn($canViewHidden);
    $account->method('isAnonymous')->willReturn(FALSE);
    $account->method('id')->willReturn($id);
    return $account;
  }

}
