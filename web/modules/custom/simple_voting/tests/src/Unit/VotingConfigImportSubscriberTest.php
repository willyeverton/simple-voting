<?php

namespace Drupal\Tests\simple_voting\Unit;

use Drupal\Core\Config\ConfigImporter;
use Drupal\Core\Config\ConfigImporterEvent;
use Drupal\Core\Config\StorageComparerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\simple_voting\EventSubscriber\VotingConfigImportSubscriber;
use Drupal\simple_voting\Service\VotingMutationLock;
use PHPUnit\Framework\TestCase;

/**
 * Tests configuration import coordination with domain mutations.
 *
 * @coversDefaultClass \Drupal\simple_voting\EventSubscriber\VotingConfigImportSubscriber
 */
final class VotingConfigImportSubscriberTest extends TestCase {

  /**
   * Configuration imports hold the global mutation gate until completion.
   *
   * @covers ::onConfigImporterValidate
   * @covers ::onConfigImporterImport
   */
  public function testImportHoldsGlobalMutationGateUntilFinished(): void {
    $importer = $this->createMock(ConfigImporter::class);
    $comparer = $this->createMock(StorageComparerInterface::class);
    $comparer->method('getChangelist')->willReturn([]);
    $importer->method('getStorageComparer')->willReturn($comparer);
    $backend = $this->createMock(LockBackendInterface::class);
    $backend->expects(self::once())
      ->method('acquire')
      ->with('simple_voting.config_import', 30.0)
      ->willReturn(TRUE);
    $backend->expects(self::once())
      ->method('release')
      ->with('simple_voting.config_import');

    $subscriber = new VotingConfigImportSubscriber(
      $this->createMock(Connection::class),
      $this->translation(),
      new VotingMutationLock($backend),
    );
    $event = new ConfigImporterEvent($importer);

    $subscriber->onConfigImporterValidate($event);
    $subscriber->onConfigImporterImport($event);
  }

  /**
   * Invalid imports release the gate immediately.
   *
   * @covers ::onConfigImporterValidate
   */
  public function testRejectedImportReleasesGlobalMutationGate(): void {
    $importer = $this->createMock(ConfigImporter::class);
    $comparer = $this->createMock(StorageComparerInterface::class);
    $comparer->method('getChangelist')->willReturnCallback(
      static fn (string $operation): array => $operation === 'rename'
        ? ['simple_voting.question.legacy']
        : [],
    );
    $importer->method('getStorageComparer')->willReturn($comparer);
    $importer->expects(self::once())->method('logError');
    $backend = $this->createMock(LockBackendInterface::class);
    $backend->expects(self::once())
      ->method('acquire')
      ->with('simple_voting.config_import', 30.0)
      ->willReturn(TRUE);
    $backend->expects(self::once())
      ->method('release')
      ->with('simple_voting.config_import');

    $subscriber = new VotingConfigImportSubscriber(
      $this->createMock(Connection::class),
      $this->translation(),
      new VotingMutationLock($backend),
    );

    $subscriber->onConfigImporterValidate(new ConfigImporterEvent($importer));
  }

  /**
   * Provides a translation double that returns the source string.
   */
  private function translation(): TranslationInterface {
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')->willReturnCallback(
      static fn (string $string): string => $string,
    );
    return $translation;
  }

}
