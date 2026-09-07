<?php

namespace Drupal\Tests\simple_voting\Unit;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\simple_voting\Entity\VotingQuestionInterface;
use Drupal\simple_voting\Service\VotingApiSerializer;
use PHPUnit\Framework\TestCase;

/**
 * Tests the stable API question resource shape.
 *
 * @coversDefaultClass \Drupal\simple_voting\Service\VotingApiSerializer
 */
final class VotingApiSerializerTest extends TestCase {

  /**
   * @covers ::questionSummary
   */
  public function testQuestionSummaryContainsStableFields(): void {
    $question = $this->createMock(VotingQuestionInterface::class);
    $question->method('id')->willReturn('example-question');
    $question->method('label')->willReturn('Example question');
    $question->method('isOpen')->willReturn(TRUE);
    $question->method('showsResults')->willReturn(FALSE);

    $serializer = new VotingApiSerializer(
      $this->createMock(FileUrlGeneratorInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
    );

    self::assertSame([
      'id' => 'example-question',
      'title' => 'Example question',
      'status' => 'open',
      'show_results' => FALSE,
    ], $serializer->questionSummary($question));
  }

}
