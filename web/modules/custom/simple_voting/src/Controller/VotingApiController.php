<?php

namespace Drupal\simple_voting\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\simple_voting\Service\QuestionReadService;
use Drupal\simple_voting\Service\VotingApiSerializer;
use Drupal\simple_voting\Service\VotingResultsService;
use Drupal\simple_voting\Service\VotingVisibilityService;
use Drupal\simple_voting\Service\VotingService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Thin manual JSON API controller for Simple Voting.
 */
final class VotingApiController extends ControllerBase {

  public function __construct(
    private readonly AccountProxyInterface $votingAccount,
    private readonly QuestionReadService $questionRead,
    private readonly VotingApiSerializer $serializer,
    private readonly VotingResultsService $results,
    private readonly VotingVisibilityService $visibility,
    private readonly VotingService $votingService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_user'),
      $container->get('simple_voting.question_read'),
      $container->get('simple_voting.api_serializer'),
      $container->get('simple_voting.results'),
      $container->get('simple_voting.visibility'),
      $container->get('simple_voting.voting'),
    );
  }

  /**
   * GET /api/v1/questions.
   */
  public function listQuestions(): CacheableJsonResponse {
    $this->requireAuthenticated();
    $questions = $this->questionRead->getQuestions(TRUE);
    $data = array_map(fn ($question): array => $this->serializer->questionSummary($question), $questions);
    $cache = (new CacheableMetadata())
      ->addCacheTags(['simple_voting:question-list'])
      ->addCacheContexts(['user.permissions']);
    return (new CacheableJsonResponse(['data' => array_values($data)]))
      ->addCacheableDependency($cache);
  }

  /**
   * GET /api/v1/questions/{question_id}.
   */
  public function getQuestion(string $question_id): CacheableJsonResponse {
    $this->requireAuthenticated();
    $question = $this->questionRead->load($question_id);
    if ($question === NULL) {
      throw new NotFoundHttpException();
    }
    $cache = (new CacheableMetadata())
      ->addCacheTags([
        'config:simple_voting.question.' . $question_id,
        'simple_voting:question:' . $question_id,
      ])
      ->addCacheContexts(['user.permissions']);
    return (new CacheableJsonResponse([
      'data' => $this->serializer->question($question, $this->questionRead->options($question_id)),
    ]))->addCacheableDependency($cache);
  }

  /**
   * POST /api/v1/questions/{question_id}/votes.
   */
  public function createVote(Request $request, string $question_id): JsonResponse {
    $this->requireAuthenticated();
    if (!$this->votingAccount->hasPermission('vote in polls')) {
      throw new AccessDeniedHttpException();
    }

    $payload = $this->decodePayload($request);
    if (count($payload) !== 1 || !isset($payload['option_id']) || !is_int($payload['option_id']) || $payload['option_id'] < 1) {
      throw new BadRequestHttpException();
    }

    $this->votingService->castVote(
      $question_id,
      $payload['option_id'],
      (int) $this->votingAccount->id(),
    );
    return new JsonResponse(
      ['message' => 'Vote registered successfully.'],
      Response::HTTP_CREATED,
    );
  }

  /**
   * GET /api/v1/questions/{question_id}/results.
   */
  public function getResults(string $question_id): CacheableJsonResponse {
    $this->requireAuthenticated();
    $question = $this->questionRead->load($question_id);
    if ($question === NULL) {
      throw new NotFoundHttpException();
    }
    if (!$this->visibility->canViewResults($question, $this->votingAccount)) {
      throw new AccessDeniedHttpException();
    }

    $result = $this->results->getResults($question_id);
    return (new CacheableJsonResponse(['data' => $result['data']]))
      ->addCacheableDependency($result['cache']);
  }

  /**
   * Requires a logged-in Drupal account at the business boundary.
   */
  private function requireAuthenticated(): void {
    if ($this->votingAccount->isAnonymous()) {
      throw new UnauthorizedHttpException(
        'Basic',
        'Authentication is required.',
      );
    }
  }

  /**
   * Decodes and validates the JSON request body shape.
   *
   * @return array<string, mixed>
   *   The decoded associative JSON payload.
   */
  private function decodePayload(Request $request): array {
    $content = trim($request->getContent());
    if ($content === '') {
      throw new BadRequestHttpException();
    }
    try {
      $payload = json_decode($content, TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $exception) {
      throw new BadRequestHttpException(previous: $exception);
    }
    if (!is_array($payload) || array_is_list($payload)) {
      throw new BadRequestHttpException();
    }
    return $payload;
  }

}
