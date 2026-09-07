<?php

namespace Drupal\simple_voting\Exception;

/**
 * Indicates that a user already voted on a question.
 */
final class DuplicateVoteException extends \RuntimeException {
}
