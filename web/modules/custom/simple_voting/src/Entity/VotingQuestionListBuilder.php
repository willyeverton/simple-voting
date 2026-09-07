<?php

namespace Drupal\simple_voting\Entity;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Builds the administrative voting question listing.
 */
final class VotingQuestionListBuilder extends ConfigEntityListBuilder {

  public function __construct(
    EntityTypeInterface $entity_type,
    EntityTypeManagerInterface $entity_type_manager,
    protected readonly DateFormatterInterface $dateFormatter,
  ) {
    parent::__construct($entity_type, $entity_type_manager->getStorage($entity_type->id()));
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    return new self(
      $entity_type,
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    return [
      'title' => $this->t('Title'),
      'id' => $this->t('Identifier'),
      'status' => $this->t('Status'),
      'show_results' => $this->t('Results'),
      'changed' => $this->t('Changed'),
    ] + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\simple_voting\Entity\VotingQuestionInterface $entity */
    $row = [
      'title' => Link::fromTextAndUrl($entity->label(), $entity->toUrl('edit-form')),
      'id' => $entity->id(),
      'status' => $entity->isOpen() ? $this->t('Open') : $this->t('Closed'),
      'show_results' => $entity->showsResults() ? $this->t('Visible') : $this->t('Hidden'),
      'changed' => $entity->getChangedTime()
        ? $this->dateFormatter->format($entity->getChangedTime(), 'short')
        : $this->t('Not saved'),
    ];
    return $row + parent::buildRow($entity);
  }

}
