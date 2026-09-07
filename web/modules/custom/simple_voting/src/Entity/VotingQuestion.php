<?php

namespace Drupal\simple_voting\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines the voting question configuration entity.
 *
 * @ConfigEntityType(
 *   id = "voting_question",
 *   label = @Translation("Voting question"),
 *   label_collection = @Translation("Voting questions"),
 *   label_singular = @Translation("voting question"),
 *   label_plural = @Translation("voting questions"),
 *   handlers = {
 *     "storage" = "Drupal\Core\Config\Entity\ConfigEntityStorage",
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
 *   admin_permission = "administer simple voting",
 *   config_prefix = "question",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "title",
 *     "uuid" = "uuid",
 *     "status" = "status"
 *   },
 *   links = {
 *     "collection" = "/admin/config/simple-voting/questions",
 *     "add-form" = "/admin/config/simple-voting/questions/add",
 *     "edit-form" = "/admin/config/simple-voting/questions/{voting_question}/edit",
 *     "delete-form" = "/admin/config/simple-voting/questions/{voting_question}/delete"
 *   },
 *   config_export = {
 *     "id",
 *     "uuid",
 *     "title",
 *     "status",
 *     "show_results",
 *     "created",
 *     "changed"
 *   }
 * )
 */
class VotingQuestion extends ConfigEntityBase implements VotingQuestionInterface {

  /**
   * The stable machine name.
   *
   * @var string
   */
  protected string $id;

  /**
   * The question title.
   *
   * @var string
   */
  protected string $title = '';

  /**
   * Whether this question accepts votes.
   *
   * @var bool
   */
  protected $status = FALSE;

  /**
   * Whether eligible users may view results.
   *
   * @var bool
   */
  protected bool $show_results = FALSE;

  /**
   * Creation timestamp.
   *
   * @var int
   */
  protected int $created = 0;

  /**
   * Last changed timestamp.
   *
   * @var int
   */
  protected int $changed = 0;

  /**
   * {@inheritdoc}
   */
  public function isOpen(): bool {
    return $this->status;
  }

  /**
   * {@inheritdoc}
   */
  public function open(): static {
    $this->status = TRUE;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function close(): static {
    $this->status = FALSE;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function showsResults(): bool {
    return $this->show_results;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): int {
    return $this->created;
  }

  /**
   * {@inheritdoc}
   */
  public function getChangedTime(): int {
    return $this->changed;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    $now = \Drupal::time()->getRequestTime();
    if ($this->isNew()) {
      $this->created = $now;
    }
    $this->changed = $now;
  }

}
