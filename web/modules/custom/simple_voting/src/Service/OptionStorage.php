<?php

namespace Drupal\simple_voting\Service;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Statement\FetchAs;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\simple_voting\Exception\InvalidOptionException;
use Drupal\simple_voting\Exception\OptionInUseException;

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
  ) {}

  /**
   * Returns options ordered by weight and ID.
   */
  public function getOptions(string $questionId): array {
    return $this->database->select('simple_voting_option', 'o')
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
  public function getOption(string $questionId, int $optionId): ?array {
    $option = $this->database->select('simple_voting_option', 'o')
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
   * @param string $questionId
   *   The question machine name.
   * @param array<int, array<string, mixed>> $submitted
   *   Option values with optional id keys.
   */
  public function assertCanSync(string $questionId, array $submitted): void {
    $existing = $this->getOptions($questionId);
    $existingById = [];
    foreach ($existing as $option) {
      $existingById[(int) $option['id']] = $option;
    }

    $submittedIds = [];
    foreach ($submitted as $option) {
      if (!empty($option['id'])) {
        $id = (int) $option['id'];
        if (!isset($existingById[$id]) || in_array($id, $submittedIds, TRUE)) {
          throw new InvalidOptionException();
        }
        $submittedIds[] = $id;
      }
    }

    foreach ($existingById as $id => $option) {
      if (!in_array($id, $submittedIds, TRUE) && $this->hasVotesForOption($id)) {
        throw new OptionInUseException();
      }
    }
  }

  /**
   * Synchronizes submitted options for a question.
   *
   * @param string $questionId
   *   The question machine name.
   * @param array<int, array<string, mixed>> $submitted
   *   Option values with optional id and image_fid keys.
   * @param bool $lockHeld
   *   Whether the caller already owns the question mutation lock.
   * @param bool $invalidate
   *   Whether to invalidate question cache tags after synchronization.
   */
  public function sync(string $questionId, array $submitted, bool $lockHeld = FALSE, bool $invalidate = TRUE): void {
    $acquired = !$lockHeld;
    if ($acquired) {
      $this->mutationLock->acquire($questionId);
    }

    try {
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

      foreach ($normalized as $option) {
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
        if (!in_array($id, $submittedIds, TRUE) && $this->hasVotesForOption($id)) {
          throw new OptionInUseException();
        }
      }

      $transaction = $this->database->startTransaction();
      try {
        foreach ($existingById as $id => $oldOption) {
          if (in_array($id, $submittedIds, TRUE)) {
            continue;
          }
          $this->releaseFile($oldOption['image_fid'], $id);
          $this->database->delete('simple_voting_option')
            ->condition('id', $id)
            ->execute();
        }

        foreach ($normalized as $option) {
          if ($option['id'] !== NULL) {
            $oldOption = $existingById[$option['id']];
            $this->database->update('simple_voting_option')
              ->fields([
                'title' => $option['title'],
                'description' => $option['description'],
                'image_fid' => $option['image_fid'],
                'weight' => $option['weight'],
              ])
              ->condition('id', $option['id'])
              ->condition('question_id', $questionId)
              ->execute();
            $this->syncFileUsage(
              $oldOption['image_fid'],
              $option['image_fid'],
              $option['id'],
            );
          }
          else {
            $optionId = (int) $this->database->insert('simple_voting_option')
              ->fields([
                'question_id' => $questionId,
                'title' => $option['title'],
                'description' => $option['description'],
                'image_fid' => $option['image_fid'],
                'weight' => $option['weight'],
              ])
              ->execute();
            $this->attachFile($option['image_fid'], $optionId);
          }
        }
      }
      catch (\Throwable $exception) {
        $transaction->rollBack();
        throw $exception;
      }

      unset($transaction);
      if ($invalidate) {
        $this->invalidateQuestion($questionId);
      }
    }
    finally {
      if ($acquired) {
        $this->mutationLock->release($questionId);
      }
    }
  }

  /**
   * Deletes all options for a question after the caller checked vote policy.
   *
   * @param string $questionId
   *   The question machine name.
   * @param bool $lockHeld
   *   Whether the caller already owns the question mutation lock.
   */
  public function deleteForQuestion(string $questionId, bool $lockHeld = FALSE): void {
    $acquired = !$lockHeld;
    if ($acquired) {
      $this->mutationLock->acquire($questionId);
    }

    try {
      $options = $this->getOptions($questionId);
      foreach ($options as $option) {
        $this->releaseFile($option['image_fid'], (int) $option['id']);
      }
      $this->database->delete('simple_voting_option')
        ->condition('question_id', $questionId)
        ->execute();
      if (!$lockHeld) {
        $this->invalidateQuestion($questionId);
      }
    }
    finally {
      if ($acquired) {
        $this->mutationLock->release($questionId);
      }
    }
  }

  /**
   * Invalidates all read-model tags for a question.
   */
  private function invalidateQuestion(string $questionId): void {
    $this->cacheTagsInvalidator->invalidateTags([
      'config:simple_voting.question.' . $questionId,
      'simple_voting:question:' . $questionId,
      'simple_voting:question-list',
      'config:voting_question_list',
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
   */
  private function syncFileUsage(?int $oldFid, ?int $newFid, int $optionId): void {
    if ((int) $oldFid === (int) $newFid) {
      return;
    }
    $this->attachFile($newFid, $optionId);
    try {
      $this->releaseFile($oldFid, $optionId);
    }
    catch (\Throwable $exception) {
      $this->releaseFile($newFid, $optionId);
      throw $exception;
    }
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
   * Registers a permanent file usage.
   */
  private function attachFile(?int $fid, int $optionId): void {
    if (!$fid) {
      return;
    }
    $file = $this->loadValidatedUploadFile($fid);
    $file->setPermanent();
    $file->save();
    try {
      $this->fileUsage->add($file, 'simple_voting', 'voting_option', (string) $optionId);
    }
    catch (\Throwable $exception) {
      $file->setTemporary();
      $file->save();
      throw $exception;
    }
  }

  /**
   * Releases one File API usage record.
   */
  private function releaseFile(?int $fid, int $optionId): void {
    if (!$fid) {
      return;
    }
    $file = $this->entityTypeManager->getStorage('file')->load((int) $fid);
    if ($file === NULL) {
      return;
    }

    $this->fileUsage->delete($file, 'simple_voting', 'voting_option', (string) $optionId);
    if (str_starts_with($file->getFileUri(), self::IMAGE_DIRECTORY)
      && !$this->fileUsage->listUsage($file)) {
      $file->setTemporary();
      $file->save();
    }
  }

}
