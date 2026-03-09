<?php

namespace Drupal\bezport_tools\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Administration settings form.
 */
class BezportToolsSettingsForm extends ConfigFormBase {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bezport_tools_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['bezport_tools.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('bezport_tools.settings');
    $settings = $config->get();

    $form['bezport_admin'] = [
      '#type' => 'details',
      '#title' => $this->t('Bezport admin'),
    ];

    $form['bezport_admin']['bezport_admin_uid'] = [
      '#type' => 'number',
      '#title' => $this->t('Bezport admin UID'),
      '#description' => $this->t('Fake uživatel s rolí Administrator.'),
      '#min' => 1,
      '#max' => 9999999,
      '#default_value' => $settings['bezport_admin_uid'] ?? 1,
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $uid = (int) $form_state->getValue('bezport_admin_uid');

    /** @var \Drupal\user\UserStorageInterface $user_storage */
    $user_storage = $this->entityTypeManager->getStorage('user');

    /** @var \Drupal\user\UserInterface|null $account */
    $account = $user_storage->load($uid);

    if (!$account instanceof UserInterface) {
      $form_state->setErrorByName('bezport_admin_uid', $this->t('Uživatel s UID @uid neexistuje.', [
        '@uid' => $uid,
      ]));
      return;
    }

    // Pozn.: kontrolujeme machine name role.
    if (!$account->hasRole('administrator')) {
      $form_state->setErrorByName('bezport_admin_uid', $this->t('Uživatel s UID @uid nemá roli "administrator".', [
        '@uid' => $uid,
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('bezport_tools.settings');
    $form_values = $form_state->getValues();

    $config
      ->set('bezport_admin_uid', (int) $form_values['bezport_admin_uid'])
      ->save();

    parent::submitForm($form, $form_state);
  }

}