<?php

declare(strict_types=1);

namespace Drupal\rabp\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings form for rabp.
 */
final class SettingsForm extends ConfigFormBase {

  public function getFormId(): string {
    return 'rabp_settings_form';
  }

  protected function getEditableConfigNames(): array {
    return ['rabp.settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    $config = $this->config('rabp.settings');

    $form['#attached']['library'][] = 'rabp/admin';

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable breakpoint box'),
      '#default_value' => (bool) $config->get('status'),
    ];

    $form['position'] = [
      '#type' => 'select',
      '#title' => $this->t('Position'),
      '#options' => [
        'bottomleft' => $this->t('Bottom left'),
        'bottomright' => $this->t('Bottom right'),
        'topleft' => $this->t('Top left'),
        'topright' => $this->t('Top right'),
      ],
      '#default_value' => (string) ($config->get('position') ?? 'bottomleft'),
    ];

    $form['foreground'] = [
      '#type' => 'color',
      '#title' => $this->t('Foreground (text color)'),
      '#default_value' => (string) ($config->get('foreground') ?? '#000000'),
      '#required' => TRUE,
    ];

    $form['breakpoint_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Breakpoints'),
      '#open' => TRUE,
      '#tree' => TRUE,
      '#description' => $this->t('Set breakpoint width in pixels and background color for each breakpoint.'),
      '#attributes' => [
        'class' => ['rabp-breakpoint-settings'],
      ],
    ];

    $form['breakpoint_settings']['header'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['rabp-breakpoint-grid', 'rabp-breakpoint-grid--header'],
      ],
    ];

    $form['breakpoint_settings']['header']['label'] = [
      '#markup' => '<div><strong>' . $this->t('Breakpoint') . '</strong></div>',
    ];
    $form['breakpoint_settings']['header']['width'] = [
      '#markup' => '<div><strong>' . $this->t('Width (px)') . '</strong></div>',
    ];
    $form['breakpoint_settings']['header']['background'] = [
      '#markup' => '<div><strong>' . $this->t('Background color') . '</strong></div>',
    ];

    $breakpoints = [
      'small' => 'Small',
      'smedium' => 'Smedium',
      'medium' => 'Medium',
      'large' => 'Large',
      'xlarge' => 'X-large',
      'xxlarge' => 'XX-large',
      'xxxlarge' => 'XXX-large',
    ];

    foreach ($breakpoints as $key => $label) {
      $form['breakpoint_settings'][$key] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['rabp-breakpoint-grid'],
        ],
      ];

      $form['breakpoint_settings'][$key]['label'] = [
        '#markup' => '<div class="rabp-breakpoint-label">' . $label . '</div>',
      ];

      $form['breakpoint_settings'][$key]['width'] = [
        '#type' => 'number',
        '#title' => $label . ' ' . $this->t('width'),
        '#title_display' => 'invisible',
        '#default_value' => (int) $config->get($key),
        '#min' => 0,
        '#step' => 1,
        '#required' => TRUE,
        '#field_suffix' => 'px',
      ];

      $form['breakpoint_settings'][$key]['background'] = [
        '#type' => 'color',
        '#title' => $label . ' ' . $this->t('background color'),
        '#title_display' => 'invisible',
        '#default_value' => (string) ($config->get('background_' . $key) ?? '#2fb9b3'),
        '#required' => TRUE,
      ];
    }

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $foreground = (string) $form_state->getValue('foreground');
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $foreground)) {
      $form_state->setErrorByName('foreground', $this->t('Enter a valid hex color in format #RRGGBB.'));
    }
    else {
      $form_state->setValue('foreground', strtolower($foreground));
    }

    $breakpoint_settings = (array) $form_state->getValue('breakpoint_settings');

    foreach (['small', 'smedium', 'medium', 'large', 'xlarge', 'xxlarge', 'xxxlarge'] as $key) {
      $background = (string) ($breakpoint_settings[$key]['background'] ?? '');
      if (!preg_match('/^#[0-9a-fA-F]{6}$/', $background)) {
        $form_state->setErrorByName("breakpoint_settings][$key][background", $this->t('Enter a valid hex color in format #RRGGBB.'));
      }
      else {
        $breakpoint_settings[$key]['background'] = strtolower($background);
      }

      $width = $breakpoint_settings[$key]['width'] ?? NULL;
      if (!is_numeric($width) || (int) $width < 0) {
        $form_state->setErrorByName("breakpoint_settings][$key][width", $this->t('Width must be a non-negative number.'));
      }
    }

    $form_state->setValue('breakpoint_settings', $breakpoint_settings);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);

    $breakpoint_settings = (array) $form_state->getValue('breakpoint_settings');

    $config = $this->configFactory->getEditable('rabp.settings');
    $config
      ->set('status', (bool) $form_state->getValue('status'))
      ->set('position', (string) $form_state->getValue('position'))
      ->set('foreground', (string) $form_state->getValue('foreground'));

    foreach (['small', 'smedium', 'medium', 'large', 'xlarge', 'xxlarge', 'xxxlarge'] as $key) {
      $config
        ->set($key, (int) $breakpoint_settings[$key]['width'])
        ->set('background_' . $key, (string) $breakpoint_settings[$key]['background']);
    }

    $config->save();
  }

}