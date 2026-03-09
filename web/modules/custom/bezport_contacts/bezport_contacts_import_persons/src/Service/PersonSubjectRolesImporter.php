<?php

declare(strict_types=1);

namespace Drupal\bezport_contacts_import_persons\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\bezport_contacts_main\Service\ApiPageFetcher;
use Drupal\bezport_contacts_main\Service\ImportNotifierInterface;
use GuzzleHttp\ClientInterface;

final class PersonSubjectRolesImporter {

  private $logger;

  public function __construct(
    private readonly Connection $database,
    private readonly ApiPageFetcher $pageFetcher,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('bezport_contacts_import_persons');
  }

  /**
   * Imports relations for persons grouped by "people page".
   *
   * @param array<int, int[]> $person_ids_by_page
   *   Page => person IDs list.
   *
   * @return int
   *   Inserted relations count.
   */
  public function import(
    ClientInterface $client,
    string $token,
    array $person_ids_by_page,
    ImportNotifierInterface $notifier,
  ): int {
    $inserted = 0;

    /** @var array<int, int|string> $skipped_markers */
    $skipped_markers = [];

    foreach ($person_ids_by_page as $people_page => $person_ids) {
      if ($person_ids === []) {
        continue;
      }

      // API expects repeated "person[]" parameters (http_build_query handles it).
      $query = http_build_query(['person' => $person_ids]);

      // Force page=1 and huge itemsPerPage (as original controller).
      $path = sprintf('/api/person_subject_roles?%s&page=1&itemsPerPage=10000', $query);

      $data = $this->pageFetcher->fetchWithRetry(
        $client,
        $token,
        $path,
        'person_subject_roles',
        (int) $people_page,
        $notifier,
        $skipped_markers,
        3
      );

      // Pokud je [] => buď endpoint vrátil prázdno, nebo byl po retry SKIPPED.
      if ($data === []) {
        continue;
      }

      foreach ($data as $rel) {
        if (empty($rel['personId']) || empty($rel['roleId']) || empty($rel['subjectId'])) {
          continue;
        }

        $this->database->insert('bezport_contacts_persons_subjects_roles_rls')
          ->fields([
            'person_id',
            'role_id',
            'subject_id',
          ])
          ->values([
            (int) $rel['personId'],
            (int) $rel['roleId'],
            (int) $rel['subjectId'],
          ])
          ->execute();

        $inserted++;
      }
    }

    if ($skipped_markers !== []) {
      $this->logger->warning('person_subject_roles skipped markers: @m', [
        '@m' => implode(', ', array_map('strval', $skipped_markers)),
      ]);
    }

    return $inserted;
  }

}
