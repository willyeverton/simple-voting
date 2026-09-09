<?php

namespace Drupal\Tests\simple_voting\Kernel;

use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\KernelTests\KernelTestBase;

/**
 * Confirms the runtime vote and option tables are installed by the module.
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
    $this->installSchema('simple_voting', [
      'simple_voting_option',
      'simple_voting_vote',
    ]);
  }

  /**
   * Confirms schema boundaries and the vote uniqueness index exist.
   */
  public function testRuntimeTablesExist(): void {
    $schema = $this->container->get('database')->schema();
    self::assertTrue($schema->tableExists('simple_voting_option'));
    self::assertTrue($schema->tableExists('simple_voting_vote'));
    self::assertTrue($schema->indexExists('simple_voting_vote', 'question_uid'));
  }

  /**
   * Confirms the voting question entity definition is installed.
   */
  public function testVotingQuestionEntityDefinitionIsInstalled(): void {
    \simple_voting_update_11003();
    $update_manager = $this->container->get('entity.definition_update_manager');
    self::assertArrayHasKey('voting_question', $update_manager->getEntityTypes());
  }

  /**
   * The database rejects a second vote for the same question and user.
   */
  public function testQuestionAndUserVoteCombinationIsUnique(): void {
    $database = $this->container->get('database');
    $fields = [
      'question_id' => 'example_question',
      'option_id' => 1,
      'uid' => 10,
      'timestamp' => 1,
    ];
    $database->insert('simple_voting_vote')->fields($fields)->execute();

    $this->expectException(IntegrityConstraintViolationException::class);
    $database->insert('simple_voting_vote')->fields($fields)->execute();
  }

}
