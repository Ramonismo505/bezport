<?php

declare(strict_types=1);

namespace Drupal\bezport_contacts_main\Service;

use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Console notifier implementation.
 *
 * Used for Drush / CLI execution.
 */
final class ConsoleNotifier implements ImportNotifierInterface {

  public function __construct(
    private readonly ConsoleOutput $console,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function info(string $message): void {
    $this->console->writeln('<info>' . $message . '</info>');
  }

  /**
   * {@inheritdoc}
   */
  public function warning(string $message): void {
    $this->console->writeln('<comment>' . $message . '</comment>');
  }

  /**
   * {@inheritdoc}
   */
  public function error(string $message): void {
    $this->console->writeln('<error>' . $message . '</error>');
  }

  /**
   * {@inheritdoc}
   */
  public function success(string $message): void {
    // Symfony nemá vestavěný "success", použijeme zelený text.
    $this->console->writeln('<fg=green>' . $message . '</>');
  }

}
