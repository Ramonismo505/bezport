<?php

declare(strict_types=1);

namespace Drupal\bezport_contacts_main\Service;

interface ImportNotifierInterface {

  public function info(string $message): void;

  public function warning(string $message): void;

  public function error(string $message): void;

  public function success(string $message): void;
}
