<?php

declare(strict_types=1);

namespace Drupal\bezport_contacts_main\Commands;

use Drupal\bezport_contacts_main\Service\BezportContactsOrchestrator;
use Drupal\bezport_contacts_main\Service\ConsoleNotifier;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\UserSession;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Output\ConsoleOutput;

final class BezportContactsMainCommands extends DrushCommands {

  public function __construct(
    private readonly AccountSwitcherInterface $accountSwitcher,
    private readonly BezportContactsOrchestrator $orchestrator,
  ) {
    parent::__construct();
  }

  /**
   * Import data to temp tables.
   *
   * @command bezport_contacts:import
   * @aliases bc-import bc-i
   * @usage ddev drush bezport_contacts:import
   */
  public function import(): int {
    $notifier = new ConsoleNotifier(new ConsoleOutput());

    $this->accountSwitcher->switchTo(new UserSession(['uid' => 34]));

    try {
      $notifier->info('Starting Bezport contacts IMPORT (temp tables)...');

      // Import-only pipeline (no ImportStateManager tracking).
      $result = $this->orchestrator->import('drush', $notifier);

      foreach ($this->orchestrator->formatResultsForHtml($result) as $line) {
        $notifier->info(strip_tags($line));
      }

      if (!empty($result['ok'])) {
        $notifier->success('IMPORT finished OK.');
        return self::EXIT_SUCCESS;
      }

      $notifier->error('IMPORT finished with errors.');
      return self::EXIT_FAILURE;
    }
    catch (\Throwable $e) {
      $notifier->error('IMPORT exception: ' . $e->getMessage());
      return self::EXIT_FAILURE;
    }
    finally {
      $this->accountSwitcher->switchBack();
    }
  }

  /**
   * Process data.
   *
   * @command bezport_contacts:process
   * @aliases bc-process bc-p
   * @usage ddev drush bezport_contacts:process
   */
  public function process(): int {
    $notifier = new ConsoleNotifier(new ConsoleOutput());

    $this->accountSwitcher->switchTo(new UserSession(['uid' => 34]));

    try {
      $notifier->info('Starting Bezport contacts PROCESS (from temp tables)...');

      // Process-only pipeline (no ImportStateManager tracking).
      $result = $this->orchestrator->process('drush', $notifier);

      foreach ($this->orchestrator->formatResultsForHtml($result) as $line) {
        $notifier->info(strip_tags($line));
      }

      if (!empty($result['ok'])) {
        $notifier->success('PROCESS finished OK.');
        return self::EXIT_SUCCESS;
      }

      $notifier->error('PROCESS finished with errors.');
      return self::EXIT_FAILURE;
    }
    catch (\Throwable $e) {
      $notifier->error('PROCESS exception: ' . $e->getMessage());
      return self::EXIT_FAILURE;
    }
    finally {
      $this->accountSwitcher->switchBack();
    }
  }

  /**
   * Run all processes.
   *
   * @command bezport_contacts:run
   * @aliases bc-run bc-r
   * @usage ddev drush bezport_contacts:run
   */
  public function run(): int {
    $notifier = new ConsoleNotifier(new ConsoleOutput());

    $this->accountSwitcher->switchTo(new UserSession(['uid' => 34]));

    try {
      $notifier->info('Starting Bezport contacts FULL RUN (import + process)...');

      // Full run => ImportStateManager tracking ON.
      $result = $this->orchestrator->runAll('drush', $notifier, TRUE);

      foreach ($this->orchestrator->formatResultsForHtml($result) as $line) {
        $notifier->info(strip_tags($line));
      }

      if (!empty($result['ok'])) {
        $notifier->success('RUN finished OK.');
        return self::EXIT_SUCCESS;
      }

      $notifier->error('RUN finished with errors.');
      return self::EXIT_FAILURE;
    }
    catch (\Throwable $e) {
      $notifier->error('RUN exception: ' . $e->getMessage());
      return self::EXIT_FAILURE;
    }
    finally {
      $this->accountSwitcher->switchBack();
    }
  }

}
