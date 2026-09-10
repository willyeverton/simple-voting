<?php

namespace Drupal\simple_voting\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\simple_voting\Entity\VotingQuestionInterface;
use Drupal\simple_voting\Exception\QuestionNotFoundException;

/**
 * Reads question and option content entities.
 */
class QuestionReadService {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly OptionStorage $optionStorage,
  ) {}

  /**
   * Loads a question or returns NULL.
   */
  public function load(string $machineName): ?VotingQuestionInterface {
    $storage = $this->entityTypeManager->getStorage('voting_question');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('machine_name', $machineName)
      ->range(0, 1)
      ->execute();
    $question = $ids ? $storage->load(reset($ids)) : NULL;

    return $question instanceof VotingQuestionInterface ? $question : NULL;
  }

  /**
   * Loads a question by its internal persistence identifier.
   */
  public function loadById(int $questionId): ?VotingQuestionInterface {
    $question = $this->entityTypeManager->getStorage('voting_question')->load($questionId);
    return $question instanceof VotingQuestionInterface ? $question : NULL;
  }

  /**
   * Loads a question and throws a domain exception when it is missing.
   */
  public function requireQuestion(string $machineName): VotingQuestionInterface {
    $question = $this->load($machineName);
    if ($question === NULL) {
      throw new QuestionNotFoundException();
    }
    return $question;
  }

  /**
   * Returns questions for a catalogue or historical CMS listing.
   *
   * @return \Drupal\simple_voting\Entity\VotingQuestionInterface[]
   *   The matching voting questions.
   */
  public function getQuestions(bool $openOnly = FALSE): array {
    $query = $this->entityTypeManager
      ->getStorage('voting_question')
      ->getQuery()
      ->accessCheck(FALSE)
      ->sort('created', 'DESC');

    if ($openOnly) {
      $query->condition('status', TRUE);
    }

    $ids = $query->execute();
    if (!$ids) {
      return [];
    }

    return array_values(array_filter(
      $this->entityTypeManager->getStorage('voting_question')->loadMultiple($ids),
      static fn ($question): bool => $question instanceof VotingQuestionInterface,
    ));
  }

  /**
   * Returns options ordered by administrative weight.
   */
  public function options(int $questionId): array {
    return $this->optionStorage->getOptions($questionId);
  }

}
