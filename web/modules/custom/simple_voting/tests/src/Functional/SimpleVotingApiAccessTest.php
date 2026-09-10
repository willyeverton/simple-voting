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
  protected static $modules = ['block', 'simple_voting'];

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

  /**
   * Anonymous clients cannot enumerate known questions or results.
   */
  public function testAnonymousReadEndpointsRequireAuthentication(): void {
    foreach (['/api/v1/questions/example_question', '/api/v1/questions/example_question/results'] as $path) {
      $this->drupalGet($path);
      self::assertSame(401, $this->getSession()->getStatusCode(), $path);
    }
  }

  /**
   * A disabled global vote switch blocks every API read endpoint.
   */
  public function testDisabledVotingBlocksEveryApiReadEndpoint(): void {
    $account = $this->drupalCreateUser(['access simple voting API']);
    $this->drupalLogin($account);

    $question = \Drupal::entityTypeManager()
      ->getStorage('voting_question')
      ->create([
        'machine_name' => 'api_visible_question',
        'title' => 'API visible question',
        'status' => TRUE,
      ]);
    $question->save();

    \Drupal::configFactory()
      ->getEditable('simple_voting.settings')
      ->set('voting_enabled', FALSE)
      ->save();

    foreach ([
      '/api/v1/questions',
      '/api/v1/questions/api_visible_question',
      '/api/v1/questions/api_visible_question/results',
    ] as $path) {
      $this->drupalGet($path);
      self::assertSame(503, $this->getSession()->getStatusCode(), $path);
    }
  }

}
