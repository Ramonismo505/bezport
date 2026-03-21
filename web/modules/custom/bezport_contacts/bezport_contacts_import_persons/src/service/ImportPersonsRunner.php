<?php

declare(strict_types=1);

namespace Drupal\bezport_contacts_import_persons\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\bezport_contacts_main\Service\ControlRestApiAvailability;
use Drupal\bezport_contacts_main\Service\ImportNotifierInterface;
use Drupal\bezport_contacts_main\Service\RestApiClient;

final class ImportPersonsRunner {

  private $logger;

  public function __construct(
    private readonly Connection $database,
    private readonly RestApiClient $restApiClient,
    private readonly ControlRestApiAvailability $controlRas,
    private readonly PersonsImporter $personsImporter,
    private readonly PersonSubjectRolesImporter $personSubjectRolesImporter,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('bezport_contacts_import_persons');
  }

  /**
   * Runs the import (Persons + person_subject_roles relations).
   *
   * @param string $type
   *   cron|manual|drush
   *
   * @return array{ok:bool, message:string, stats:array<string, int>}
   */
  public function run(string $type, ImportNotifierInterface $notifier): array {
    $type = strtolower(trim($type));
    if (!in_array($type, ['cron', 'manual', 'drush'], TRUE)) {
      $type = 'manual';
    }

    try {
      $notifier->info('Checking REST API availability...');
      $ras = $this->controlRas->testRestApiServer();
      if ($ras !== TRUE) {
        $msg = (string) $ras;
        $notifier->error($msg);
        $this->logger->error('REST API not available: @msg', ['@msg' => $msg]);
        return ['ok' => FALSE, 'message' => $msg, 'stats' => []];
      }

      $notifier->info('Authenticating...');
      $auth = $this->restApiClient->createAuthenticatedClient();
      $client = $auth['client'];
      $token = $auth['token'];

      $this->truncateTables($notifier);

      $stats = [];

      // Imports persons and returns per-page person IDs to allow relation imports.
      $notifier->info('Importing persons...');
      $persons_result = $this->personsImporter->import($client, $token, $notifier);

      $stats['persons'] = (int) ($persons_result['inserted'] ?? 0);
      $stats['persons_pages'] = (int) ($persons_result['pages'] ?? 0);

      $notifier->info('Importing person/subject/role relations...');
      $stats['persons_subjects_roles_rls'] = $this->personSubjectRolesImporter->import(
        $client,
        $token,
        $persons_result['person_ids_by_page'] ?? [],
        $notifier
      );

      $notifier->success('Persons import completed.');
      return ['ok' => TRUE, 'message' => 'OK', 'stats' => $stats];
    }
    catch (\Throwable $e) {
      $this->logger->error('Import failed: @msg', ['@msg' => $e->getMessage()]);
      $notifier->error('Import failed: ' . $e->getMessage());
      return ['ok' => FALSE, 'message' => $e->getMessage(), 'stats' => []];
    }
  }

  private function truncateTables(ImportNotifierInterface $notifier): void {
    $notifier->info('Truncating temp tables...');

    foreach ([
      'bezport_contacts_persons',
      'bezport_contacts_persons_subjects_roles_rls',
    ] as $table) {
      $this->database->truncate($table)->execute();
    }

    $notifier->success('Truncate completed.');
  }

}
