<?php

namespace Drupal\simple_voting\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\simple_voting\Entity\VotingQuestionInterface;

/**
 * Serializes domain values into the versioned API resource shape.
 */
final class VotingApiSerializer {

  public function __construct(
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Serializes a compact question summary.
   */
  public function questionSummary(VotingQuestionInterface $question): array {
    return [
      'id' => $question->id(),
      'title' => (string) $question->label(),
      'status' => $question->isOpen() ? 'open' : 'closed',
      'show_results' => $question->showsResults(),
    ];
  }

  /**
   * Serializes a question and its options.
   */
  public function question(VotingQuestionInterface $question, array $options): array {
    $data = $this->questionSummary($question);
    $fileIds = array_values(array_filter(array_map(
      static fn (array $option): ?int => !empty($option['image_fid']) ? (int) $option['image_fid'] : NULL,
      $options,
    )));
    $files = $fileIds
      ? $this->entityTypeManager->getStorage('file')->loadMultiple($fileIds)
      : [];

    $data['options'] = array_map(function (array $option) use ($files): array {
      $item = [
        'id' => (int) $option['id'],
        'title' => (string) $option['title'],
      ];
      if ((string) ($option['description'] ?? '') !== '') {
        $item['description'] = (string) $option['description'];
      }
      if (!empty($option['image_fid']) && isset($files[(int) $option['image_fid']])) {
        $item['image_url'] = $this->fileUrlGenerator->generateString(
          $files[(int) $option['image_fid']]->getFileUri(),
        );
      }
      return $item;
    }, $options);
    return $data;
  }

}
