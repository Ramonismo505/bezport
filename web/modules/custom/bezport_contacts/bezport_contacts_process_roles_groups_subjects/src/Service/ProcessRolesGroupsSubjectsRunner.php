<?php

declare(strict_types=1);

namespace Drupal\bezport_contacts_process_roles_groups_subjects\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\bezport_contacts_main\Service\ImportNotifierInterface;
use Psr\Log\LoggerInterface;

final class ProcessRolesGroupsSubjectsRunner {

  private LoggerInterface $logger;

  /**
   * Sem budeme postupně přidávat další procesory (SubjectsProcessor...).
   */
  public function __construct(
    private readonly RolesProcessor $rolesProcessor,
    private readonly GroupsProcessor $groupsProcessor,
    private readonly SubjectsGroupsProcessor $subjectsGroupsProcessor,
    private readonly SubjectsProcessor $subjectsProcessor,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('bezport_contacts_process_rgs');
  }

  /**
   * Runs process pipeline in this module.
   *
   * @param string $type
   *   cron|manual|drush
   *
   * @return array{ok:bool, message:string, steps:array<string,mixed>}
   */
  public function run(string $type, ImportNotifierInterface $notifier): array {
    $type = strtolower(trim($type));
    if (!in_array($type, ['cron', 'manual', 'drush'], TRUE)) {
      $type = 'manual';
    }

    $steps = [];
    $all_ok = TRUE;

    // 1) Roles.
    $steps['roles'] = $this->rolesProcessor->process($notifier);
    if (empty($steps['roles']['ok'])) {
      $all_ok = FALSE;
      $this->logger->error('RolesProcessor failed: @msg', [
        '@msg' => (string) ($steps['roles']['message'] ?? 'Unknown error'),
      ]);
    }

    // 2) Groups.
    $steps['groups'] = $this->groupsProcessor->process($notifier);
    if (empty($steps['groups']['ok'])) {
      $all_ok = FALSE;
      $this->logger->error('GroupsProcessor failed: @msg', [
        '@msg' => (string) ($steps['groups']['message'] ?? 'Unknown error'),
      ]);
    }

    // 3) Subjects / Groups release.
    $steps['subjects_groups'] = $this->subjectsGroupsProcessor->process($notifier);
    if (empty($steps['subjects_groups']['ok'])) {
      $all_ok = FALSE;
      $this->logger->error('SubjectsGroupsProcessor failed: @msg', [
        '@msg' => (string) ($steps['subjects_groups']['message'] ?? 'Unknown error'),
      ]);
    }

    // 4) Subjects
    $steps['subjects'] = $this->subjectsProcessor->process($notifier);
    if (empty($steps['subjects']['ok'])) {
      $all_ok = FALSE;
      $this->logger->error('SubjectsProcessor failed: @msg', [
        '@msg' => (string) ($steps['subjects']['message'] ?? 'Unknown error'),
      ]);
    }

    return [
      'ok' => $all_ok,
      'message' => $all_ok ? 'OK' : 'FAILED',
      'steps' => $steps,
    ];
  }

}
