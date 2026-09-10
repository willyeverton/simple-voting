<?php

namespace Drupal\Tests\simple_voting\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\simple_voting\Controller\VotingResultsController;
use Drupal\simple_voting\Entity\VotingQuestionInterface;
use Drupal\simple_voting\Service\QuestionReadService;
use Drupal\simple_voting\Service\VoteStorage;
use Drupal\simple_voting\Service\VotingAvailabilityService;
use Drupal\simple_voting\Service\VotingResultsService;
use Drupal\simple_voting\Service\VotingVisibilityService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests CMS result access observability.
 */
final class VotingResultsControllerTest extends TestCase {

  /**
   * Hidden results are logged without adding result data to the render array.
   */
  public function testDeniedCmsResultsAreLoggedWithoutExposingData(): void {
    $question = $this->createMock(VotingQuestionInterface::class);
    $question->method('id')->willReturn(42);
    $questionRead = $this->createMock(QuestionReadService::class);
    $questionRead->expects(self::once())
      ->method('load')
      ->with('hidden_question')
      ->willReturn($question);

    $visibility = new VotingVisibilityService($this->createMock(VoteStorage::class));
    $question->method('showsResults')->willReturn(FALSE);
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('hasPermission')->with('view voting results')->willReturn(FALSE);
    $results = new VotingResultsService(
      $this->createMock(Connection::class),
      $questionRead,
    );
    $account->method('id')->willReturn(10);
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())
      ->method('notice')
      ->with(
        'Voting results access denied.',
        [
          'uid' => 10,
          'question_id' => 'hidden_question',
          'endpoint' => 'cms_results',
        ],
      );

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('voting_enabled')->willReturn(TRUE);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('simple_voting.settings')->willReturn($config);

    $controller = new VotingResultsController(
      $questionRead,
      new VotingAvailabilityService($configFactory),
      $results,
      $visibility,
      $account,
      $logger,
    );
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')->willReturnCallback(
      static fn (string $string): string => $string,
    );
    $controller->setStringTranslation($translation);

    $build = $controller->results('hidden_question');

    self::assertArrayHasKey('message', $build);
    self::assertArrayNotHasKey('table', $build);
  }

}
