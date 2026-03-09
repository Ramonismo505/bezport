<?php

namespace Drupal\bezport_contacts_main\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Http\ClientFactory;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;

/**
 * Service for REST API availability check.
 */
final class ControlRestApiAvailability {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ClientFactory $httpClientFactory,
  ) {}

  /**
   * Tests REST API server availability.
   *
   * @return bool|string
   *   TRUE if OK, otherwise error message.
   */
  public function testRestApiServer(): bool|string {

    $config = $this->configFactory->get('bezport_contacts_main.settings');

    $username = (string) $config->get('username');
    $password = (string) $config->get('password');
    $base_uri = (string) $config->get('base_uri');

    if ($username === '' || $password === '' || $base_uri === '') {
      return 'Chybí konfigurace (username/password/base_uri).';
    }

    $client = $this->httpClientFactory->fromOptions([
      'base_uri' => $base_uri,
      'timeout' => 5,
      'connect_timeout' => 3,
    ]);

    try {
      $response = $client->post('/authentication_token', [
        'headers' => ['Content-Type' => 'application/json'],
        RequestOptions::JSON => [
          'username' => $username,
          'password' => $password,
        ],
      ]);

      $code = $response->getStatusCode();

      if ($code === 200) {
        return TRUE;
      }

      return 'Chyba ' . $code . ' na Rest Api Serveru';
    }
    catch (GuzzleException $e) {

      $code = NULL;

      // Místo method_exists zkontrolujeme, zda je to RequestException
      // a zda skutečně obsahuje response objekt.
      if ($e instanceof RequestException && $e->hasResponse()) {
        $code = $e->getResponse()->getStatusCode();
      }

      if ($code !== NULL) {
        return 'Chyba ' . $code . ' na Rest Api Serveru';
      }

      // Timeout / DNS / connect error a další chyby bez odpovědi
      return 'Nelze se připojit k Rest Api Serveru: ' . $e->getMessage();
    }
  }

}
