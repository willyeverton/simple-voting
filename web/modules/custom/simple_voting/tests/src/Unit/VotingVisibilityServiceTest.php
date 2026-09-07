<?php

namespace Drupal\Tests\simple_voting\Unit;

use Drupal\Core\Session\AccountInterface;
use Drupal\simple_voting\Entity\VotingQuestionInterface;
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
  public function testPublicResultsAreVisibleWithoutElevatedPermission(): void {
    $question = $this->createMock(VotingQuestionInterface::class);
    $question->method('showsResults')->willReturn(TRUE);
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->with('view voting results')->willReturn(FALSE);

    self::assertTrue((new VotingVisibilityService())->canViewResults($question, $account));
  }

  /**
   * @covers ::canViewResults
   */
  public function testHiddenResultsRequirePermission(): void {
    $question = $this->createMock(VotingQuestionInterface::class);
    $question->method('showsResults')->willReturn(FALSE);
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->with('view voting results')->willReturn(FALSE);

    self::assertFalse((new VotingVisibilityService())->canViewResults($question, $account));
  }

  /**
   * @covers ::canViewResults
   */
  public function testPermissionCanViewHiddenResults(): void {
    $question = $this->createMock(VotingQuestionInterface::class);
    $question->method('showsResults')->willReturn(FALSE);
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->with('view voting results')->willReturn(TRUE);

    self::assertTrue((new VotingVisibilityService())->canViewResults($question, $account));
  }

}
