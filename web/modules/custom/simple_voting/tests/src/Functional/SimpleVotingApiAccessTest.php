<?php

namespace Drupal\Tests\simple_voting\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Verifies the API does not expose data to anonymous clients.
 *
 * @group simple_voting
 */
final class SimpleVotingApiAccessTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['simple_voting'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Anonymous clients must authenticate before receiving business data.
   */
  public function testAnonymousApiAccessIsRejected(): void {
    $this->drupalGet('/api/v1/questions');
    self::assertSame(401, $this->getSession()->getStatusCode());
  }

}
