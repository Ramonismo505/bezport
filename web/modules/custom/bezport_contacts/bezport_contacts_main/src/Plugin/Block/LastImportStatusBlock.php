<?php

declare(strict_types=1);

namespace Drupal\bezport_contacts_main\Plugin\Block;

use Drupal\bezport_contacts_main\Service\ImportStateManager;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shows last import start/end + type.
 *
 * @Block(
 *   id = "bezport_contacts_last_import_status",
 *   admin_label = @Translation("Bezport Contacts: poslední import"),
 *   category = @Translation("Bezport Contacts")
 * )
 */
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

    $start = $this->importState->formatCzDateTime($state['start']);
    $end = $this->importState->formatCzDateTime($state['end']);
    $type = $state['type'] ?: '';

    $text = $this->t('Poslední import: zatím žádný.');
    if ($start !== '') {
      $range = $start;
      if ($end !== '') {
        $range .= ' - ' . $end;
      }
      else {
        $range .= ' - ' . $this->t('probíhá');
      }

      $suffix = $type !== '' ? (', ' . $type) : '';

      $text  = '<p>';
      $text .= $this->t('Poslední import: @range@suffix', [
        '@range' => $range,
        '@suffix' => $suffix,
      ]);
      $text  .= '</p>';

    }

    return [
      '#markup' => $text,
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

}
