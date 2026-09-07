<?php

namespace Drupal\simple_voting\Exception;

/**
 * Indicates that a question cannot be removed because it has votes.
 */
final class QuestionHasVotesException extends \RuntimeException {
}
