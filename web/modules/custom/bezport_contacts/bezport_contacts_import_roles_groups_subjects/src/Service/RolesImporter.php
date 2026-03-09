<?php

declare(strict_types=1);

namespace Drupal\bezport_contacts_import_roles_groups_subjects\Service;

use Drupal\Core\Database\Connection;
use Drupal\bezport_contacts_main\Service\ApiPageFetcher;
use Drupal\bezport_contacts_main\Service\ImportNotifierInterface;

final class RolesImporter {

  public function __construct(
    private readonly Connection $database,
    private readonly ApiPageFetcher $fetcher,
  ) {}

  /**
   * @param object $client
   * @param string $token
   */
  public function import(object $client, string $token, ImportNotifierInterface $notifier): int {
    $notifier->info('Starting import Roles to Temp table.');

    $inserted = 0;
    $skipped_pages = [];

    $page = 1;
    do {
      $data = $this->fetcher->fetchWithRetry(
        $client,
        $token,
        '/api/roles?page=' . $page,
        'roles',
        $page,
        $notifier,
        $skipped_pages,
        3
      );

      foreach ($data as $role) {
        if (!is_array($role)) {
          continue;
        }
        $id = $role['id'] ?? NULL;
        $name = $role['name'] ?? '';

        if (empty($id) || $name === '') {
          continue;
        }

        $this->database->insert('bezport_contacts_roles')
          ->fields([
            'role_id' => (int) $id,
            'name' => (string) $name,
          ])
          ->execute();

        $inserted++;
      }

      $page++;
    } while ($data !== []);

    if ($skipped_pages !== []) {
      $notifier->warning('Roles: skipped pages: ' . implode(', ', $skipped_pages));
    }

    $notifier->success('Import Roles completed. Inserted: ' . $inserted);
    return $inserted;
  }

}
