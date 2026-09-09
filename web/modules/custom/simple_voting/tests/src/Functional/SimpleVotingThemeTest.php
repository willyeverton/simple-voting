<?php

namespace Drupal\Tests\simple_voting\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Verifies the global Simple Voting theme shell.
 *
 * @group simple_voting
 */
final class SimpleVotingThemeTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'simple_voting'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'simple_voting_theme';

  /**
   * Anonymous visitors receive the account menu login link.
   */
  public function testAnonymousHomeOffersLogin(): void {
    $this->drupalGet('<front>');

    $this->assertSession()->linkExists('Log in');
    $this->assertSession()->linkNotExists('Voting questions');

    $this->drupalGet('/voting');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Authenticated voters receive account and public navigation links.
   */
  public function testAuthenticatedNavigation(): void {
    $account = $this->drupalCreateUser(['vote in polls']);
    $this->drupalLogin($account);
    $this->drupalGet('<front>');

    $this->assertSession()->linkExists('Log out');
    $this->assertSession()->linkExists('Voting questions');
  }

  /**
   * Repeated theme updates preserve the original frontend theme for rollback.
   */
  public function testThemeUpdateStoresPreviousFrontendThemeOnce(): void {
    $theme_config = \Drupal::configFactory()->getEditable('system.theme');
    $theme_config->set('default', 'stark')->save();

    \simple_voting_update_11004();
    self::assertSame('stark', \Drupal::state()->get('simple_voting.previous_default_theme'));

    \simple_voting_update_11004();
    self::assertSame('stark', \Drupal::state()->get('simple_voting.previous_default_theme'));
  }

  /**
   * The theme update preserves the configured administrative theme.
   */
  public function testThemeUpdatePreservesAdminTheme(): void {
    $theme_config = \Drupal::configFactory()->getEditable('system.theme');
    $admin_theme = $theme_config->get('admin');

    \simple_voting_update_11004();

    $theme_config = \Drupal::config('system.theme');
    self::assertSame('simple_voting_theme', $theme_config->get('default'));
    self::assertSame($admin_theme, $theme_config->get('admin'));
  }

}
