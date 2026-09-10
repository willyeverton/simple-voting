<?php

namespace Drupal\simple_voting\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface for voting questions.
 */
interface VotingQuestionInterface extends ContentEntityInterface, EntityChangedInterface {

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
   * Returns the stable public machine name.
   */
  public function getMachineName(): string;

  /**
   * Returns the creation timestamp.
   */
  public function getCreatedTime(): int;

}
