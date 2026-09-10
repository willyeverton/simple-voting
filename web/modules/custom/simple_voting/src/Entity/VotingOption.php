<?php

namespace Drupal\simple_voting\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines an answer option owned by a voting question.
 *
 * @ContentEntityType(
 *   id = "voting_option",
 *   label = @Translation("Voting option"),
 *   label_collection = @Translation("Voting options"),
 *   label_singular = @Translation("voting option"),
 *   label_plural = @Translation("voting options"),
 *   handlers = {
 *     "access" = "Drupal\simple_voting\Access\VotingOptionAccessControlHandler"
 *   },
 *   base_table = "voting_option",
 *   admin_permission = "administer simple voting",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "title"
 *   }
 * )
 */
class VotingOption extends ContentEntityBase implements VotingOptionInterface {

  /**
   * {@inheritdoc}
   */
  public function getQuestionId(): int {
    return (int) $this->get('question_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight(): int {
    return (int) $this->get('weight')->value;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['question_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Voting question'))
      ->setSetting('target_type', 'voting_question')
      ->setRequired(TRUE);
    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);
    $fields['description'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Description'));
    $fields['image_fid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Image file ID'))
      ->setSetting('unsigned', TRUE);
    $fields['weight'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Weight'))
      ->setDefaultValue(0);

    return $fields;
  }

}
