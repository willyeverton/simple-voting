<?php

namespace Drupal\simple_voting\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\simple_voting\Form\VoteForm;
use Drupal\simple_voting\Service\QuestionReadService;
use Drupal\simple_voting\Service\VoteStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * CMS pages for discovering questions and submitting votes.
 */
final class VotingPageController extends ControllerBase {

  public function __construct(
    private readonly ConfigFactoryInterface $votingConfigFactory,
    private readonly AccountProxyInterface $votingAccount,
    private readonly FormBuilderInterface $votingFormBuilder,
    private readonly QuestionReadService $questionRead,
    private readonly VoteStorage $voteStorage,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('current_user'),
      $container->get('form_builder'),
      $container->get('simple_voting.question_read'),
      $container->get('simple_voting.vote_storage'),
    );
  }

  /**
   * Lists open and closed questions for the CMS.
   */
  public function listQuestions(): array {
    $build = [
      '#cache' => [
        'tags' => ['simple_voting:question-list', 'voting_question_list', 'config:simple_voting.settings'],
        'contexts' => ['user', 'user.permissions'],
      ],
    ];

    if (!(bool) $this->votingConfigFactory->get('simple_voting.settings')->get('voting_enabled')) {
      $build['disabled'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Voting is temporarily disabled.'),
        '#attributes' => ['class' => ['messages', 'messages--warning']],
        '#weight' => -10,
      ];
      return $build;
    }

    $openOnly = !$this->votingAccount->hasPermission('administer simple voting');
    $questions = $this->questionRead->getQuestions($openOnly);
    $questionIds = array_map(static fn ($question): int => (int) $question->id(), $questions);
    $votedInternal = array_flip($this->voteStorage->getVotedQuestionIds(
      (int) $this->votingAccount->id(),
      $questionIds,
    ));
    $voted = [];
    foreach ($questions as $question) {
      if (isset($votedInternal[(int) $question->id()])) {
        $voted[$question->getMachineName()] = TRUE;
      }
    }

    if (!$questions) {
      $build['empty'] = ['#plain_text' => $this->t('No voting questions are available.')];
      return $build;
    }

    $items = [];
    foreach ($questions as $question) {
      $questionId = $question->getMachineName();
      $build['#cache']['tags'][] = 'voting_question:' . $question->id();
      $build['#cache']['tags'][] = 'simple_voting:question:' . $question->id();

      if (!$question->isOpen()) {
        if (($question->showsResults() && isset($voted[$questionId]))
          || $this->votingAccount->hasPermission('view voting results')) {
          $items[] = [
            '#type' => 'link',
            '#title' => $this->t('@title (closed)', ['@title' => $question->label()]),
            '#url' => Url::fromRoute('simple_voting.results', ['question_id' => $questionId]),
          ];
        }
        else {
          $items[] = ['#plain_text' => $this->t('@title (closed)', ['@title' => $question->label()])];
        }
        continue;
      }

      $route = isset($voted[$questionId]) && $question->showsResults()
        ? 'simple_voting.results'
        : 'simple_voting.vote';
      $title = $question->label();
      if (isset($voted[$questionId])) {
        $title = $this->t('@title (voted)', ['@title' => $title]);
      }
      $items[] = [
        '#type' => 'link',
        '#title' => $title,
        '#url' => Url::fromRoute($route, ['question_id' => $questionId]),
      ];
    }

    $build['questions'] = [
      '#theme' => 'item_list',
      '#items' => $items,
    ];
    return $build;
  }

  /**
   * Displays a vote form or a read-only state for one question.
   */
  public function votePage(string $question_id): array {
    if (!(bool) $this->votingConfigFactory->get('simple_voting.settings')->get('voting_enabled')) {
      return [
        '#plain_text' => $this->t('Voting is temporarily disabled.'),
        '#cache' => ['tags' => ['config:simple_voting.settings']],
      ];
    }

    $question = $this->questionRead->load($question_id);
    if ($question === NULL || (!$question->isOpen() && !$this->votingAccount->hasPermission('administer simple voting'))) {
      throw new NotFoundHttpException();
    }

    $votedOption = $this->voteStorage->getVotedOption((int) $question->id(), (int) $this->votingAccount->id());
    return $this->votingFormBuilder->getForm(
      VoteForm::class,
      $question_id,
      $votedOption !== NULL || !$question->isOpen(),
      $votedOption,
      $question->showsResults(),
    );
  }

  /**
   * Dynamic title for a question page.
   */
  public function title(string $question_id): TranslatableMarkup|string {
    if (!(bool) $this->votingConfigFactory->get('simple_voting.settings')->get('voting_enabled')) {
      return $this->t('Voting unavailable');
    }
    $question = $this->questionRead->load($question_id);
    if ($question === NULL) {
      return $this->t('Voting question');
    }
    if (!$question->isOpen() && !$this->votingAccount->hasPermission('administer simple voting')) {
      return $this->t('Voting question');
    }
    return $question->label();
  }

}
