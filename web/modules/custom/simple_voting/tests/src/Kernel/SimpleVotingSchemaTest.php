<?php

namespace Drupal\KernelTests\simple_voting;

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
  protected static $modules = ['system', 'user', 'file', 'simple_voting'];

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

}
