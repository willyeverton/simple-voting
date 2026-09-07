<?php

namespace Drupal\simple_voting\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Statement\FetchAs;

/**
 * Shared read model for CMS, block, and API results.
 */
final class VotingResultsService {

  public function __construct(
    private readonly Connection $database,
    private readonly QuestionReadService $questionRead,
  ) {}

  /**
   * Returns aggregate results and cache metadata for a question.
   *
   * @return array{data: array<string, mixed>, cache: \Drupal\Core\Cache\CacheableMetadata}
   *   The result data and its cacheability metadata.
   */
  public function getResults(string $questionId): array {
    $question = $this->questionRead->requireQuestion($questionId);

    $query = $this->database->select('simple_voting_option', 'o');
    $query->fields('o', ['id', 'title', 'weight']);
    $query->leftJoin(
      'simple_voting_vote',
      'v',
      '[v].[option_id] = [o].[id] AND [v].[question_id] = [o].[question_id]',
    );
    $query->addExpression('COUNT([v].[id])', 'votes');
    $query->condition('o.question_id', $questionId);
    $query->groupBy('o.id');
    $query->groupBy('o.title');
    $query->groupBy('o.weight');
    $query->orderBy('o.weight');
    $query->orderBy('o.id');

    $rows = $query->execute()->fetchAll(FetchAs::Associative);
    $total = (int) array_sum(array_map(
      static fn (array $row): int => (int) $row['votes'],
      $rows,
    ));

    $options = array_map(
      static function (array $row) use ($total): array {
        $votes = (int) $row['votes'];
        return [
          'id' => (int) $row['id'],
          'title' => (string) $row['title'],
          'votes' => $votes,
          'percentage' => $total > 0 ? round(($votes / $total) * 100, 1) : 0.0,
        ];
      },
      $rows,
    );

    $cache = (new CacheableMetadata())
      ->addCacheTags([
        'config:simple_voting.question.' . $questionId,
        'simple_voting:question:' . $questionId,
      ])
      ->addCacheContexts(['user.permissions']);

    return [
      'data' => [
        'question_id' => $question->id(),
        'question_title' => $question->label(),
        'total_votes' => $total,
        'options' => array_values($options),
      ],
      'cache' => $cache,
    ];
  }

}
