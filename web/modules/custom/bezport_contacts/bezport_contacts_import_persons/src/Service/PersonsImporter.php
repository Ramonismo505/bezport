<?php

declare(strict_types=1);

namespace Drupal\bezport_contacts_import_persons\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\bezport_contacts_main\Service\ApiPageFetcher;
use Drupal\bezport_contacts_main\Service\ImportNotifierInterface;
use GuzzleHttp\ClientInterface;

final class PersonsImporter {

  private $logger;

  public function __construct(
    private readonly Connection $database,
    private readonly ApiPageFetcher $pageFetcher,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('bezport_contacts_import_persons');
  }

  /**
   * @return array{
   *   inserted:int,
   *   pages:int,
   *   person_ids_by_page:array<int, int[]>,
   *   skipped_pages:int[]
   * }
   */
  public function import(ClientInterface $client, string $token, ImportNotifierInterface $notifier): array {
    $inserted = 0;
    $page = 1;

    /** @var array<int, int[]> $person_ids_by_page */
    $person_ids_by_page = [];

    /** @var int[] $skipped_pages */
    $skipped_pages = [];

    while (TRUE) {
      $path = '/api/people?page=' . $page;

      $data = $this->pageFetcher->fetchWithRetry(
        $client,
        $token,
        $path,
        'people',
        $page,
        $notifier,
        $skipped_pages,
        3
      );

      // Konec stránkování: API vrací [].
      if ($data === []) {
        break;
      }

      $page_person_ids = [];

      foreach ($data as $person) {
        if (empty($person['id']) || empty($person['lastName'])) {
          continue;
        }

        $page_person_ids[] = (int) $person['id'];

        $this->database->insert('bezport_contacts_persons')
          ->fields([
            'person_id',
            'first_name',
            'last_name',
            'degree_before',
            'degree_after',
            'mobile',
            'phone',
            'fax',
            'email',
            'role',
            'street',
            'street_number',
            'street_number2',
            'region',
            'city',
          ])
          ->values([
            (int) $person['id'],
            (string) ($person['firstName'] ?? ''),
            (string) ($person['lastName'] ?? ''),
            (string) ($person['degreeBefore'] ?? ''),
            (string) ($person['degreeAfter'] ?? ''),
            (string) ($person['mobile'] ?? ''),
            (string) ($person['phone'] ?? ''),
            (string) ($person['fax'] ?? ''),
            (string) ($person['email'] ?? ''),
            (string) ($person['function'] ?? ''),
            (string) ($person['street'] ?? ''),
            (string) ($person['streetNumber'] ?? ''),
            (string) ($person['streetNumber2'] ?? ''),
            (string) ($person['region'] ?? ''),
            (string) ($person['city'] ?? ''),
          ])
          ->execute();

        $inserted++;
      }

      if ($page_person_ids !== []) {
        $person_ids_by_page[$page] = $page_person_ids;
      }

      $page++;
    }

    // pages = poslední úspěšně načtená stránka (page-1).
    return [
      'inserted' => $inserted,
      'pages' => $page - 1,
      'person_ids_by_page' => $person_ids_by_page,
      'skipped_pages' => $skipped_pages,
    ];
  }

}
