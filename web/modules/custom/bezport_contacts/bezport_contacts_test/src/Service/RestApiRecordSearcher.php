<?php

declare(strict_types=1);

namespace Drupal\bezport_contacts_test\Service;

use Drupal\bezport_contacts_main\Service\ApiPageFetcher;
use Drupal\bezport_contacts_main\Service\ControlRestApiAvailability;
use Drupal\bezport_contacts_main\Service\NullNotifier;
use Drupal\bezport_contacts_main\Service\RestApiClient;

/**
 * Real-time search in REST API (test-only).
 */
final class RestApiRecordSearcher {

  public function __construct(
    private readonly ControlRestApiAvailability $controlRas,
    private readonly RestApiClient $restApiClient,
    private readonly ApiPageFetcher $pageFetcher,
    private readonly PaginatedEndpointChecker $endpointChecker,
  ) {}

  /**
   * @return array {
   *   ok: bool,
   *   message: string,
   *   endpoint_label: string,
   *   query_param: string,
   *   query_value: string,
   *   items: array<int, mixed>
   * }
   */
  public function search(string $endpointKey, string $term): array {
    $term = trim($term);

    $map = $this->endpointChecker->getEndpointsMap();
    if (!isset($map[$endpointKey])) {
      return [
        'ok' => false,
        'message' => 'Není vybrán platný endpoint.',
        'endpoint_label' => '',
        'query_param' => '',
        'query_value' => $term,
        'items' => [],
      ];
    }

    if ($term === '') {
      return [
        'ok' => false,
        'message' => 'Není zadán hledaný výraz.',
        'endpoint_label' => $map[$endpointKey]['label'],
        'query_param' => '',
        'query_value' => $term,
        'items' => [],
      ];
    }

    // 1) Quick availability check.
    $ras = $this->controlRas->testRestApiServer();
    if ($ras !== TRUE) {
      return [
        'ok' => false,
        'message' => (string) $ras,
        'endpoint_label' => $map[$endpointKey]['label'],
        'query_param' => '',
        'query_value' => $term,
        'items' => [],
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
        'endpoint_label' => $map[$endpointKey]['label'],
        'query_param' => '',
        'query_value' => $term,
        'items' => [],
      ];
    }

    // Query param mapping:
    // roles/groups/subjects => name
    // persons => lastName
    $query_param = ($endpointKey === 'persons') ? 'lastName' : 'name';

    // We query page=1 + filter param.
    $query = http_build_query([
      'page' => 1,
      $query_param => $term,
    ]);

    // Convert "/api/subjects?page=" => "/api/subjects"
    $path_prefix = $map[$endpointKey]['path_prefix'];
    $base_path = strstr($path_prefix, '?page=', true);
    if ($base_path === false) {
      // Fallback – should not happen with our map.
      $base_path = rtrim($path_prefix, '=');
    }

    $path = $base_path . '?' . $query;

    $notifier = new NullNotifier();
    $skipped = [];

    // Reuse fetcher retry/utf8/json guards.
    $data = $this->pageFetcher->fetchWithRetry(
      $client,
      $token,
      $path,
      'search_' . $endpointKey,
      1,
      $notifier,
      $skipped,
      3
    );

    // If even search page was skipped after retries, treat as error.
    if ($skipped !== []) {
      return [
        'ok' => false,
        'message' => 'Dotaz se nepodařilo načíst ani po opakování (retry).',
        'endpoint_label' => $map[$endpointKey]['label'],
        'query_param' => $query_param,
        'query_value' => $term,
        'items' => [],
      ];
    }

    if (!is_array($data) || $data === []) {
      return [
        'ok' => true,
        'message' => 'Nenalezeno.',
        'endpoint_label' => $map[$endpointKey]['label'],
        'query_param' => $query_param,
        'query_value' => $term,
        'items' => [],
      ];
    }

    return [
      'ok' => true,
      'message' => 'Nalezeno záznamů: ' . count($data),
      'endpoint_label' => $map[$endpointKey]['label'],
      'query_param' => $query_param,
      'query_value' => $term,
      'items' => $data,
    ];
  }

}
