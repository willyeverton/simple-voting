<?php

namespace Drupal\simple_voting\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\simple_voting\Form\VoteForm;
use Drupal\simple_voting\Service\QuestionReadService;
use Drupal\simple_voting\Service\VotingResultsService;
use Drupal\simple_voting\Service\VotingVisibilityService;
use Drupal\simple_voting\Service\VoteStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configurable block that embeds one question's voting form.
 *
 * @Block(
 *   id = "simple_voting_voting_block",
 *   admin_label = @Translation("Simple Voting: question form"),
 *   category = @Translation("Simple Voting")
 * )
 */
final class VotingBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly AccountProxyInterface $currentUser,
    protected readonly FormBuilderInterface $formBuilder,
    protected readonly QuestionReadService $questionRead,
    protected readonly VoteStorage $voteStorage,
    protected readonly VotingResultsService $results,
    protected readonly VotingVisibilityService $visibility,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('current_user'),
      $container->get('form_builder'),
      $container->get('simple_voting.question_read'),
      $container->get('simple_voting.vote_storage'),
      $container->get('simple_voting.results'),
      $container->get('simple_voting.visibility'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['question_id' => ''] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $questions = $this->questionRead->getQuestions();
    $options = ['' => $this->t('- Select a question -')];
    foreach ($questions as $question) {
      $options[$question->id()] = $question->label();
    }
    $form['question_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Question'),
      '#options' => $options,
      '#default_value' => $this->configuration['question_id'] ?? '',
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['question_id'] = $form_state->getValue('question_id');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $questionId = (string) ($this->configuration['question_id'] ?? '');
    if ($questionId === '') {
      return [];
    }

    if (!$this->currentUser->hasPermission('vote in polls')) {
      return ['#cache' => ['contexts' => ['user.permissions']]];
    }

    $question = $this->questionRead->load($questionId);
    if ($question === NULL) {
      return ['#plain_text' => $this->t('The selected question is no longer available.')];
    }

    $votedOption = $this->voteStorage->getVotedOption($questionId, (int) $this->currentUser->id());
    $enabled = (bool) $this->configFactory->get('simple_voting.settings')->get('voting_enabled');
    $locked = !$question->isOpen() || !$enabled || $votedOption !== NULL;

    if ($locked && $this->visibility->canViewResults($question, $this->currentUser)) {
      return $this->buildResults($questionId);
    }

    if ($locked) {
      return [
        'message' => [
          '#plain_text' => $votedOption !== NULL
            ? $this->t('Your vote was recorded.')
            : $this->t('This question is not accepting votes.'),
        ],
        '#cache' => $this->cacheMetadata($questionId),
      ];
    }

    $build = $this->formBuilder->getForm(VoteForm::class, $questionId, FALSE, NULL, $question->showsResults());
    $build['#cache'] = $this->cacheMetadata($questionId);
    return $build;
  }

  /**
   * Returns the shared results read model as a render array.
   */
  private function buildResults(string $questionId): array {
    $result = $this->results->getResults($questionId);
    $data = $result['data'];
    return [
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
      'total' => [
        '#plain_text' => $this->t('Total votes: @total', ['@total' => $data['total_votes']]),
      ],
      '#cache' => [
        'tags' => $result['cache']->getCacheTags(),
        'contexts' => array_unique(array_merge(
          $result['cache']->getCacheContexts(),
          ['user', 'user.permissions'],
        )),
        'max-age' => $result['cache']->getCacheMaxAge(),
      ],
    ];
  }

  /**
   * Returns block-level cache metadata.
   */
  private function cacheMetadata(string $questionId): array {
    return [
      'tags' => [
        'config:simple_voting.settings',
        'config:simple_voting.question.' . $questionId,
        'simple_voting:question:' . $questionId,
      ],
      'contexts' => ['user', 'user.permissions'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return array_unique(array_merge(parent::getCacheContexts(), ['user', 'user.permissions']));
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    $tags = parent::getCacheTags();
    if (!empty($this->configuration['question_id'])) {
      $questionId = (string) $this->configuration['question_id'];
      $tags[] = 'config:simple_voting.settings';
      $tags[] = 'config:simple_voting.question.' . $questionId;
      $tags[] = 'simple_voting:question:' . $questionId;
    }
    return $tags;
  }

}
