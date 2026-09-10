<?php

namespace Drupal\simple_voting\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface for voting options.
 */
interface VotingOptionInterface extends ContentEntityInterface {

  /**
   * Returns the owning question ID.
   */
  public function getQuestionId(): int;

  /**
   * Returns the administrative sort weight.
   */
  public function getWeight(): int;

}
