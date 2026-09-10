<?php

namespace Drupal\Tests\simple_voting\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\simple_voting\Exception\QuestionNotFoundException;
use Drupal\simple_voting\Form\VoteForm;
use Drupal\simple_voting\Service\QuestionReadService;
use Drupal\simple_voting\Service\VotingService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests stale vote form submissions.
 */
final class VoteFormTest extends TestCase {

  /**
   * A deleted question produces a safe message and catalogue redirect.
   */
  public function testDeletedQuestionProducesSafeMessageAndRedirect(): void {
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects(self::once())
      ->method('addError')
      ->with(self::callback(
        static fn ($message): bool => $message instanceof TranslatableMarkup
          && $message->getUntranslatedString() === 'This question is no longer available.',
      ))
      ->willReturn($messenger);
    $container = new ContainerBuilder();
    $container->set('messenger', $messenger);
    \Drupal::setContainer($container);

    $votingService = $this->createMock(VotingService::class);
    $votingService->expects(self::once())
      ->method('castVote')
      ->willThrowException(new QuestionNotFoundException());
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('id')->willReturn(10);

    $form = new VoteForm(
      $this->createMock(ConfigFactoryInterface::class),
      $this->createMock(QuestionReadService::class),
      $votingService,
      $currentUser,
      $this->createMock(EntityTypeManagerInterface::class),
    );
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')->willReturnCallback(
      static fn (string $string): string => $string,
    );
    $form->setStringTranslation($translation);
    $form_state = new FormState();
    $form_state->setValue('question_id', 'deleted_question');
    $form_state->setValue('option_id', 1);
    $form_build = [];

    $form->submitForm($form_build, $form_state);

    self::assertSame('simple_voting.questions', $form_state->getRedirect()->getRouteName());
    \Drupal::unsetContainer();
  }

  /**
   * Resets the global Drupal container after each test.
   */
  protected function tearDown(): void {
    if (\Drupal::hasContainer()) {
      \Drupal::unsetContainer();
    }
    parent::tearDown();
  }

}
