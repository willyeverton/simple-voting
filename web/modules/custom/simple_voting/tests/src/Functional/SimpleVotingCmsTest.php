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
  protected static $modules = ['block', 'simple_voting'];

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
  public function testHiddenResultsPermissionCanReachCmsResults(): void {
    $account = $this->drupalCreateUser(['view voting results']);
    $this->drupalLogin($account);

    $question = \Drupal::entityTypeManager()
      ->getStorage('voting_question')
      ->create([
        'machine_name' => 'hidden_question',
        'title' => 'Hidden question',
        'status' => FALSE,
        'show_results' => FALSE,
      ]);
    $question->save();

    $this->drupalGet('/voting/hidden_question/results');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('No votes have been registered.');
  }

  /**
   * An authenticated voter can submit one vote through the CMS form.
   */
  public function testAuthenticatedVoterCanSubmitVote(): void {
    $account = $this->drupalCreateUser(['vote in polls']);
    $this->drupalLogin($account);

    $question = \Drupal::entityTypeManager()
      ->getStorage('voting_question')
      ->create([
        'machine_name' => 'cms_vote_question',
        'title' => 'CMS vote question',
        'status' => TRUE,
        'show_results' => TRUE,
      ]);
    $question->save();
    $option = \Drupal::entityTypeManager()
      ->getStorage('voting_option')
      ->create([
        'question_id' => $question->id(),
        'title' => 'First option',
        'description' => '',
        'weight' => 0,
      ]);
    $option->save();
    $optionId = (int) $option->id();

    $this->drupalGet('/voting/cms_vote_question/results');
    $this->assertSession()->pageTextContains('Results are not available for this question.');

    $this->drupalGet('/voting/cms_vote_question');
    $this->submitForm(['option_id' => (string) $optionId], 'Register vote');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Total votes: 1');
    $this->assertSession()->pageTextContains('First option');
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
        'machine_name' => 'visible_question',
        'title' => 'Visible question',
        'status' => TRUE,
      ]);
    $question->save();

    \Drupal::configFactory()
      ->getEditable('simple_voting.settings')
      ->set('voting_enabled', FALSE)
      ->save();

    $this->drupalGet('/voting');

    $this->assertSession()->pageTextContains('Voting is temporarily disabled.');
    $this->assertSession()->linkNotExists('Visible question');

    foreach (['/voting/visible_question', '/voting/visible_question/results'] as $path) {
      $this->drupalGet($path);
      $this->assertSession()->pageTextContains('Voting is temporarily disabled.');
    }
  }

}
