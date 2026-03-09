<?php

declare(strict_types=1);

namespace Drupal\bezport_contacts_main\Service;

/**
 * No-op notifier implementation.
 *
 * Used for cron or silent execution where no output is required.
 */
final class NullNotifier implements ImportNotifierInterface {

  /**
   * {@inheritdoc}
   */
  public function info(string $message): void {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function warning(string $message): void {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function error(string $message): void {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function success(string $message): void {
    // Do nothing.
  }

}
