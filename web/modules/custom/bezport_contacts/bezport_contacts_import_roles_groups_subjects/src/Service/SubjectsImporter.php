<?php

declare(strict_types=1);

namespace Drupal\bezport_contacts_import_roles_groups_subjects\Service;

use Drupal\Core\Database\Connection;
use Drupal\bezport_contacts_main\Service\ApiPageFetcher;
use Drupal\bezport_contacts_main\Service\ImportNotifierInterface;

final class SubjectsImporter {

  public function __construct(
    private readonly Connection $database,
    private readonly ApiPageFetcher $fetcher,
  ) {}

  /**
   * @param object $client
   * @param string $token
   */
  public function import(object $client, string $token, ImportNotifierInterface $notifier): int {
    $notifier->info('Starting import Subjects to Temp table.');

    $inserted = 0;
    $skipped_pages = [];

    $page = 1;
    do {
      $data = $this->fetcher->fetchWithRetry(
        $client,
        $token,
        '/api/subjects?page=' . $page,
        'subjects',
        $page,
        $notifier,
        $skipped_pages,
        3
      );

      foreach ($data as $subject) {
        if (!is_array($subject)) {
          continue;
        }

        $id = $subject['id'] ?? NULL;
        $name = $subject['name'] ?? '';

        if (empty($id) || $name === '') {
          continue;
        }

        $emails = is_array($subject['emails'] ?? NULL) ? $subject['emails'] : [];
        $mobile = is_array($subject['mobilePhones'] ?? NULL) ? $subject['mobilePhones'] : [];
        $land = is_array($subject['landlines'] ?? NULL) ? $subject['landlines'] : [];

        $emails_serialized = $emails ? serialize($emails) : '';
        $mobile_serialized = $mobile ? serialize($mobile) : '';
        $land_serialized = $land ? serialize($land) : '';

        $this->database->insert('bezport_contacts_subjects')
          ->fields([
            'subject_id' => (int) $id,
            'name' => (string) $name,
            'street' => (string) ($subject['street'] ?? ''),
            'street_number' => (string) ($subject['streetNumber'] ?? ''),
            'municipality' => (string) ($subject['municipality'] ?? ''),
            'municipality_region' => (string) ($subject['municipalityRegion'] ?? ''),
            'zip' => (string) ($subject['zip'] ?? ''),
            'whole_address' => (string) ($subject['wholeAddress'] ?? ''),
            'emails_serialized' => $emails_serialized,
            'emails' => $emails ? implode('; ', $emails) : '',
            'mobile_phones_serialized' => $mobile_serialized,
            'mobile_phones' => $mobile ? implode('; ', $mobile) : '',
            'landlines_serialized' => $land_serialized,
            'landlines' => $land ? implode('; ', $land) : '',
            'group' => (string) ($subject['group'] ?? ''),
            'company_id' => (string) ($subject['companyID'] ?? ''),
          ])
          ->execute();

        $inserted++;
      }

      $page++;
    } while ($data !== []);

    if ($skipped_pages !== []) {
      $notifier->warning('Subjects: skipped pages: ' . implode(', ', $skipped_pages));
    }

    $notifier->success('Import Subjects completed. Inserted: ' . $inserted);
    return $inserted;
  }

}
