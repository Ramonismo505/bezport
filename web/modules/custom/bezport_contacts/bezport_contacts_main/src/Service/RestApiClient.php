<?php

declare(strict_types=1);

namespace Drupal\bezport_contacts_main\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;

final class RestApiClient {

  private LoggerInterface $logger;

  public function __construct(
    private readonly ClientFactory $httpClientFactory,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ControlRestApiAvailability $availability,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('bezport_contacts_api');
  }

  /**
   * @return array{client:object, token:string}
   */
  public function createAuthenticatedClient(): array {
    // 1) Nejprve ověř dostupnost REST API (vč. konfigurace).
    $ok = $this->availability->testRestApiServer();
    if ($ok !== true) {
      $this->logger->error('REST API unavailable: @msg', ['@msg' => (string) $ok]);
      throw new \RuntimeException((string) $ok);
    }

    // 2) Když je API dostupné, pokračujeme standardním auth.
    $config = $this->configFactory->get('bezport_contacts_main.settings');

    $username = (string) $config->get('username');
    $password = (string) $config->get('password');
    $base_uri = (string) $config->get('base_uri');

    if ($username === '' || $password === '' || $base_uri === '') {
      // Teoreticky už pokryto v availability testu, ale necháme jako pojistku.
      throw new \RuntimeException('Bezport API není nastaveno (username/password/base_uri).');
    }

    $client = $this->httpClientFactory->fromOptions([
      'base_uri' => $base_uri,
      'timeout' => 30,
      'connect_timeout' => 10,
      'http_errors' => false,
    ]);

    $response = $client->post('/authentication_token', [
      'headers' => ['Content-Type' => 'application/json'],
      RequestOptions::JSON => [
        'username' => $username,
        'password' => $password,
      ],
    ]);

    $status = $response->getStatusCode();
    $body = (string) $response->getBody();

    if ($status < 200 || $status >= 300) {
      $this->logger->error('Auth failed HTTP @status. Body: @body', [
        '@status' => (string) $status,
        '@body' => mb_substr($body, 0, 500),
      ]);
      throw new \RuntimeException('Authentication failed (HTTP ' . $status . ').');
    }

    if (!mb_check_encoding($body, 'UTF-8')) {
      $body = mb_convert_encoding($body, 'UTF-8', 'UTF-8');
    }

    $data = json_decode($body, true);
    $token = $data['token'] ?? '';

    if (!is_string($token) || $token === '') {
      $this->logger->error('Auth token missing/invalid. Body: @body', [
        '@body' => mb_substr($body, 0, 500),
      ]);
      throw new \RuntimeException('Nelze získat JWT token.');
    }

    return ['client' => $client, 'token' => $token];
  }

}
