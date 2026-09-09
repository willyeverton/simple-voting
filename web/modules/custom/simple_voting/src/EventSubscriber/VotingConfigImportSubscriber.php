<?php

namespace Drupal\simple_voting\EventSubscriber;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigImporterEvent;
use Drupal\Core\Database\Connection;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Protects runtime references when configuration entities are synchronized.
 */
final class VotingConfigImportSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  public function __construct(
    private readonly Connection $database,
    TranslationInterface $stringTranslation,
  ) {
    $this->setStringTranslation($stringTranslation);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ConfigEvents::IMPORT_VALIDATE => ['onConfigImporterValidate', 10],
    ];
  }

  /**
   * Rejects question renames and deletion of questions with runtime data.
   */
  public function onConfigImporterValidate(ConfigImporterEvent $event): void {
    $importer = $event->getConfigImporter();

    foreach ($event->getChangelist('rename') as $rename) {
      if (str_contains($rename, 'simple_voting.question.')) {
        $importer->logError($this->t('Voting question identifiers are immutable and cannot be renamed during configuration import.'));
      }
    }

    foreach ($event->getChangelist('delete') as $configName) {
      if (!str_starts_with($configName, 'simple_voting.question.')) {
        continue;
      }

      $questionId = substr($configName, strlen('simple_voting.question.'));
      if ($this->hasRuntimeData($questionId)) {
        $importer->logError($this->t('Voting question @question cannot be removed during configuration import because it has runtime options or votes.', [
          '@question' => $questionId,
        ]));
      }
    }
  }

  /**
   * Determines whether options or votes still reference a question.
   */
  private function hasRuntimeData(string $questionId): bool {
    foreach (['simple_voting_option' => 'question_id', 'simple_voting_vote' => 'question_id'] as $table => $column) {
      if ($this->database->schema()->tableExists($table)
        && $this->database->select($table, 'data')
          ->fields('data', ['id'])
          ->condition($column, $questionId)
          ->range(0, 1)
          ->execute()
          ->fetchField()) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
