<?php

namespace Drupal\simple_voting\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for voting questions.
 */
interface VotingQuestionInterface extends ConfigEntityInterface {

  /**
   * Determines whether the question accepts votes.
   */
  public function isOpen(): bool;

  /**
   * Opens the question for voting.
   */
  public function open(): static;

  /**
   * Closes the question for voting.
   */
  public function close(): static;

  /**
   * Determines whether results are visible to eligible users.
   */
  public function showsResults(): bool;

  /**
   * Returns the creation timestamp.
   */
  public function getCreatedTime(): int;

  /**
   * Returns the changed timestamp.
   */
  public function getChangedTime(): int;

}
