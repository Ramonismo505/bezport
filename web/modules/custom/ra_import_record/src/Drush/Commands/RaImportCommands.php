<?php

declare(strict_types=1);

namespace Drupal\ra_import_record\Drush\Commands;

use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ra_import_record\RaImportManager;

/**
 * Drush commands for Ra Import Record.
 */
final class RaImportCommands extends DrushCommands {

  public function __construct(
    private readonly RaImportManager $importManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  /**
   * Hromadně smaže importované entity nebo záznamy o importu.
   */
  #[CLI\Command(name: 'ra-import:delete', aliases: ['raid'])]
  #[CLI\Option(name: 'method', description: 'Metoda importu (např. manual, bulk)')]
  #[CLI\Option(name: 'type', description: 'Typ cílové entity (např. node, taxonomy_term)')]
  #[CLI\Option(name: 'bundle', description: 'Strojový název bundle (např. article, tags)')]
  #[CLI\Option(name: 'logs-only', description: 'Smazat pouze záznamy RaImportRecord, cílové entity ponechat.')]
  #[CLI\Usage(name: 'drush raid --type=node --bundle=article --method=bulk', description: 'Smaže všechny hromadně importované články.')]
  #[CLI\Usage(name: 'drush raid --logs-only', description: 'Vyčistí celou historii importů, ale data v Drupalu ponechá.')]
  public function deleteImported(array $options = ['method' => NULL, 'type' => NULL, 'bundle' => NULL, 'logs-only' => FALSE]): void {
    $storage = $this->entityTypeManager->getStorage('ra_import_record');
    $query = $storage->getQuery()->accessCheck(FALSE);

    if ($options['method']) {
      $query->condition('import_method', $options['method']);
    }
    if ($options['type']) {
      $query->condition('target_entity_type', $options['type']);
    }
    if ($options['bundle']) {
      $query->condition('target_entity_bundle', $options['bundle']);
    }

    $record_ids = $query->execute();

    if (empty($record_ids)) {
      $this->logger()->notice('Nebyly nalezeny žádné záznamy odpovídající filtrům.');
      return;
    }

    $total = count($record_ids);
    $action_text = $options['logs-only'] ? 'samostatných logů importu' : 'cílových entit (včetně logů)';
    
    if (!$this->io()->confirm("Opravdu chceš smazat $total $action_text?")) {
      return;
    }

    $deleted_targets = 0;
    $deleted_logs = 0;

    // Rozdělení do dávek pro efektivní práci s pamětí.
    $chunks = array_chunk($record_ids, 50);

    foreach ($chunks as $chunk) {
      /** @var \Drupal\ra_import_record\Entity\RaImportRecordInterface[] $records */
      $records = $storage->loadMultiple($chunk);

      foreach ($records as $record) {
        if (!$options['logs-only']) {
          $target_type = $record->getTargetEntityTypeId();
          $target_id = $record->getTargetEntityId();
          
          try {
            $target_storage = $this->entityTypeManager->getStorage($target_type);
            $target_entity = $target_storage->load($target_id);
            
            if ($target_entity) {
              $target_entity->delete();
              $deleted_targets++;
              // Náš hook_entity_delete se postará o smazání samotného $record.
              continue;
            }
          } catch (\Exception $e) {
            $this->logger()->warning("Nepodařilo se smazat cílovou entitu $target_type:$target_id.");
          }
        }
        
        // Smaže log, pokud mažeme jen logy, nebo pokud cílová entita už fyzicky neexistovala.
        $record->delete();
        $deleted_logs++;
      }
    }

    $this->logger()->success("Hotovo. Smazáno cílových entit: $deleted_targets, smazáno samostatných logů: $deleted_logs.");
  }

}