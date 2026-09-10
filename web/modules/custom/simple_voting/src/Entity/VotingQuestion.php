<?php

namespace Drupal\simple_voting\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the voting question content entity.
 *
 * @ContentEntityType(
 *   id = "voting_question",
 *   label = @Translation("Voting question"),
 *   label_collection = @Translation("Voting questions"),
 *   label_singular = @Translation("voting question"),
 *   label_plural = @Translation("voting questions"),
 *   handlers = {
 *     "list_builder" = "Drupal\simple_voting\Entity\VotingQuestionListBuilder",
 *     "form" = {
 *       "add" = "Drupal\simple_voting\Form\VotingQuestionForm",
 *       "edit" = "Drupal\simple_voting\Form\VotingQuestionForm",
 *       "delete" = "Drupal\simple_voting\Form\VotingQuestionDeleteForm"
 *     },
 *     "access" = "Drupal\simple_voting\Access\VotingQuestionAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider"
 *     }
 *   },
 *   base_table = "voting_question",
 *   admin_permission = "administer simple voting",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "title",
 *     "status" = "status"
 *   },
 *   links = {
 *     "collection" = "/admin/config/simple-voting/questions",
 *     "add-form" = "/admin/config/simple-voting/questions/add",
 *     "edit-form" = "/admin/config/simple-voting/questions/{voting_question}/edit",
 *     "delete-form" = "/admin/config/simple-voting/questions/{voting_question}/delete"
 *   }
 * )
 */
class VotingQuestion extends ContentEntityBase implements VotingQuestionInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public function isOpen(): bool {
    return (bool) $this->get('status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function open(): static {
    $this->set('status', TRUE);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function close(): static {
    $this->set('status', FALSE);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function showsResults(): bool {
    return (bool) $this->get('show_results')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getMachineName(): string {
    return (string) $this->get('machine_name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): int {
    return (int) $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    if ($this->isNew() && $this->getCreatedTime() === 0) {
      $this->set('created', \Drupal::time()->getRequestTime());
    }
    if (!$this->isNew()) {
      $original = $storage->loadUnchanged($this->id());
      if ($original instanceof VotingQuestionInterface && $this->getMachineName() !== $original->getMachineName()) {
        throw new \LogicException('A voting question machine name is immutable.');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['machine_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Machine name'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 128)
      ->addConstraint('Regex', ['pattern' => '/^[a-z0-9][a-z0-9_]{1,127}$/'])
      ->addConstraint('UniqueField');
    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Question title'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);
    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Open for voting'))
      ->setDefaultValue(FALSE);
    $fields['show_results'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Show results'))
      ->setDefaultValue(FALSE);
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'));
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'));

    return $fields;
  }

}
