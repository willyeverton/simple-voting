<?php

namespace Drupal\simple_voting\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\simple_voting\Exception\VoteLockUnavailableException;
use Drupal\simple_voting\Service\VotingMutationLock;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Global Simple Voting settings form.
 */
final class VotingSettingsForm extends ConfigFormBase {

  public function __construct(
    ConfigFactoryInterface $configFactory,
    TypedConfigManagerInterface $typedConfigManager,
    protected readonly VotingMutationLock $mutationLock,
  ) {
    parent::__construct($configFactory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('simple_voting.mutation_lock'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['simple_voting.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'simple_voting_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('simple_voting.settings');
    $form['voting_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable voting'),
      '#description' => $this->t('When disabled, the complete CMS and API voting flow is unavailable, including results.'),
      '#default_value' => (bool) $config->get('voting_enabled'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try {
      $this->mutationLock->acquireGlobal();
      try {
        $this->configFactory->getEditable('simple_voting.settings')
          ->set('voting_enabled', (bool) $form_state->getValue('voting_enabled'))
          ->save();
      }
      finally {
        $this->mutationLock->releaseGlobal();
      }
    }
    catch (VoteLockUnavailableException) {
      $this->messenger()->addError($this->t('Voting settings could not be saved now. Please try again shortly.'));
      return;
    }

    $this->messenger()->addStatus($this->t('Voting settings saved.'));
    parent::submitForm($form, $form_state);
  }

}
