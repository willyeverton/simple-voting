<?php

namespace Drupal\simple_voting\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\simple_voting\Service\QuestionReadService;
use Drupal\simple_voting\Service\VotingAvailabilityService;
use Drupal\simple_voting\Service\VotingResultsService;
use Drupal\simple_voting\Service\VotingVisibilityService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * CMS presentation for the shared results read model.
 */
final class VotingResultsController extends ControllerBase {

  public function __construct(
    private readonly QuestionReadService $questionRead,
    private readonly VotingAvailabilityService $availability,
    private readonly VotingResultsService $results,
    private readonly VotingVisibilityService $visibility,
    private readonly AccountProxyInterface $votingAccount,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('simple_voting.question_read'),
      $container->get('simple_voting.availability'),
      $container->get('simple_voting.results'),
      $container->get('simple_voting.visibility'),
      $container->get('current_user'),
      $container->get('logger.channel.simple_voting'),
    );
  }

  /**
   * Displays authorized results or a no-data confirmation.
   */
  public function results(string $question_id): array {
    if (!$this->availability->isAvailable()) {
      return [
        '#plain_text' => $this->t('Voting is temporarily disabled.'),
        '#cache' => ['tags' => ['config:simple_voting.settings']],
      ];
    }
    $question = $this->questionRead->load($question_id);
    if ($question === NULL) {
      throw new NotFoundHttpException();
    }

    if (!$this->visibility->canViewResults($question, $this->votingAccount)) {
      $this->logger->notice('Voting results access denied.', [
        'uid' => (int) $this->votingAccount->id(),
        'question_id' => $question_id,
        'endpoint' => 'cms_results',
      ]);
      return [
        'message' => [
          '#plain_text' => $this->t('Results are not available for this question.'),
        ],
        '#cache' => [
          'tags' => ['voting_question:' . $question->id(), 'simple_voting:question:' . $question->id()],
          'contexts' => ['user', 'user.permissions'],
        ],
      ];
    }

    $result = $this->results->getResults((int) $question->id());
    $data = $result['data'];
    $build = [
      'title' => ['#plain_text' => (string) $data['question_title']],
      'total' => ['#plain_text' => $this->t('Total votes: @total', ['@total' => $data['total_votes']])],
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Option'), $this->t('Votes'), $this->t('Percentage')],
        '#rows' => array_map(static fn (array $option): array => [
          $option['title'],
          $option['votes'],
          $option['percentage'] . '%',
        ], $data['options']),
        '#empty' => $this->t('No votes have been registered.'),
      ],
      '#cache' => [
        'tags' => $result['cache']->getCacheTags(),
        'contexts' => $result['cache']->getCacheContexts(),
        'max-age' => $result['cache']->getCacheMaxAge(),
      ],
    ];
    return $build;
  }

  /**
   * Dynamic title for a results page.
   */
  public function title(string $question_id): TranslatableMarkup|string {
    if (!$this->availability->isAvailable()) {
      return $this->t('Voting unavailable');
    }
    $question = $this->questionRead->load($question_id);
    return $question ? $question->label() : $this->t('Voting results');
  }

}
