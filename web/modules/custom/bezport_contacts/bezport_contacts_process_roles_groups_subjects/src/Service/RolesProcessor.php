<?php

declare(strict_types=1);

namespace Drupal\bezport_contacts_process_roles_groups_subjects\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\bezport_contacts_main\Service\ImportNotifierInterface;
use Drupal\bezport_contacts_main\Service\ProcessorInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy\TermStorageInterface;
use Psr\Log\LoggerInterface;

final class RolesProcessor implements ProcessorInterface {

  private TermStorageInterface $termStorage;

  private LoggerInterface $logger;

  public function __construct(
    private readonly Connection $database,
    EntityTypeManagerInterface $entityTypeManager,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    /** @var \Drupal\taxonomy\TermStorageInterface $storage */
    $storage = $entityTypeManager->getStorage('taxonomy_term');
    $this->termStorage = $storage;

    $this->logger = $loggerFactory->get('bezport_contacts_process_rgs');
  }

  public function id(): string {
    return 'roles';
  }

  public function label(): string {
    return 'Roles';
  }

  public function process(ImportNotifierInterface $notifier): array {
    $vid = 'bezport_contacts_roles';

    $stats = [
      'new' => 0,
      'updated' => 0,
      'deleted' => 0,
      'source_total' => 0,
      'active_source_total' => 0,
    ];

    try {
      $notifier->info('Roles processing starts.');

      $used_role_ids = $this->loadUsedRoleIds();
      $stats['active_source_total'] = count($used_role_ids);

      $roles_map = $this->loadRolesMap($used_role_ids);
      $stats['source_total'] = count($roles_map);

      $existing = $this->loadExistingTermsBySourceId($vid);

      $source_ids = array_keys($roles_map);
      $existing_ids = array_keys($existing);

      $new_ids = array_diff($source_ids, $existing_ids);
      $update_ids = array_intersect($source_ids, $existing_ids);
      $obsolete_ids = array_diff($existing_ids, $source_ids);

      // NEW
      foreach ($new_ids as $source_id) {
        $name = $roles_map[(int) $source_id] ?? '';
        if ($name === '') {
          continue;
        }

        $term = $this->termStorage->create([
          'vid' => $vid,
          'name' => $name,
          'field_source_id' => (string) $source_id,
        ]);
        $term->save();
        $stats['new']++;
      }

      // UPDATE (only on change)
      foreach ($update_ids as $source_id) {
        $term = $existing[(int) $source_id] ?? NULL;
        if (!$term) {
          continue;
        }

        $new_name = $roles_map[(int) $source_id] ?? '';
        if ($new_name === '') {
          continue;
        }

        if ((string) $term->label() !== $new_name) {
          $term->setName($new_name);
          $term->save();
          $stats['updated']++;
        }
      }

      // DELETE obsolete
      foreach ($obsolete_ids as $source_id) {
        $term = $existing[(int) $source_id] ?? NULL;
        if (!$term) {
          continue;
        }

        try {
          $term->delete();
          $stats['deleted']++;
        }
        catch (\Throwable $e) {
          $this->logger->error('Failed to delete role term source_id=@sid: @msg', [
            '@sid' => (string) $source_id,
            '@msg' => $e->getMessage(),
          ]);
          $notifier->warning('Cannot delete obsolete role source_id ' . $source_id . ': ' . $e->getMessage());
        }
      }

      // SORT (ponecháme)
      try {
        \Drupal::service('krizport_tools.common_tools')->setTermsWeightByCz($vid);
      }
      catch (\Throwable $e) {
        $this->logger->warning('Sort failed: @msg', ['@msg' => $e->getMessage()]);
        $notifier->warning('Sort failed: ' . $e->getMessage());
      }

      $notifier->success('Roles processing complete.');
      return ['ok' => TRUE, 'message' => 'OK', 'stats' => $stats];
    }
    catch (\Throwable $e) {
      $this->logger->error('Roles processing failed: @msg', ['@msg' => $e->getMessage()]);
      $notifier->error('Roles processing failed: ' . $e->getMessage());
      return ['ok' => FALSE, 'message' => $e->getMessage(), 'stats' => $stats];
    }
  }

  /**
   * @return int[]
   */
  private function loadUsedRoleIds(): array {
    $result = $this->database->select('bezport_contacts_persons_subjects_roles_rls', 'r')
      ->fields('r', ['role_id'])
      ->execute()
      ->fetchCol();

    $ids = [];
    foreach ($result as $v) {
      $id = (int) $v;
      if ($id > 0) {
        $ids[$id] = $id;
      }
    }

    return array_values($ids);
  }

  /**
   * @param int[] $used_role_ids
   *
   * @return array<int,string>
   */
  private function loadRolesMap(array $used_role_ids): array {
    if ($used_role_ids === []) {
      return [];
    }

    $rows = $this->database->select('bezport_contacts_roles', 'r')
      ->fields('r', ['role_id', 'name'])
      ->condition('r.role_id', $used_role_ids, 'IN')
      ->orderBy('r.role_id', 'ASC')
      ->execute()
      ->fetchAll();

    $map = [];
    foreach ($rows as $row) {
      $id = (int) ($row->role_id ?? 0);
      $name = (string) ($row->name ?? '');
      if ($id > 0 && $name !== '') {
        $map[$id] = $name;
      }
    }

    return $map;
  }

  /**
   * @return array<int,\Drupal\taxonomy\TermInterface>
   */
  private function loadExistingTermsBySourceId(string $vid): array {
    /** @var \Drupal\taxonomy\TermInterface[] $terms */
    $terms = $this->termStorage->loadByProperties(['vid' => $vid]);

    $map = [];
    foreach ($terms as $term) {
      /** @var \Drupal\taxonomy\TermInterface $term */

      $sid_raw = (string) $term->get('field_source_id')->value;
      $sid = (int) $sid_raw;

      if ($sid <= 0) {
        continue;
      }

      if (isset($map[$sid])) {
        $this->logger->warning('Duplicate term field_source_id=@sid in vid=@vid (term ids: @a, @b)', [
          '@sid' => (string) $sid,
          '@vid' => $vid,
          '@a' => (string) $map[$sid]->id(),
          '@b' => (string) $term->id(),
        ]);
        continue;
      }

      $map[$sid] = $term;
    }

    return $map;
  }

}
