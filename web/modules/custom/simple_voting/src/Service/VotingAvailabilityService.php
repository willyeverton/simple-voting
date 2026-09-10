<?php

namespace Drupal\simple_voting\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\simple_voting\Exception\VotingDisabledException;

/**
 * Centralizes the global voting availability policy.
 */
final class VotingAvailabilityService {

  public function __construct(private readonly ConfigFactoryInterface $configFactory) {}

  /**
   * Determines whether the voting feature is globally available.
   */
  public function isAvailable(): bool {
    return (bool) $this->configFactory->get('simple_voting.settings')->get('voting_enabled');
  }

  /**
   * Rejects access while voting is globally disabled.
   */
  public function assertAvailable(): void {
    if (!$this->isAvailable()) {
      throw new VotingDisabledException();
    }
  }

}
