<?php

declare(strict_types=1);

namespace Drupal\bezport_contacts_main\Plugin\Block;

use Drupal\bezport_contacts_main\Service\ImportStateManager;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[Block(
  id: 'bezport_contacts_last_import_status',
  admin_label: new TranslatableMarkup('Bezport Contacts: poslední import'),
  category: new TranslatableMarkup('Bezport Contacts')
)]
final class LastImportStatusBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly ImportStateManager $importState,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('bezport_contacts_main.import_state'),
    );
  }

  public function build(): array {
    $state = $this->importState->getState();

    $start = $this->importState->formatCzDateTime($state['start'] ?? '');
    $end = $this->importState->formatCzDateTime($state['end'] ?? '');
    $type = $state['type'] ?? '';

    // Rychlé opuštění, pokud nemáme data.
    if ($start === '') {
      return [
        '#markup' => $this->t('Poslední import: zatím žádný.'),
        '#cache' => ['max-age' => 0],
      ];
    }

    $range = $start;
    $range .= ($end !== '') ? ' - ' . $end : ' - ' . $this->t('probíhá');
    $suffix = $type !== '' ? (', ' . $type) : '';

    return [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Poslední import: @range@suffix', [
        '@range' => $range,
        '@suffix' => $suffix,
      ]),
      '#cache' => [
        'max-age' => 0, // TODO: Vyměnit za cache tags
      ],
    ];
  }

}