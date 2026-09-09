<?php

namespace Drupal\simple_voting\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\simple_voting\Exception\QuestionHasVotesException;
use Drupal\simple_voting\Exception\PersistenceFailureException;
use Drupal\simple_voting\Exception\VoteLockUnavailableException;
use Drupal\simple_voting\Service\QuestionDeletionService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Confirms safe deletion of a voting question.
 */
final class VotingQuestionDeleteForm extends EntityConfirmFormBase {

  public function __construct(protected readonly QuestionDeletionService $deletionService) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('simple_voting.question_deletion'));
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Are you sure you want to delete the question %label?', [
      '%label' => $this->getEntity()->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->getEntity()->toUrl('collection');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\simple_voting\Entity\VotingQuestionInterface $question */
    $question = $this->getEntity();
    try {
      $this->deletionService->delete($question);
    }
    catch (QuestionHasVotesException $exception) {
      throw new UnprocessableEntityHttpException(
        'Questions with votes must be closed or archived instead of deleted.',
        $exception,
      );
    }
    catch (VoteLockUnavailableException) {
      $this->messenger()->addError($this->t('The question could not be deleted now. Please try again shortly.'));
      return;
    }
    catch (PersistenceFailureException) {
      $this->messenger()->addError($this->t('The question could not be deleted. Please try again.'));
      return;
    }
    $this->messenger()->addStatus($this->t('Question %label was deleted.', [
      '%label' => $question->label(),
    ]));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
