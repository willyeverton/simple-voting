<?php

namespace Drupal\Tests\simple_voting\Unit;

use Drupal\Core\Entity\EntityStorageInterface;
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
    $question->method('id')->willReturn(42);
    $question->method('getMachineName')->willReturn('example_question');
    $question->method('label')->willReturn('Example question');
    $question->method('isOpen')->willReturn(TRUE);
    $question->method('showsResults')->willReturn(FALSE);

    $serializer = new VotingApiSerializer(
      $this->createMock(FileUrlGeneratorInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
    );

    self::assertSame([
      'id' => 'example_question',
      'title' => 'Example question',
      'status' => 'open',
      'show_results' => FALSE,
    ], $serializer->questionSummary($question));
  }

  /**
   * The serializer loads option images in one storage call.
   *
   * @covers ::question
   */
  public function testQuestionLoadsImageFilesInBatch(): void {
    $question = $this->createMock(VotingQuestionInterface::class);
    $question->method('id')->willReturn(42);
    $question->method('getMachineName')->willReturn('example_question');
    $question->method('label')->willReturn('Example question');
    $question->method('isOpen')->willReturn(TRUE);
    $question->method('showsResults')->willReturn(TRUE);

    $firstFile = new class {

      /**
       * Returns the first fixture URI.
       */
      public function getFileUri(): string {
        return 'public://simple_voting/options/first.png';
      }

    };
    $secondFile = new class {

      /**
       * Returns the second fixture URI.
       */
      public function getFileUri(): string {
        return 'public://simple_voting/options/second.png';
      }

    };

    $fileStorage = $this->createMock(EntityStorageInterface::class);
    $fileStorage->expects(self::once())
      ->method('loadMultiple')
      ->with([11, 12])
      ->willReturn([11 => $firstFile, 12 => $secondFile]);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::once())
      ->method('getStorage')
      ->with('file')
      ->willReturn($fileStorage);
    $fileUrlGenerator = $this->createMock(FileUrlGeneratorInterface::class);
    $fileUrlGenerator->method('generateString')->willReturnMap([
      ['public://simple_voting/options/first.png', 'https://example.test/first.png'],
      ['public://simple_voting/options/second.png', 'https://example.test/second.png'],
    ]);

    $data = (new VotingApiSerializer($fileUrlGenerator, $entityTypeManager))->question(
      $question,
      [
        ['id' => 11, 'title' => 'First', 'image_fid' => 11],
        ['id' => 12, 'title' => 'Second', 'image_fid' => 12],
      ],
    );

    self::assertSame('https://example.test/first.png', $data['options'][0]['image_url']);
    self::assertSame('https://example.test/second.png', $data['options'][1]['image_url']);
  }

}
