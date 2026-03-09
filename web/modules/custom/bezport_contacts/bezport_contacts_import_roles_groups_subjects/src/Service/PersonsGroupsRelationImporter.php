<?php

declare(strict_types=1);

namespace Drupal\bezport_contacts_import_roles_groups_subjects\Service;

use Drupal\Core\Database\Connection;
use Drupal\bezport_contacts_main\Service\ApiPageFetcher;
use Drupal\bezport_contacts_main\Service\ImportNotifierInterface;

final class PersonsGroupsRelationImporter {

  public function __construct(
    private readonly Connection $database,
    private readonly ApiPageFetcher $fetcher,
  ) {}

  /**
   * @param object $client
   * @param string $token
   * @param int[] $group_ids
   */
  public function import(object $client, string $token, array $group_ids, ImportNotifierInterface $notifier): int {
    $notifier->info('Starting import Person / Groups relation to Temp table.');

    $inserted = 0;

    foreach ($group_ids as $group_id) {
      $group_id = (int) $group_id;
      if ($group_id <= 0) {
        continue;
      }

      $skipped_pages = [];
      $page = 1;

      do {
        $data = $this->fetcher->fetchWithRetry(
          $client,
          $token,
          '/api/person_groups?page=' . $page . '&group=' . $group_id,
          'person_groups(group=' . $group_id . ')',
          $page,
          $notifier,
          $skipped_pages,
          3
        );

        foreach ($data as $rel) {
          if (!is_array($rel)) {
            continue;
          }

          $person_id = $rel['personId'] ?? NULL;
          $gid = $rel['groupId'] ?? NULL;

          if (empty($person_id) || empty($gid)) {
            continue;
          }

          $this->database->insert('bezport_contacts_persons_groups_rls')
            ->fields([
              'person_id' => (int) $person_id,
              'group_id' => (int) $gid,
            ])
            ->execute();

          $inserted++;
        }

        $page++;
      } while ($data !== []);

      if ($skipped_pages !== []) {
        $notifier->warning('Person/Groups relation (group ' . $group_id . '): skipped pages: ' . implode(', ', $skipped_pages));
      }
    }

    $notifier->success('Import Person / Groups relation completed. Inserted: ' . $inserted);
    return $inserted;
  }

}
