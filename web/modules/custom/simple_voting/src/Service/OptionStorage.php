<?php

namespace Drupal\simple_voting\Service;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Statement\FetchAs;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\simple_voting\Entity\VotingOptionInterface;
use Drupal\simple_voting\Exception\InvalidOptionException;
use Drupal\simple_voting\Exception\OptionInUseException;
use Psr\Log\LoggerInterface;

/**
 * Persistence and file usage boundary for question options.
 */
class OptionStorage {

  private const IMAGE_DIRECTORY = 'public://simple_voting/options/';

  private const ALLOWED_IMAGE_MIME_TYPES = [
    'image/png',
    'image/jpeg',
    'image/gif',
    'image/webp',
  ];

  private const ALLOWED_IMAGE_EXTENSIONS = [
    'png',
    'jpg',
    'jpeg',
    'gif',
    'webp',
  ];

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileUsageInterface $fileUsage,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    private readonly VotingMutationLock $mutationLock,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Returns options ordered by weight and ID.
   */
  public function getOptions(int $questionId): array {
    return $this->database->select('voting_option', 'o')
      ->fields('o', ['id', 'question_id', 'title', 'description', 'image_fid', 'weight'])
      ->condition('question_id', $questionId)
      ->orderBy('weight')
      ->orderBy('id')
      ->execute()
      ->fetchAll(FetchAs::Associative);
  }

  /**
   * Returns one option when it belongs to the requested question.
   */
  public function getOption(int $questionId, int $optionId): ?array {
    $option = $this->database->select('voting_option', 'o')
      ->fields('o', ['id', 'question_id', 'title', 'description', 'image_fid', 'weight'])
      ->condition('id', $optionId)
      ->condition('question_id', $questionId)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    return $option ?: NULL;
  }

  /**
   * Validates that submitted option IDs can be synchronized safely.
   *
   * @param int $questionId
   *   The internal question ID.
   * @param array<int, array<string, mixed>> $submitted
   *   Option values with optional id keys.
   */
  public function assertCanSync(int $questionId, array $submitted): void {
    $existing = $this->getOptions($questionId);
    $existingById = [];
    foreach ($existing as $option) {
      $existingById[(int) $option['id']] = $option;
    }

    $submittedById = [];
    foreach ($submitted as $option) {
      if (!empty($option['id'])) {
        $id = (int) $option['id'];
        if (!isset($existingById[$id]) || isset($submittedById[$id])) {
          throw new InvalidOptionException();
        }
        $submittedById[$id] = $option;
      }
    }

    foreach ($existingById as $id => $option) {
      if (isset($submittedById[$id])) {
        continue;
      }
      if ($this->hasVotesForOption($id)) {
        throw new OptionInUseException();
      }
    }
  }

  /**
   * Synchronizes submitted options for a question.
   *
   * @param int $questionId
   *   The internal question ID.
   * @param array<int, array<string, mixed>> $submitted
   *   Option values with optional id and image_fid keys.
   * @param bool $lockHeld
   *   Whether the caller already owns the question mutation lock.
   * @param bool $invalidate
   *   Whether to invalidate question cache tags after synchronization.
   * @param bool $globalLockHeld
   *   Whether the caller already owns the global mutation gate.
   */
  public function sync(int $questionId, array $submitted, bool $lockHeld = FALSE, bool $invalidate = TRUE, bool $globalLockHeld = FALSE): void {
    $globalLockAcquired = FALSE;
    $questionLockAcquired = FALSE;

    try {
      if (!$globalLockHeld) {
        $this->mutationLock->acquireGlobal();
        $globalLockAcquired = TRUE;
      }
      if (!$lockHeld) {
        $this->mutationLock->acquire((string) $questionId);
        $questionLockAcquired = TRUE;
      }
      $normalized = [];
      foreach (array_values($submitted) as $weight => $option) {
        $title = trim((string) ($option['title'] ?? ''));
        if ($title === '') {
          continue;
        }

        $normalized[] = [
          'id' => !empty($option['id']) ? (int) $option['id'] : NULL,
          'title' => $title,
          'description' => trim((string) ($option['description'] ?? '')),
          'image_fid' => !empty($option['image_fid']) ? (int) $option['image_fid'] : NULL,
          'weight' => $weight,
        ];
      }

      if (!$normalized) {
        throw new InvalidOptionException();
      }

      $this->renewLocks($questionId);
      $existing = $this->getOptions($questionId);
      $existingById = [];
      foreach ($existing as $option) {
        $existingById[(int) $option['id']] = $option;
      }

      foreach ($normalized as $option) {
        if ($option['id'] !== NULL && !isset($existingById[$option['id']])) {
          throw new InvalidOptionException();
        }
      }

      $submittedIds = array_values(array_filter(array_column($normalized, 'id')));
      if (count($submittedIds) !== count(array_unique($submittedIds))) {
        throw new InvalidOptionException();
      }
      $normalizedMap = [];
      foreach ($normalized as $option) {
        if ($option['id'] !== NULL) {
          $normalizedMap[$option['id']] = $option;
        }
      }

      foreach ($normalized as $option) {
        $this->renewLocks($questionId);
        if (!$option['image_fid']) {
          continue;
        }
        $oldImageFid = $option['id'] !== NULL
          ? (int) ($existingById[$option['id']]['image_fid'] ?? 0)
          : 0;
        if ($oldImageFid !== (int) $option['image_fid']) {
          $this->loadValidatedUploadFile((int) $option['image_fid']);
        }
      }

      foreach ($existingById as $id => $oldOption) {
        if (in_array($id, $submittedIds, TRUE)) {
          continue;
        }
        if ($this->hasVotesForOption($id)) {
          throw new OptionInUseException();
        }
      }

      $fileJournal = [
        'attached' => [],
        'released' => [],
      ];
      $this->renewLocks($questionId);
      $transaction = $this->database->startTransaction();
      try {
        foreach ($existingById as $id => $oldOption) {
          if (in_array($id, $submittedIds, TRUE)) {
            continue;
          }
          $this->releaseFile($oldOption['image_fid'], $id, $fileJournal);
          $this->entityTypeManager->getStorage('voting_option')->load($id)?->delete();
        }

        foreach ($normalized as $option) {
          if ($option['id'] !== NULL) {
            $oldOption = $existingById[$option['id']];
            $optionEntity = $this->entityTypeManager->getStorage('voting_option')->load($option['id']);
            if (!$optionEntity instanceof VotingOptionInterface
              || $optionEntity->getQuestionId() !== $questionId) {
              throw new InvalidOptionException();
            }
            $optionEntity->set('title', $option['title']);
            $optionEntity->set('description', $option['description']);
            $optionEntity->set('image_fid', $option['image_fid']);
            $optionEntity->set('weight', $option['weight']);
            $optionEntity->save();
            $this->syncFileUsage(
              $oldOption['image_fid'],
              $option['image_fid'],
              $option['id'],
              $fileJournal,
            );
          }
          else {
            $optionEntity = $this->entityTypeManager->getStorage('voting_option')->create([
              'question_id' => $questionId,
              'title' => $option['title'],
              'description' => $option['description'],
              'image_fid' => $option['image_fid'],
              'weight' => $option['weight'],
            ]);
            $optionEntity->save();
            $optionId = (int) $optionEntity->id();
            $this->attachFile($option['image_fid'], $optionId, $fileJournal);
          }
        }
      }
      catch (\Throwable $exception) {
        $transaction->rollBack();
        try {
          $this->compensateFileOperations($questionId, $fileJournal);
        }
        catch (\Throwable $compensationException) {
          $this->logger->critical('Option file compensation failed.', [
            'question_id' => $questionId,
            'operation' => 'sync_options',
            'exception_class' => $compensationException::class,
          ]);
          throw $compensationException;
        }
        throw $exception;
      }

      unset($transaction);
      if ($invalidate) {
        $this->invalidateQuestion($questionId);
      }
    }
    finally {
      if ($questionLockAcquired) {
        $this->mutationLock->release((string) $questionId);
      }
      if ($globalLockAcquired) {
        $this->mutationLock->releaseGlobal();
      }
    }
  }

  /**
   * Deletes all options for a question after the caller checked vote policy.
   *
   * @param int $questionId
   *   The internal question ID.
   * @param bool $lockHeld
   *   Whether the caller already owns the question mutation lock.
   * @param bool $globalLockHeld
   *   Whether the caller already owns the global mutation gate.
   */
  public function deleteForQuestion(int $questionId, bool $lockHeld = FALSE, bool $globalLockHeld = FALSE): void {
    $globalLockAcquired = FALSE;
    $questionLockAcquired = FALSE;
    $fileJournal = [
      'attached' => [],
      'released' => [],
    ];

    try {
      if (!$globalLockHeld) {
        $this->mutationLock->acquireGlobal();
        $globalLockAcquired = TRUE;
      }
      if (!$lockHeld) {
        $this->mutationLock->acquire((string) $questionId);
        $questionLockAcquired = TRUE;
      }

      $this->renewLocks($questionId);
      try {
        $options = $this->getOptions($questionId);
        foreach ($options as $option) {
          $this->releaseFile($option['image_fid'], (int) $option['id'], $fileJournal);
        }
        $storage = $this->entityTypeManager->getStorage('voting_option');
        $ids = $storage->getQuery()
          ->accessCheck(FALSE)
          ->condition('question_id', $questionId)
          ->execute();
        if ($ids) {
          $storage->delete($storage->loadMultiple($ids));
        }
      }
      catch (\Throwable $exception) {
        $this->compensateFileOperations($questionId, $fileJournal);
        throw $exception;
      }

      if (!$lockHeld) {
        $this->invalidateQuestion($questionId);
      }
    }
    finally {
      if ($questionLockAcquired) {
        $this->mutationLock->release((string) $questionId);
      }
      if ($globalLockAcquired) {
        $this->mutationLock->releaseGlobal();
      }
    }
  }

  /**
   * Renews locks held by the current option mutation.
   */
  private function renewLocks(int $questionId): void {
    $this->mutationLock->renewGlobal();
    $this->mutationLock->renew((string) $questionId);
  }

  /**
   * Invalidates all read-model tags for a question.
   */
  private function invalidateQuestion(int $questionId): void {
    $this->cacheTagsInvalidator->invalidateTags([
      'voting_question:' . $questionId,
      'simple_voting:question:' . $questionId,
      'simple_voting:question-list',
      'voting_question_list',
    ]);
  }

  /**
   * Returns whether an option has votes.
   */
  private function hasVotesForOption(int $optionId): bool {
    return (bool) $this->database->select('simple_voting_vote', 'v')
      ->fields('v', ['id'])
      ->condition('option_id', $optionId)
      ->range(0, 1)
      ->execute()
      ->fetchField();
  }

  /**
   * Synchronizes File API usage for an existing option.
   *
   * @param int|null $oldFid
   *   The previous file ID.
   * @param int|null $newFid
   *   The replacement file ID.
   * @param int $optionId
   *   The option ID owning the usage.
   * @param array<string, mixed> $fileJournal
   *   File operations to compensate if the transaction fails.
   */
  private function syncFileUsage(?int $oldFid, ?int $newFid, int $optionId, array &$fileJournal): void {
    if ((int) $oldFid === (int) $newFid) {
      return;
    }
    $this->attachFile($newFid, $optionId, $fileJournal);
    $this->releaseFile($oldFid, $optionId, $fileJournal);
  }

  /**
   * Loads and validates a temporary option image upload.
   */
  private function loadValidatedUploadFile(int $fid): FileInterface {
    $file = $this->entityTypeManager->getStorage('file')->load($fid);
    if (!$file instanceof FileInterface
      || $file->isTemporary() === FALSE
      || $file->getSize() > 2 * 1024 * 1024
      || !in_array($file->getMimeType(), self::ALLOWED_IMAGE_MIME_TYPES, TRUE)
      || !str_starts_with($file->getFileUri(), self::IMAGE_DIRECTORY)
      || !in_array(strtolower(pathinfo($file->getFileUri(), PATHINFO_EXTENSION)), self::ALLOWED_IMAGE_EXTENSIONS, TRUE)) {
      throw new InvalidOptionException();
    }

    return $file;
  }

  /**
   * Registers a permanent file usage and records the side effect.
   *
   * @param int|null $fid
   *   The file ID to attach.
   * @param int $optionId
   *   The option ID owning the usage.
   * @param array<string, mixed> $fileJournal
   *   File operations to compensate if the transaction fails.
   */
  private function attachFile(?int $fid, int $optionId, array &$fileJournal): void {
    if (!$fid) {
      return;
    }
    $file = $this->loadValidatedUploadFile($fid);
    $fileJournal['attached'][] = [
      'fid' => $fid,
      'option_id' => $optionId,
    ];
    try {
      $file->setPermanent();
      $file->save();
      $this->fileUsage->add($file, 'simple_voting', 'voting_option', (string) $optionId);
    }
    catch (\Throwable $exception) {
      if ($this->hasFileUsage($file, $optionId)) {
        $this->fileUsage->delete($file, 'simple_voting', 'voting_option', (string) $optionId);
      }
      $file->setTemporary();
      $file->save();
      throw $exception;
    }
  }

  /**
   * Releases one File API usage record and records the side effect.
   *
   * @param int|null $fid
   *   The file ID to release.
   * @param int $optionId
   *   The option ID owning the usage.
   * @param array<string, mixed> $fileJournal
   *   File operations to compensate if the transaction fails.
   */
  private function releaseFile(?int $fid, int $optionId, array &$fileJournal): void {
    if (!$fid) {
      return;
    }
    $file = $this->entityTypeManager->getStorage('file')->load((int) $fid);
    if (!$file instanceof FileInterface) {
      return;
    }

    if ($this->hasFileUsage($file, $optionId)) {
      $fileJournal['released'][] = [
        'fid' => (int) $fid,
        'option_id' => $optionId,
      ];
      $this->fileUsage->delete($file, 'simple_voting', 'voting_option', (string) $optionId);
    }
    if (str_starts_with($file->getFileUri(), self::IMAGE_DIRECTORY)
      && !$this->fileUsage->listUsage($file)) {
      $file->setTemporary();
      $file->save();
    }
  }

  /**
   * Compensates File API side effects after a database rollback.
   *
   * @param int $questionId
   *   The internal question ID used for operational logging.
   * @param array<string, mixed> $fileJournal
   *   File operations recorded during the failed mutation.
   */
  private function compensateFileOperations(int $questionId, array $fileJournal): void {
    foreach (array_reverse($fileJournal['attached']) as $operation) {
      $file = $this->entityTypeManager->getStorage('file')->load($operation['fid']);
      if (!$file instanceof FileInterface) {
        continue;
      }
      if ($this->hasFileUsage($file, $operation['option_id'])) {
        $this->fileUsage->delete(
          $file,
          'simple_voting',
          'voting_option',
          (string) $operation['option_id'],
        );
      }
      if (str_starts_with($file->getFileUri(), self::IMAGE_DIRECTORY)
        && !$this->fileUsage->listUsage($file)) {
        $file->setTemporary();
        $file->save();
      }
    }

    foreach (array_reverse($fileJournal['released']) as $operation) {
      $file = $this->entityTypeManager->getStorage('file')->load($operation['fid']);
      if (!$file instanceof FileInterface) {
        continue;
      }
      $file->setPermanent();
      $file->save();
      if (!$this->hasFileUsage($file, $operation['option_id'])) {
        $this->fileUsage->add(
          $file,
          'simple_voting',
          'voting_option',
          (string) $operation['option_id'],
        );
      }
    }

    $this->logger->notice('Option file operations compensated after rollback.', [
      'question_id' => $questionId,
      'attached_count' => count($fileJournal['attached']),
      'released_count' => count($fileJournal['released']),
    ]);
  }

  /**
   * Determines whether this module owns a usage record for an option.
   */
  private function hasFileUsage(FileInterface $file, int $optionId): bool {
    $usage = $this->fileUsage->listUsage($file);
    return isset($usage['simple_voting']['voting_option'][(string) $optionId]);
  }

}
