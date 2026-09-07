<?php

namespace Drupal\simple_voting\Service;

use Drupal\Core\Database\Connection;

/**
 * Persistence boundary for immutable voting records.
 */
class VoteStorage {

  public function __construct(private readonly Connection $database) {}

  /**
   * Determines whether a user already voted on a question.
   */
  public function hasVote(string $questionId, int $uid): bool {
    return (bool) $this->database->select('simple_voting_vote', 'v')
      ->fields('v', ['id'])
      ->condition('question_id', $questionId)
      ->condition('uid', $uid)
      ->range(0, 1)
      ->execute()
      ->fetchField();
  }

  /**
   * Returns the option selected by a user, or NULL when not voted.
   */
  public function getVotedOption(string $questionId, int $uid): ?int {
    $optionId = $this->database->select('simple_voting_vote', 'v')
      ->fields('v', ['option_id'])
      ->condition('question_id', $questionId)
      ->condition('uid', $uid)
      ->range(0, 1)
      ->execute()
      ->fetchField();

    return $optionId === FALSE ? NULL : (int) $optionId;
  }

  /**
   * Returns all question IDs voted by a user from a known set.
   */
  public function getVotedQuestionIds(int $uid, array $questionIds): array {
    if ($uid <= 0 || !$questionIds) {
      return [];
    }

    return array_map(
      'strval',
      $this->database->select('simple_voting_vote', 'v')
        ->fields('v', ['question_id'])
        ->condition('uid', $uid)
        ->condition('question_id', $questionIds, 'IN')
        ->execute()
        ->fetchCol(),
    );
  }

  /**
   * Inserts an immutable vote record.
   */
  public function insert(string $questionId, int $optionId, int $uid, int $timestamp): int {
    return (int) $this->database->insert('simple_voting_vote')
      ->fields([
        'question_id' => $questionId,
        'option_id' => $optionId,
        'uid' => $uid,
        'timestamp' => $timestamp,
      ])
      ->execute();
  }

  /**
   * Returns whether a question has any votes.
   */
  public function hasVotesForQuestion(string $questionId): bool {
    return (bool) $this->database->select('simple_voting_vote', 'v')
      ->fields('v', ['id'])
      ->condition('question_id', $questionId)
      ->range(0, 1)
      ->execute()
      ->fetchField();
  }

  /**
   * Returns whether an option has any votes.
   */
  public function hasVotesForOption(int $optionId): bool {
    return (bool) $this->database->select('simple_voting_vote', 'v')
      ->fields('v', ['id'])
      ->condition('option_id', $optionId)
      ->range(0, 1)
      ->execute()
      ->fetchField();
  }

}
