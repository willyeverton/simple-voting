<?php

namespace Drupal\simple_voting\Exception;

/**
 * Indicates that an option cannot be removed because it has votes.
 */
final class OptionInUseException extends \RuntimeException {
}
