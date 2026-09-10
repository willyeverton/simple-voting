<?php

namespace Drupal\Tests\simple_voting\Kernel;

use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simple_voting\Entity\VotingOptionInterface;
use Drupal\simple_voting\Entity\VotingQuestionInterface;
use Drupal\simple_voting\Exception\OptionInUseException;

/**
 * Confirms the entity and runtime vote storage boundaries.
 *
 * @group simple_voting
 */
final class SimpleVotingSchemaTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'file', 'block', 'simple_voting'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('voting_question');
    $this->installEntitySchema('voting_option');
    $this->installSchema('simple_voting', ['simple_voting_vote']);
  }

  /**
   * Confirms content entity and vote tables are installed.
   */
  public function testStorageTablesExist(): void {
    $schema = $this->container->get('database')->schema();
    self::assertTrue($schema->tableExists('voting_question'));
    self::assertTrue($schema->tableExists('voting_option'));
    self::assertTrue($schema->tableExists('simple_voting_vote'));
    self::assertTrue($schema->indexExists('simple_voting_vote', 'question_uid'));
  }

  /**
   * Questions expose machine names while options retain internal entity IDs.
   */
  public function testQuestionAndOptionAreContentEntitiesWithSeparateIdentifiers(): void {
    $question = $this->createQuestion('favorite_color');
    $option = $this->createOption($question, 'Blue');

    self::assertInstanceOf(VotingQuestionInterface::class, $question);
    self::assertGreaterThan(0, (int) $question->id());
    self::assertSame('favorite_color', $question->getMachineName());
    $loaded = $this->container->get('simple_voting.question_read')->load('favorite_color');
    self::assertSame($question->id(), $loaded?->id());
    self::assertInstanceOf(VotingOptionInterface::class, $option);
    self::assertGreaterThan(0, (int) $option->id());
    self::assertSame((int) $question->id(), $option->getQuestionId());
  }

  /**
   * Votes persist internal entity IDs rather than the public machine name.
   */
  public function testVotesUseInternalQuestionAndOptionIds(): void {
    $question = $this->createQuestion('favorite_color');
    $option = $this->createOption($question, 'Blue');
    $database = $this->container->get('database');
    $database->insert('simple_voting_vote')->fields([
      'question_id' => (int) $question->id(),
      'option_id' => (int) $option->id(),
      'uid' => 10,
      'timestamp' => 1,
    ])->execute();

    $vote = $database->select('simple_voting_vote', 'vote')
      ->fields('vote', ['question_id', 'option_id'])
      ->execute()
      ->fetchAssoc();
    self::assertSame((string) $question->id(), (string) $vote['question_id']);
    self::assertSame((string) $option->id(), (string) $vote['option_id']);
  }

  /**
   * The database rejects a second vote for the same internal question and user.
   */
  public function testQuestionAndUserVoteCombinationIsUnique(): void {
    $question = $this->createQuestion('favorite_color');
    $option = $this->createOption($question, 'Blue');
    $database = $this->container->get('database');
    $fields = [
      'question_id' => (int) $question->id(),
      'option_id' => (int) $option->id(),
      'uid' => 10,
      'timestamp' => 1,
    ];
    $database->insert('simple_voting_vote')->fields($fields)->execute();

    $this->expectException(IntegrityConstraintViolationException::class);
    $database->insert('simple_voting_vote')->fields($fields)->execute();
  }

  /**
   * An option can be edited while it has no votes.
   */
  public function testOptionCanBeEditedWithoutVotes(): void {
    $question = $this->createQuestion('favorite_color');
    $option = $this->createOption($question, 'Blue');
    $optionStorage = $this->container->get('simple_voting.option_storage');
    $optionStorage->sync((int) $question->id(), [
      [
        'id' => (int) $option->id(),
        'title' => 'Blueish',
        'description' => 'A bluish tone',
        'image_fid' => NULL,
      ],
    ]);

    $reloaded = $this->container->get('entity_type.manager')
      ->getStorage('voting_option')
      ->load((int) $option->id());
    self::assertSame('Blueish', (string) $reloaded->label());
  }

  /**
   * An option with votes cannot be removed.
   */
  public function testOptionCannotBeRemovedWithVotes(): void {
    $question = $this->createQuestion('favorite_color');
    $option = $this->createOption($question, 'Blue');
    $this->container->get('database')->insert('simple_voting_vote')->fields([
      'question_id' => (int) $question->id(),
      'option_id' => (int) $option->id(),
      'uid' => 10,
      'timestamp' => 1,
    ])->execute();

    $optionStorage = $this->container->get('simple_voting.option_storage');
    $this->expectException(OptionInUseException::class);
    $optionStorage->assertCanSync((int) $question->id(), []);
  }

  /**
   * An option with votes can have its content edited.
   */
  public function testOptionCanBeEditedWithVotes(): void {
    $question = $this->createQuestion('favorite_color');
    $option = $this->createOption($question, 'Blue');
    $this->container->get('database')->insert('simple_voting_vote')->fields([
      'question_id' => (int) $question->id(),
      'option_id' => (int) $option->id(),
      'uid' => 10,
      'timestamp' => 1,
    ])->execute();

    $optionStorage = $this->container->get('simple_voting.option_storage');
    $submitted = [
      [
        'id' => (int) $option->id(),
        'title' => 'Blueish',
        'description' => 'A bluish tone',
        'image_fid' => NULL,
      ],
    ];
    $optionStorage->assertCanSync((int) $question->id(), $submitted);
    $optionStorage->sync((int) $question->id(), $submitted);

    $reloaded = $this->container->get('entity_type.manager')
      ->getStorage('voting_option')
      ->load((int) $option->id());
    self::assertSame('Blueish', (string) $reloaded->label());
    self::assertSame('A bluish tone', (string) ($reloaded->get('description')->first()?->value ?? ''));
  }

  /**
   * A question without votes can be deleted.
   */
  public function testQuestionCanBeDeletedWithoutVotes(): void {
    $question = $this->createQuestion('favorite_color');
    $this->createOption($question, 'Blue');
    $this->container->get('simple_voting.question_deletion')->delete($question);

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('voting_question');
    self::assertNull($storage->load((int) $question->id()));
  }

  /**
   * Creates a voting question for testing.
   *
   * @param string $machineName
   *   The question machine name.
   *
   * @return \Drupal\simple_voting\Entity\VotingQuestionInterface
   *   The created question.
   */
  private function createQuestion(string $machineName): VotingQuestionInterface {
    $question = $this->container->get('entity_type.manager')
      ->getStorage('voting_question')
      ->create([
        'machine_name' => $machineName,
        'title' => 'Favorite color?',
        'status' => FALSE,
        'show_results' => TRUE,
      ]);
    $question->save();
    return $question;
  }

  /**
   * Creates a voting option for testing.
   *
   * @param \Drupal\simple_voting\Entity\VotingQuestionInterface $question
   *   The parent question.
   * @param string $title
   *   The option title.
   *
   * @return \Drupal\simple_voting\Entity\VotingOptionInterface
   *   The created option.
   */
  private function createOption(VotingQuestionInterface $question, string $title): VotingOptionInterface {
    $option = $this->container->get('entity_type.manager')
      ->getStorage('voting_option')
      ->create([
        'question_id' => $question->id(),
        'title' => $title,
        'weight' => 0,
      ]);
    $option->save();
    return $option;
  }

}
