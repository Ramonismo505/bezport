<?php

declare(strict_types=1);

namespace Drupal\ra_import_record\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Entity\EntityChangedTrait;

/**
 * Defines the Ra Import Record entity.
 */
#[ContentEntityType(
  id: 'ra_import_record',
  label: new TranslatableMarkup('Import Record'),
  base_table: 'ra_import_record',
  handlers: [
    'views_data' => 'Drupal\views\EntityViewsData',
  ],
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
  ],
)]
class RaImportRecord extends ContentEntityBase implements RaImportRecordInterface {

  // Využijeme Trait z jádra pro automatickou obsluhu changed metod.
  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public function getTargetEntityTypeId(): string {
    return (string) $this->get('target_entity_type')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTargetEntityTypeId(string $type): static {
    $this->set('target_entity_type', $type);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetEntityId(): int {
    return (int) $this->get('target_entity_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTargetEntityId(int $id): static {
    $this->set('target_entity_id', $id);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getImportMethod(): string {
    return (string) $this->get('import_method')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setImportMethod(string $method): static {
    $this->set('import_method', $method);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): int {
    return (int) $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime(int $timestamp): static {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return (string) $this->get('description')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setDescription(string $description): static {
    $this->set('description', $description);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetEntityBundle(): string {
    return (string) $this->get('target_entity_bundle')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTargetEntityBundle(string $bundle): static {
    $this->set('target_entity_bundle', $bundle);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetEntityUuid(): string {
    return (string) $this->get('target_entity_uuid')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTargetEntityUuid(string $uuid): static {
    $this->set('target_entity_uuid', $uuid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['target_entity_type'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Target Entity Type'))
      ->setDescription(new TranslatableMarkup('Strojový název typu naimportované entity.'))
      ->setSetting('max_length', 32)
      ->setRequired(TRUE);

    $fields['target_entity_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Target Entity ID'))
      ->setDescription(new TranslatableMarkup('ID naimportované entity.'))
      ->setRequired(TRUE);

    $fields['import_method'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Import Method'))
      ->setSetting('allowed_values', [
        'manual' => 'Manuálně',
        'bulk' => 'Hromadně',
      ])
      ->setDefaultValue('bulk')
      ->setRequired(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Created'))
      ->setDescription(new TranslatableMarkup('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Changed'))
      ->setDescription(new TranslatableMarkup('The time that the entity was last edited.'));

    $fields['description'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Description'))
      ->setDefaultValue('');

    $fields['target_entity_bundle'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Target Entity Bundle'))
      ->setDescription(new TranslatableMarkup('Strojový název bundle (typ obsahu, slovník) naimportované entity.'))
      ->setSetting('max_length', 32)
      ->setRequired(TRUE);

    // UUID v Drupalu má vždy přesně 36 znaků.
    $fields['target_entity_uuid'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Target Entity UUID'))
      ->setDescription(new TranslatableMarkup('UUID naimportované entity.'))
      ->setSetting('max_length', 36)
      ->setRequired(TRUE);

    return $fields;
  }

}