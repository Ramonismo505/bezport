<?php

declare(strict_types=1);

namespace Drupal\bezport_contacts_process_persons\Service;

use Drupal\bezport_contacts_main\Service\ImportNotifierInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

final class ProcessPersonsRunner {

  private LoggerInterface $logger;

  public function __construct(
    private readonly PersonsProcessor $personsProcessor,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('bezport_contacts_process_persons');
  }

  /**
   * @return array{ok:bool, message:string, steps:array<string,mixed>}
   */
  public function run(string $type, ImportNotifierInterface $notifier): array {
    $steps = [];
    $all_ok = TRUE;

    $steps['persons'] = $this->personsProcessor->process($notifier);
    if (empty($steps['persons']['ok'])) {
      $all_ok = FALSE;
      $this->logger->error('PersonsProcessor failed: @msg', [
        '@msg' => (string) ($steps['persons']['message'] ?? 'Unknown error'),
      ]);
    }

    return [
      'ok' => $all_ok,
      'message' => $all_ok ? 'OK' : 'FAILED',
      'steps' => $steps,
    ];
  }

}
