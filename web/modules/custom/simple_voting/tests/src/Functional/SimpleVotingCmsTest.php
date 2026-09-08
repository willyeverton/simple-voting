<?php

namespace Drupal\Tests\simple_voting\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Verifies the CMS voting catalogue respects global availability.
 *
 * @group simple_voting
 */
final class SimpleVotingCmsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['simple_voting'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The public questions link belongs to the main menu and protected route.
   */
  public function testPublicQuestionsMenuLinkUsesProtectedRoute(): void {
    $definition = \Drupal::service('plugin.manager.menu.link')
      ->getDefinition('simple_voting.public_questions');

    self::assertSame('main', $definition['menu_name']);
    self::assertSame('simple_voting.questions', $definition['route_name']);
  }

  /**
   * The disabled catalogue does not expose question links.
   */
  public function testDisabledVotingHidesQuestionList(): void {
    $account = $this->drupalCreateUser(['vote in polls']);
    $this->drupalLogin($account);

    $question = \Drupal::entityTypeManager()
      ->getStorage('voting_question')
      ->create([
        'id' => 'visible_question',
        'title' => 'Visible question',
        'status' => TRUE,
      ]);
    $question->save();

    \Drupal::configFactory()
      ->getEditable('simple_voting.settings')
      ->set('voting_enabled', FALSE)
      ->save();

    $this->drupalGet('/voting');

    $this->assertSession()->pageTextContains(
      'Voting is temporarily disabled. Existing authorized results '
      . 'remain available.',
    );
    $this->assertSession()->linkNotExists('Visible question');
  }

}
