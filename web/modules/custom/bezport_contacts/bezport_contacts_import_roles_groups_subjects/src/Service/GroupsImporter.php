<?php

declare(strict_types=1);

namespace Drupal\bezport_contacts_import_roles_groups_subjects\Service;

use Drupal\Core\Database\Connection;
use Drupal\bezport_contacts_main\Service\ApiPageFetcher;
use Drupal\bezport_contacts_main\Service\ImportNotifierInterface;

final class GroupsImporter {

  public function __construct(
    private readonly Connection $database,
    private readonly ApiPageFetcher $fetcher,
  ) {}

  /**
   * @param object $client
   * @param string $token
   *
   * @return array{inserted:int, group_ids:int[]}
   */
  public function import(object $client, string $token, ImportNotifierInterface $notifier): array {
    $notifier->info('Starting import Groups to Temp table.');

    $inserted = 0;
    $group_ids = [];
    $skipped_pages = [];

    $page = 1;
    do {
      $data = $this->fetcher->fetchWithRetry(
        $client,
        $token,
        '/api/groups?page=' . $page,
        'groups',
        $page,
        $notifier,
        $skipped_pages,
        3
      );

      foreach ($data as $group) {
        if (!is_array($group)) {
          continue;
        }
        $id = $group['id'] ?? NULL;
        $name = $group['name'] ?? '';

        if (empty($id) || $name === '') {
          continue;
        }

        $id = (int) $id;
        $group_ids[] = $id;

        $this->database->insert('bezport_contacts_groups')
          ->fields([
            'group_id' => $id,
            'name' => (string) $name,
          ])
          ->execute();

        $inserted++;
      }

      $page++;
    } while ($data !== []);

    $group_ids = array_values(array_unique($group_ids));

    if ($skipped_pages !== []) {
      $notifier->warning('Groups: skipped pages: ' . implode(', ', $skipped_pages));
    }

    $notifier->success('Import Groups completed. Inserted: ' . $inserted);
    return [
      'inserted' => $inserted,
      'group_ids' => $group_ids,
    ];
  }

}
