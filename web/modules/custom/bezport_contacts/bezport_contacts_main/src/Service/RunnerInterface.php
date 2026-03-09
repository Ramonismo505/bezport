<?php

declare(strict_types=1);

namespace Drupal\bezport_contacts_main\Service;

interface RunnerInterface {

  /**
   * Execute the runner.
   *
   * @param string $type
   *   cron|manual|drush
   *
   * @return array{ok:bool, message:string, stats:array<string, mixed>}
   */
  public function run(string $type, ImportNotifierInterface $notifier): array;

}
