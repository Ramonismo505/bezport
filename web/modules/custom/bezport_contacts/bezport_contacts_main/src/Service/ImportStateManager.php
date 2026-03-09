<?php

declare(strict_types=1);

namespace Drupal\bezport_contacts_main\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\DrupalDateTime;

final class ImportStateManager {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  public function markStart(string $type): void {
    $type = $this->normalizeType($type);

    $config = $this->configFactory->getEditable('bezport_contacts_main.settings');

    // Store in ISO for stability.
    $config->set('last_import_start', $this->nowIso());
    $config->set('last_import_end', '');
    $config->set('last_import_type', $type);
    $config->save();
  }

  public function markEnd(): void {
    $config = $this->configFactory->getEditable('bezport_contacts_main.settings');
    $config->set('last_import_end', $this->nowIso());
    $config->save();
  }

  /**
   * @return array{start:string, end:string, type:string}
   */
  public function getState(): array {
    $config = $this->configFactory->get('bezport_contacts_main.settings');

    return [
      'start' => (string) $config->get('last_import_start'),
      'end' => (string) $config->get('last_import_end'),
      'type' => (string) $config->get('last_import_type'),
    ];
  }

  public function formatCzDateTime(string $iso): string {
    if ($iso === '') {
      return '';
    }

    try {
      // Interpret ISO in site timezone.
      $dt = new DrupalDateTime($iso);
      // Custom format in Czech style: "21. 2. 2026 21:08:30"
      return $dt->format('j. n. Y H:i:s');
    }
    catch (\Throwable) {
      return $iso;
    }
  }

  private function nowIso(): string {
    // Use site timezone.
    $dt = new DrupalDateTime('now');
    return $dt->format('Y-m-d\TH:i:s');
  }

  private function normalizeType(string $type): string {
    $type = strtolower(trim($type));
    return match ($type) {
      'cron', 'manual', 'drush' => $type,
      default => 'cron',
    };
  }

}
