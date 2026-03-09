<?php

declare(strict_types=1);

namespace Drupal\ra_import_record;

/**
 * Příklad služby, která využívá tvůj manager pomocí DI.
 */
final class RaImporter {

  public function __construct(
    private readonly RaImportManager $importManager,
  ) {}

  public function processImport(array $data): void {
    // ... logika importu entity ...
    // $entity = $this->saveSomething($data);

    // Použití injektované služby:
    $this->importManager->createRecord($entity, 'bulk', 'Importováno přes DI službu.');
  }
}