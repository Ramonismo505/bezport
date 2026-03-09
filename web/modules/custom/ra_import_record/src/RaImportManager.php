<?php

declare(strict_types=1);

namespace Drupal\ra_import_record;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ra_import_record\Entity\RaImportRecordInterface;

/**
 * Service for managing import records.
 */
final class RaImportManager {

  /**
   * Constructs a RaImportManager object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Creates a new import record for a given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * The entity that was imported.
   * @param string $method
   * The import method ('bulk' or 'manual').
   * @param string $description
   * (optional) Additional description or error message.
   *
   * @return \Drupal\ra_import_record\Entity\RaImportRecordInterface
   * The created import record entity.
   */
  public function createRecord(EntityInterface $entity, string $method = 'bulk', string $description = ''): RaImportRecordInterface {
    /** @var \Drupal\ra_import_record\Entity\RaImportRecordInterface $record */
    $record = $this->entityTypeManager->getStorage('ra_import_record')->create([
      'target_entity_type' => $entity->getEntityTypeId(),
      'target_entity_id' => $entity->id(),
      'import_method' => $method,
      'description' => $description,
    ]);

    $record->save();
    return $record;
  }

  /**
   * Checks if an entity has already been recorded as imported.
   */
  public function isImported(string $entity_type, int|string $entity_id): bool {
    $storage = $this->entityTypeManager->getStorage('ra_import_record');
    $query = $storage->getQuery()
      ->condition('target_entity_type', $entity_type)
      ->condition('target_entity_id', $entity_id)
      ->accessCheck(FALSE)
      ->range(0, 1);

    $ids = $query->execute();
    return !empty($ids);
  }

}