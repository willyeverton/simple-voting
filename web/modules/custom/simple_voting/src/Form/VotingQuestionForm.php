<?php

namespace Drupal\simple_voting\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\simple_voting\Entity\VotingQuestionInterface;
use Drupal\simple_voting\Exception\InvalidOptionException;
use Drupal\simple_voting\Exception\OptionInUseException;
use Drupal\simple_voting\Exception\PersistenceFailureException;
use Drupal\simple_voting\Exception\VoteLockUnavailableException;
use Drupal\simple_voting\Service\OptionStorage;
use Drupal\simple_voting\Service\QuestionPersistenceService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Administration form for questions and their owned options.
 */
final class VotingQuestionForm extends EntityForm {

  public function __construct(
    protected readonly OptionStorage $optionStorage,
    protected readonly QuestionPersistenceService $questionPersistence,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('simple_voting.option_storage'),
      $container->get('simple_voting.question_persistence'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\simple_voting\Entity\VotingQuestionInterface $question */
    $question = $this->entity;

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Question title'),
      '#default_value' => $question->label(),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    if ($question->isNew()) {
      $form['id'] = [
        '#type' => 'machine_name',
        '#title' => $this->t('Stable identifier'),
        '#machine_name' => [
          'exists' => [$this, 'exists'],
          'source' => ['title'],
        ],
        '#maxlength' => 128,
        '#required' => TRUE,
      ];
    }

    $this->initializeOptions($form_state);
    $count = (int) $form_state->get('options_count');
    $options = $form_state->get('existing_options') ?: [];

    $form['options_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Answer options'),
      '#tree' => TRUE,
      '#prefix' => '<div id="simple-voting-options-wrapper">',
      '#suffix' => '</div>',
    ];

    for ($index = 0; $index < $count; $index++) {
      $option = $options[$index] ?? [];
      $form['options_wrapper'][$index] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Option @number', ['@number' => $index + 1]),
      ];
      $form['options_wrapper'][$index]['option_id'] = [
        '#type' => 'hidden',
        '#default_value' => $option['id'] ?? '',
      ];
      $form['options_wrapper'][$index]['option_title'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Title'),
        '#default_value' => $option['title'] ?? '',
        '#required' => TRUE,
        '#maxlength' => 255,
      ];
      $form['options_wrapper'][$index]['option_description'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Description'),
        '#default_value' => $option['description'] ?? '',
        '#rows' => 3,
        '#maxlength' => 2000,
      ];
      $form['options_wrapper'][$index]['option_image'] = [
        '#type' => 'managed_file',
        '#title' => $this->t('Image'),
        '#default_value' => !empty($option['image_fid']) ? [(int) $option['image_fid']] : [],
        '#upload_location' => 'public://simple_voting/options/',
        '#upload_validators' => [
          'FileExtension' => ['extensions' => 'png jpg jpeg gif webp'],
          'FileSizeLimit' => ['fileLimit' => 2 * 1024 * 1024],
        ],
        '#upload_button' => $this->t('Upload image'),
      ];
      if ($index > 0) {
        $form['options_wrapper'][$index]['remove'] = [
          '#type' => 'submit',
          '#value' => $this->t('Remove option'),
          '#name' => 'remove_option_' . $index,
          '#submit' => ['::removeOption'],
          '#ajax' => [
            'callback' => '::refreshOptions',
            'wrapper' => 'simple-voting-options-wrapper',
          ],
          '#limit_validation_errors' => [],
          '#option_index' => $index,
        ];
      }
    }

    $form['options_wrapper']['add_option'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add option'),
      '#submit' => ['::addOption'],
      '#ajax' => [
        'callback' => '::refreshOptions',
        'wrapper' => 'simple-voting-options-wrapper',
      ],
      '#limit_validation_errors' => [],
    ];

    $form['show_results'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show results after voting'),
      '#default_value' => $question->showsResults(),
    ];
    $form['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Lifecycle status'),
      '#options' => [
        0 => $this->t('Closed'),
        1 => $this->t('Open'),
      ],
      '#default_value' => $question->isOpen() ? 1 : 0,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    if ($this->entity->isNew() && !preg_match('/^[a-z0-9][a-z0-9_]{1,127}$/', (string) $form_state->getValue('id'))) {
      $form_state->setErrorByName('id', $this->t('The identifier must use lowercase letters, numbers, and underscores only.'));
    }
    $values = $form_state->getValue('options_wrapper') ?: [];
    $valid = 0;
    foreach ($values as $index => $option) {
      if (!is_numeric($index)) {
        continue;
      }
      if (trim((string) ($option['option_title'] ?? '')) !== '') {
        $valid++;
      }
    }
    if ($valid < 1) {
      $form_state->setErrorByName('options_wrapper', $this->t('Add at least one answer option.'));
      return;
    }

    if (!$this->entity->isNew()) {
      try {
        $this->optionStorage->assertCanSync($this->entity->id(), $this->submittedOptions($form_state));
      }
      catch (OptionInUseException) {
        $form_state->setErrorByName('options_wrapper', $this->t('An option with votes cannot be removed. Close the question instead.'));
      }
      catch (InvalidOptionException) {
        $form_state->setErrorByName('options_wrapper', $this->t('One of the submitted options is invalid.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state): void {
    if (!$entity instanceof VotingQuestionInterface) {
      throw new \InvalidArgumentException('The form entity must be a voting question.');
    }
    $entity->set('title', trim((string) $form_state->getValue('title')));
    $entity->set('show_results', (bool) $form_state->getValue('show_results'));
    $form_state->getValue('status') ? $entity->open() : $entity->close();
    if ($entity->isNew()) {
      $entity->set('id', $form_state->getValue('id'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\simple_voting\Entity\VotingQuestionInterface $question */
    $question = $this->entity;
    $isNew = $question->isNew();
    try {
      $status = $this->questionPersistence->save(
        $question,
        $this->submittedOptions($form_state),
      );
    }
    catch (OptionInUseException) {
      $this->messenger()->addError($this->t('An option with votes cannot be removed. Close the question instead.'));
      $form_state->setRedirectUrl($question->toUrl($isNew ? 'collection' : 'edit-form'));
      return SAVED_UPDATED;
    }
    catch (InvalidOptionException) {
      $this->messenger()->addError($this->t('One of the submitted options is invalid.'));
      $form_state->setRedirectUrl($question->toUrl($isNew ? 'collection' : 'edit-form'));
      return SAVED_UPDATED;
    }
    catch (VoteLockUnavailableException) {
      $this->messenger()->addError($this->t('The question could not be saved now. Please try again shortly.'));
      $form_state->setRedirectUrl($question->toUrl($isNew ? 'collection' : 'edit-form'));
      return SAVED_UPDATED;
    }
    catch (PersistenceFailureException) {
      $this->messenger()->addError($this->t('The question could not be saved. Please try again.'));
      $form_state->setRedirectUrl($question->toUrl($isNew ? 'collection' : 'edit-form'));
      return SAVED_UPDATED;
    }

    $this->messenger()->addStatus($status === SAVED_NEW
      ? $this->t('Question %label was created.', ['%label' => $question->label()])
      : $this->t('Question %label was updated.', ['%label' => $question->label()]));
    $form_state->setRedirectUrl($question->toUrl('collection'));
    return $status;
  }

  /**
   * AJAX submit callback for adding a row.
   */
  public function addOption(array &$form, FormStateInterface $form_state): void {
    $count = (int) $form_state->get('options_count');
    $form_state->set('existing_options', $this->collectCurrentOptions($count, $form_state));
    $form_state->set('options_count', $count + 1);
    $form_state->setRebuild();
  }

  /**
   * AJAX submit callback for removing a row.
   */
  public function removeOption(array &$form, FormStateInterface $form_state): void {
    $index = (int) $form_state->getTriggeringElement()['#option_index'];
    $count = (int) $form_state->get('options_count');
    $options = $this->collectCurrentOptions($count, $form_state);
    array_splice($options, $index, 1);
    $form_state->set('existing_options', $options);
    $form_state->set('options_count', max(1, $count - 1));
    $form_state->setRebuild();
  }

  /**
   * AJAX callback returning the rebuilt options wrapper.
   */
  public function refreshOptions(array &$form, FormStateInterface $form_state): array {
    return $form['options_wrapper'];
  }

  /**
   * Machine name existence callback.
   */
  public function exists(string $id): bool {
    return (bool) $this->entityTypeManager->getStorage('voting_question')->load($id);
  }

  /**
   * Initializes form state options once.
   */
  private function initializeOptions(FormStateInterface $form_state): void {
    if ($form_state->get('options_count') !== NULL) {
      return;
    }
    $existing = $this->entity->isNew() ? [] : $this->optionStorage->getOptions($this->entity->id());
    $form_state->set('existing_options', array_values($existing));
    $form_state->set('options_count', max(1, count($existing)));
  }

  /**
   * Collects current option values before an AJAX rebuild.
   */
  private function collectCurrentOptions(int $count, FormStateInterface $form_state): array {
    $options = [];
    for ($index = 0; $index < $count; $index++) {
      $image = $form_state->getValue(['options_wrapper', $index, 'option_image']) ?: [];
      $options[] = [
        'id' => $form_state->getValue(['options_wrapper', $index, 'option_id']),
        'title' => $form_state->getValue(['options_wrapper', $index, 'option_title']) ?? '',
        'description' => $form_state->getValue(['options_wrapper', $index, 'option_description']) ?? '',
        'image_fid' => is_array($image) ? ($image[0] ?? NULL) : NULL,
      ];
    }
    return $options;
  }

  /**
   * Returns normalized option submission values.
   */
  private function submittedOptions(FormStateInterface $form_state): array {
    $values = $form_state->getValue('options_wrapper') ?: [];
    $options = [];
    foreach ($values as $index => $value) {
      if (!is_numeric($index)) {
        continue;
      }
      $image = $value['option_image'] ?? [];
      $options[] = [
        'id' => !empty($value['option_id']) ? (int) $value['option_id'] : NULL,
        'title' => trim((string) ($value['option_title'] ?? '')),
        'description' => trim((string) ($value['option_description'] ?? '')),
        'image_fid' => is_array($image) ? ($image[0] ?? NULL) : NULL,
      ];
    }
    return $options;
  }

}
