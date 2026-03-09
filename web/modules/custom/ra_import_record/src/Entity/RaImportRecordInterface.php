<?php

declare(strict_types=1);

namespace Drupal\ra_import_record\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a Ra Import Record entity.
 */
interface RaImportRecordInterface extends ContentEntityInterface, EntityChangedInterface {

  /**
   * Gets the target entity type ID.
   */
  public function getTargetEntityTypeId(): string;

  /**
   * Sets the target entity type ID.
   */
  public function setTargetEntityTypeId(string $type): static;

  /**
   * Gets the target entity ID.
   */
  public function getTargetEntityId(): int;

  /**
   * Sets the target entity ID.
   */
  public function setTargetEntityId(int $id): static;

  /**
   * Gets the import method.
   */
  public function getImportMethod(): string;

  /**
   * Sets the import method.
   */
  public function setImportMethod(string $method): static;

  /**
   * Gets the creation timestamp.
   */
  public function getCreatedTime(): int;

  /**
   * Sets the creation timestamp.
   */
  public function setCreatedTime(int $timestamp): static;

  /**
   * Gets the changed timestamp.
   */
  public function getChangedTime();

  /**
   * Sets the changed timestamp.
   *
   * @param int $timestamp
   * The changed timestamp.
   */
  public function setChangedTime($timestamp);

  /**
   * Gets the description.
   */
  public function getDescription(): string;

  /**
   * Sets the description.
   */
  public function setDescription(string $description): static;

  /**
   * Gets the target entity bundle.
   */
  public function getTargetEntityBundle(): string;

  /**
   * Sets the target entity bundle.
   */
  public function setTargetEntityBundle(string $bundle): static;

  /**
   * Gets the target entity UUID.
   */
  public function getTargetEntityUuid(): string;

  /**
   * Sets the target entity UUID.
   */
  public function setTargetEntityUuid(string $uuid): static;

}