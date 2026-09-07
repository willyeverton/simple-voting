<?php

namespace Drupal\Tests\simple_voting\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Documents the percentage precision used by the results read model.
 *
 * @group simple_voting
 */
final class VotingResultsPercentageTest extends TestCase {

  /**
   * Confirms the documented one-decimal rounding rule.
   */
  public function testPercentageRoundsToOneDecimal(): void {
    self::assertSame(33.3, round((1 / 3) * 100, 1));
  }

}
