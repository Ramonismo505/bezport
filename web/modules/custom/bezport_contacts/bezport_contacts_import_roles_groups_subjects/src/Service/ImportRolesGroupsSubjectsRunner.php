<?php

declare(strict_types=1);

namespace Drupal\bezport_contacts_import_roles_groups_subjects\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\bezport_contacts_main\Service\ControlRestApiAvailability;
use Drupal\bezport_contacts_main\Service\ImportNotifierInterface;
use Drupal\bezport_contacts_main\Service\RestApiClient;

final class ImportRolesGroupsSubjectsRunner {

  private $logger;

  public function __construct(
    private readonly Connection $database,
    private readonly RestApiClient $restApiClient,
    private readonly ControlRestApiAvailability $controlRas,
    private readonly RolesImporter $rolesImporter,
    private readonly GroupsImporter $groupsImporter,
    private readonly PersonsGroupsRelationImporter $relationImporter,
    private readonly SubjectsImporter $subjectsImporter,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('bezport_contacts_import_rgs');
  }

  /**
   * Runs the full import (Roles + Groups + Relations + Subjects).
   *
   * @param string $type
   *   cron|manual|drush
   *
   * @return array{ok:bool, message:string, stats:array<string, int>}
   */
  public function run(string $type, ImportNotifierInterface $notifier): array {
    $type = strtolower(trim($type));
    if (!in_array($type, ['cron', 'manual', 'drush'], true)) {
      $type = 'manual';
    }

    try {
      $notifier->info('Checking REST API availability...');
      $ras = $this->controlRas->testRestApiServer();
      if ($ras !== TRUE) {
        $msg = (string) $ras;
        $notifier->error($msg);
        $this->logger->error('REST API not available: @msg', ['@msg' => $msg]);
        return ['ok' => false, 'message' => $msg, 'stats' => []];
      }

      $notifier->info('Authenticating...');
      $auth = $this->restApiClient->createAuthenticatedClient();
      $client = $auth['client'];
      $token = $auth['token'];

      $this->truncateTables($notifier);

      $stats = [];

      $stats['roles'] = $this->rolesImporter->import($client, $token, $notifier);

      $groups = $this->groupsImporter->import($client, $token, $notifier);
      $stats['groups'] = (int) ($groups['inserted'] ?? 0);

      $stats['persons_groups_rls'] = $this->relationImporter->import(
        $client,
        $token,
        $groups['group_ids'] ?? [],
        $notifier
      );

      $stats['subjects'] = $this->subjectsImporter->import($client, $token, $notifier);

      $notifier->success('RGS import completed.');
      return ['ok' => true, 'message' => 'OK', 'stats' => $stats];
    }
    catch (\Throwable $e) {
      $this->logger->error('Import failed: @msg', ['@msg' => $e->getMessage()]);
      $notifier->error('Import failed: ' . $e->getMessage());
      return ['ok' => false, 'message' => $e->getMessage(), 'stats' => []];
    }
  }

  private function truncateTables(ImportNotifierInterface $notifier): void {
    $notifier->info('Truncating temp tables...');

    foreach ([
      'bezport_contacts_roles',
      'bezport_contacts_groups',
      'bezport_contacts_subjects',
      'bezport_contacts_persons_groups_rls',
    ] as $table) {
      $this->database->truncate($table)->execute();
    }

    $notifier->success('Truncate completed.');
  }

}
