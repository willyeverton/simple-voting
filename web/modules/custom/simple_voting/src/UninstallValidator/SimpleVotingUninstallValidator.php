<?php

namespace Drupal\simple_voting\UninstallValidator;

use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleUninstallValidatorInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Prevents accidental deletion of Simple Voting runtime data.
 */
final class SimpleVotingUninstallValidator implements ModuleUninstallValidatorInterface {

  use StringTranslationTrait;

  public function __construct(
    private readonly Connection $database,
    private readonly StateInterface $state,
    TranslationInterface $stringTranslation,
  ) {
    $this->setStringTranslation($stringTranslation);
  }

  /**
   * {@inheritdoc}
   */
  public function validate($module): array {
    if ($module !== 'simple_voting'
      || $this->state->get('simple_voting.allow_destructive_uninstall', FALSE)
      || !$this->hasRuntimeData()) {
      return [];
    }

    return [
      $this->t('Simple Voting has stored questions, options, or votes. Create and verify a backup, then explicitly enable the documented destructive uninstall procedure before removing the module.'),
    ];
  }

  /**
   * Determines whether runtime options or votes exist.
   */
  private function hasRuntimeData(): bool {
    foreach (['voting_question', 'voting_option', 'simple_voting_option', 'simple_voting_vote'] as $table) {
      if ($this->database->schema()->tableExists($table)
        && $this->database->select($table, 'data')
          ->fields('data', ['id'])
          ->range(0, 1)
          ->execute()
          ->fetchField()) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
