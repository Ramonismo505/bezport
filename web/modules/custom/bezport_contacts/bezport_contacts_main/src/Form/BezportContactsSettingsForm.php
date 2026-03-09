<?php

declare(strict_types=1);

namespace Drupal\bezport_contacts_main\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings form for Bezport Contacts.
 */
final class BezportContactsSettingsForm extends ConfigFormBase {

  public function getFormId(): string {
    return 'bezport_contacts_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['bezport_contacts_main.settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('bezport_contacts_main.settings');

    $form['connection'] = [
      '#type' => 'details',
      '#title' => $this->t('Připojení k REST API'),
      '#open' => TRUE,
    ];

    $form['connection']['base_uri'] = [
      '#type' => 'url',
      '#title' => $this->t('Base URI'),
      '#required' => TRUE,
      '#default_value' => (string) $config->get('base_uri'),
      '#description' => $this->t('Např. https://example.com:58880 (bez koncového lomítka).'),
    ];

    $form['connection']['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#required' => TRUE,
      '#default_value' => (string) $config->get('username'),
    ];

    // Password: intentionally not prefilled.
    // If empty on submit, keep existing stored password.
    $form['connection']['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#required' => FALSE,
      '#description' => $this->t('Nechte prázdné pro zachování stávajícího hesla.'),
    ];

    $form['behavior'] = [
      '#type' => 'details',
      '#title' => $this->t('Chování importu'),
      '#open' => TRUE,
    ];

    $form['behavior']['use_cron'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Použít cron (automatické spouštění importů)'),
      '#default_value' => (bool) $config->get('use_cron'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $base_uri = trim((string) $form_state->getValue('base_uri'));

    if ($base_uri === '') {
      $form_state->setErrorByName('base_uri', $this->t('Base URI je povinné.'));
      return;
    }

    // Normalize: remove trailing slash.
    $base_uri = rtrim($base_uri, '/');

    // Validate URL.
    if (filter_var($base_uri, FILTER_VALIDATE_URL) === FALSE) {
      $form_state->setErrorByName('base_uri', $this->t('Base URI musí být platná URL adresa.'));
      return;
    }

    $form_state->setValue('base_uri', $base_uri);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->configFactory->getEditable('bezport_contacts_main.settings');

    $config->set('username', (string) $form_state->getValue('username'));
    $config->set('base_uri', (string) $form_state->getValue('base_uri'));
    $config->set('use_cron', (bool) $form_state->getValue('use_cron'));

    // Keep existing password if empty.
    $password = (string) $form_state->getValue('password');
    if ($password !== '') {
      $config->set('password', $password);
    }

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
