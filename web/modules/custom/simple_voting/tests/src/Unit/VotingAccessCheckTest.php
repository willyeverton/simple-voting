<?php

namespace Drupal\Tests\simple_voting\Unit;

use Drupal\Core\Session\AccountInterface;
use Drupal\simple_voting\Access\VotingApiAccessCheck;
use Drupal\simple_voting\Access\VotingResultsAccessCheck;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Tests API authentication and CMS results access boundaries.
 */
final class VotingAccessCheckTest extends TestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $container = new ContainerBuilder();
    $container->set('cache_contexts_manager', new class {

      /**
       * Accepts the cache contexts used by the access checks.
       */
      public function assertValidTokens(array $tokens): bool {
        return TRUE;
      }

    });
    \Drupal::setContainer($container);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    \Drupal::unsetContainer();
    parent::tearDown();
  }

  /**
   * Anonymous API clients must be challenged before permission evaluation.
   */
  public function testAnonymousApiClientRequiresAuthentication(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAnonymous')->willReturn(TRUE);

    $this->expectException(UnauthorizedHttpException::class);
    (new VotingApiAccessCheck())->access($account);
  }

  /**
   * API access remains separate from the vote permission.
   */
  public function testAuthenticatedApiClientNeedsApiPermission(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAnonymous')->willReturn(FALSE);
    $account->method('hasPermission')
      ->with('access simple voting API')
      ->willReturn(FALSE);

    self::assertFalse((new VotingApiAccessCheck())->access($account)->isAllowed());
  }

  /**
   * The CMS results route accepts either voter or privileged result access.
   */
  public function testResultsAccessAllowsTheDedicatedResultsPermission(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturnMap([
      ['vote in polls', FALSE],
      ['view voting results', TRUE],
    ]);

    self::assertTrue((new VotingResultsAccessCheck())->access($account)->isAllowed());
  }

}
