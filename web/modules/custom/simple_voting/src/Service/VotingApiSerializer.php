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
    $data['options'] = array_map(function (array $option): array {
      $item = [
        'id' => (int) $option['id'],
        'title' => (string) $option['title'],
      ];
      if ((string) ($option['description'] ?? '') !== '') {
        $item['description'] = (string) $option['description'];
      }
      if (!empty($option['image_fid'])) {
        $file = $this->entityTypeManager->getStorage('file')->load((int) $option['image_fid']);
        if ($file !== NULL) {
          $item['image_url'] = $this->fileUrlGenerator->generateString($file->getFileUri());
        }
      }
      return $item;
    }, $options);
    return $data;
  }

}
