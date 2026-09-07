<?php

namespace Drupal\simple_voting\Exception;

/**
 * Indicates that the per-user question lock could not be acquired.
 */
final class VoteLockUnavailableException extends \RuntimeException {
}
