<?php

declare(strict_types=1);

namespace Drupal\bezport_contacts_main\Service;

interface ProcessorInterface {

  /**
   * Machine name of the processor (for stats keys/logging).
   */
  public function id(): string;

  /**
   * Human label for logs/output.
   */
  public function label(): string;

  /**
   * Executes one processing step.
   *
   * @return array{ok:bool, message:string, stats:array<string,int>}
   */
  public function process(ImportNotifierInterface $notifier): array;

}
