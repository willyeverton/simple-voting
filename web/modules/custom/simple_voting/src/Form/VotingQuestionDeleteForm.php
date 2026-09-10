<?php

namespace Drupal\simple_voting\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\simple_voting\Exception\QuestionHasVotesException;
use Drupal\simple_voting\Exception\PersistenceFailureException;
use Drupal\simple_voting\Exception\VoteLockUnavailableException;
use Drupal\simple_voting\Service\QuestionDeletionService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirms safe deletion of a voting question.
 */
final class VotingQuestionDeleteForm extends ContentEntityConfirmFormBase {

  public function __construct(
    EntityRepositoryInterface $entityRepository,
    EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    TimeInterface $time,
    protected readonly QuestionDeletionService $deletionService,
  ) {
    parent::__construct($entityRepository, $entityTypeBundleInfo, $time);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('simple_voting.question_deletion'),
    );
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
    catch (QuestionHasVotesException) {
      $this->messenger()->addError($this->t('Questions with votes must be closed or archived instead of deleted.'));
      $form_state->setRedirectUrl($this->getCancelUrl());
      return;
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
