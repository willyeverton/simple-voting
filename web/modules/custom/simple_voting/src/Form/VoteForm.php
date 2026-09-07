<?php

namespace Drupal\simple_voting\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\simple_voting\Exception\DuplicateVoteException;
use Drupal\simple_voting\Exception\QuestionClosedException;
use Drupal\simple_voting\Exception\VoteLockUnavailableException;
use Drupal\simple_voting\Exception\VotingDisabledException;
use Drupal\simple_voting\Service\QuestionReadService;
use Drupal\simple_voting\Service\VotingService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders and submits one question's vote form.
 */
final class VoteForm extends FormBase {

  public function __construct(
    protected readonly ConfigFactoryInterface $votingConfigFactory,
    protected readonly QuestionReadService $questionRead,
    protected readonly VotingService $votingService,
    protected readonly AccountProxyInterface $currentUser,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('simple_voting.question_read'),
      $container->get('simple_voting.voting'),
      $container->get('current_user'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'simple_voting_vote_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    ?string $question_id = NULL,
    bool $locked = FALSE,
    ?int $voted_option_id = NULL,
    bool $show_results = FALSE,
  ): array {
    if ($question_id === NULL || $this->currentUser->isAnonymous()) {
      return $form;
    }

    $question = $this->questionRead->load($question_id);
    if ($question === NULL) {
      $form['notice'] = [
        '#plain_text' => $this->t('The requested question is not available.'),
      ];
      return $form;
    }

    $options = $this->questionRead->options($question_id);
    if (!$options) {
      $form['notice'] = [
        '#plain_text' => $this->t('This question has no answer options.'),
      ];
      return $form;
    }

    $globalEnabled = (bool) $this->votingConfigFactory->get('simple_voting.settings')->get('voting_enabled');
    $isClosed = !$question->isOpen();
    $locked = $locked || !$globalEnabled || $isClosed;

    $form['question_id'] = [
      '#type' => 'hidden',
      '#value' => $question_id,
    ];
    $form['option_id'] = [
      '#type' => 'radios',
      '#title' => $question->label(),
      '#options' => array_combine(
        array_map(static fn (array $option): int => (int) $option['id'], $options),
        array_map(static fn (array $option): string => (string) $option['title'], $options),
      ),
      '#required' => !$locked,
      '#disabled' => $locked,
      '#default_value' => $voted_option_id,
    ];

    foreach ($options as $option) {
      $description = [];
      if (!empty($option['image_fid'])) {
        $file = $this->entityTypeManager->getStorage('file')->load((int) $option['image_fid']);
        if ($file !== NULL) {
          $description['image'] = [
            '#theme' => 'image',
            '#uri' => $file->getFileUri(),
            '#alt' => (string) $option['title'],
            '#attributes' => ['loading' => 'lazy'],
          ];
        }
      }
      if ((string) ($option['description'] ?? '') !== '') {
        $description['text'] = [
          '#plain_text' => (string) $option['description'],
        ];
      }
      if ($description) {
        $form['option_id'][(int) $option['id']]['#description'] = $description;
      }
    }

    if ($locked) {
      $message = $isClosed
        ? $this->t('This question is closed.')
        : ($voted_option_id !== NULL
          ? $this->t('You have already voted on this question.')
          : $this->t('Voting is temporarily disabled.'));
      $form['notice'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $message,
        '#attributes' => ['class' => ['messages', 'messages--warning']],
        '#weight' => 10,
      ];
      if ($voted_option_id !== NULL && $show_results) {
        $form['results_link'] = [
          '#type' => 'link',
          '#title' => $this->t('View results'),
          '#url' => Url::fromRoute('simple_voting.results', ['question_id' => $question_id]),
          '#weight' => 12,
        ];
      }
    }
    else {
      $form['actions'] = [
        '#type' => 'actions',
        'submit' => [
          '#type' => 'submit',
          '#value' => $this->t('Register vote'),
        ],
      ];
    }

    $form['#cache'] = [
      'tags' => [
        'config:simple_voting.settings',
        'config:simple_voting.question.' . $question_id,
        'simple_voting:question:' . $question_id,
      ],
      'contexts' => ['user', 'user.permissions'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (!$form_state->getValue('option_id')) {
      $form_state->setErrorByName('option_id', $this->t('Select one option before registering your vote.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $questionId = (string) $form_state->getValue('question_id');
    $optionId = (int) $form_state->getValue('option_id');
    try {
      $this->votingService->castVote($questionId, $optionId, (int) $this->currentUser->id());
    }
    catch (DuplicateVoteException) {
      $this->messenger()->addWarning($this->t('You have already voted on this question.'));
      return;
    }
    catch (VoteLockUnavailableException) {
      $this->messenger()->addError($this->t('The vote could not be registered now. Please try again shortly.'));
      return;
    }
    catch (QuestionClosedException | VotingDisabledException) {
      $this->messenger()->addWarning($this->t('This question is not accepting votes.'));
      return;
    }

    $question = $this->questionRead->requireQuestion($questionId);
    $form_state->setRedirect(
      $question->showsResults() ? 'simple_voting.results' : 'simple_voting.vote',
      ['question_id' => $questionId],
    );
  }

}
