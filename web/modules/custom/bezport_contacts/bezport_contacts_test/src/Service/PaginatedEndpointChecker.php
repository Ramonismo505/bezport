<?php

declare(strict_types=1);

namespace Drupal\bezport_contacts_test\Service;

use Drupal\bezport_contacts_main\Service\ApiPageFetcher;
use Drupal\bezport_contacts_main\Service\ControlRestApiAvailability;
use Drupal\bezport_contacts_main\Service\NullNotifier;
use Drupal\bezport_contacts_main\Service\RestApiClient;

/**
 * Real-time checker of paginated REST API endpoints (page=1..N).
 *
 * This is test-only logic (bezport_contacts_test).
 */
final class PaginatedEndpointChecker {

  private const END_AFTER_EMPTY_PAGES = 3;
  private const HARD_PAGE_LIMIT = 5000;
  private const RETRIES = 3;

  public function __construct(
    private readonly ControlRestApiAvailability $controlRas,
    private readonly RestApiClient $restApiClient,
    private readonly ApiPageFetcher $pageFetcher,
  ) {}

  /**
   * @param string[] $selected
   *   List of endpoint keys: roles, groups, subjects, persons.
   *
   * @return array{
   *   ok: bool,
   *   message: string,
   *   results: array<string, array{
   *     label: string,
   *     total_pages: int,
   *     missing_pages: int[],
   *     missing_count: int,
   *     note: string
   *   }>
   * }
   */
  public function check(array $selected): array {
    $selected = array_values(array_unique(array_filter($selected, 'is_string')));

    if ($selected === []) {
      return [
        'ok' => false,
        'message' => 'Není vybrán žádný endpoint ke kontrole.',
        'results' => [],
      ];
    }

    // 1) Quick availability check.
    $ras = $this->controlRas->testRestApiServer();
    if ($ras !== TRUE) {
      return [
        'ok' => false,
        'message' => (string) $ras,
        'results' => [],
      ];
    }

    // 2) Auth (client + token).
    try {
      $auth = $this->restApiClient->createAuthenticatedClient();
      $client = $auth['client'];
      $token = $auth['token'];
    }
    catch (\Throwable $e) {
      return [
        'ok' => false,
        'message' => 'Nelze získat JWT token: ' . $e->getMessage(),
        'results' => [],
      ];
    }

    $endpoints = $this->getEndpointsMap();

    $notifier = new NullNotifier();
    $results = [];

    foreach ($selected as $key) {
      if (!isset($endpoints[$key])) {
        continue;
      }

      $label = $endpoints[$key]['label'];
      $prefix = $endpoints[$key]['path_prefix'];
      $context = $endpoints[$key]['context'];

      $skippedMarkers = [];
      $missingPages = [];

      $page = 1;
      $lastDataPage = 0;
      $emptyStreak = 0;

      while (true) {
        if ($page > self::HARD_PAGE_LIMIT) {
          // Safety guard: endpoint never ends (or API behaves unexpectedly).
          break;
        }

        $path = $prefix . $page;

        $data = $this->pageFetcher->fetchWithRetry(
          $client,
          $token,
          $path,
          $context,
          $page,
          $notifier,
          $skippedMarkers,
          self::RETRIES
        );

        // If fetcher skipped this marker after retries, count it as missing.
        if (in_array($page, $skippedMarkers, true)) {
          $missingPages[] = $page;
          $page++;
          continue;
        }

        // Empty array => no items; count empties to determine the end.
        if ($data === []) {
          $emptyStreak++;
          if ($emptyStreak >= self::END_AFTER_EMPTY_PAGES) {
            break;
          }
          $page++;
          continue;
        }

        // Non-empty data.
        $emptyStreak = 0;
        $lastDataPage = $page;
        $page++;
      }

      $totalPages = $lastDataPage;

      $results[$key] = [
        'label' => $label,
        'total_pages' => $totalPages,
        'missing_pages' => $missingPages,
        'missing_count' => count($missingPages),
        'note' => ($page > self::HARD_PAGE_LIMIT)
          ? 'Pozor: dosažen HARD_PAGE_LIMIT (' . self::HARD_PAGE_LIMIT . ').'
          : '',
      ];
    }

    return [
      'ok' => true,
      'message' => '',
      'results' => $results,
    ];
  }

  /**
   * @return array<string, array{label: string, path_prefix: string, context: string}>
   */
  public function getEndpointsMap(): array {
    return [
      'roles' => [
        'label' => 'Role',
        'path_prefix' => '/api/roles?page=',
        'context' => 'roles',
      ],
      'groups' => [
        'label' => 'Skupiny',
        'path_prefix' => '/api/groups?page=',
        'context' => 'groups',
      ],
      'subjects' => [
        'label' => 'Subjekty',
        'path_prefix' => '/api/subjects?page=',
        'context' => 'subjects',
      ],
      'persons' => [
        'label' => 'Osoby',
        'path_prefix' => '/api/people?page=',
        'context' => 'people',
      ],
    ];
  }

}
