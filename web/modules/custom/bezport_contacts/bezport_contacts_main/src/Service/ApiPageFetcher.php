<?php

declare(strict_types=1);

namespace Drupal\bezport_contacts_main\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

final class ApiPageFetcher {

  private LoggerInterface $logger;

  public function __construct(
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('bezport_contacts_fetcher');
  }

  /**
   * Fetch JSON array with retry; after retries => skip.
   *
   * @param array<int,int|string> $skippedMarkers
   * @return array<int,mixed>
   */
  public function fetchWithRetry(
    $client,
    string $token,
    string $path,
    string $context,
    int|string $pageMarker,
    ImportNotifierInterface $notifier,
    array &$skippedMarkers,
    int $retries = 3,
  ): array {
    for ($attempt = 1; $attempt <= $retries; $attempt++) {
      $data = $this->attempt($client, $token, $path, $context, $pageMarker, $attempt, $notifier);
      if ($data !== null) {
        return $data;
      }
      usleep(200000);
    }

    $skippedMarkers[] = $pageMarker;
    $msg = "SKIPPED {$context} marker {$pageMarker} after retries ({$path})";
    $this->logger->error($msg);
    $notifier->error($msg);

    return [];
  }

  /**
   * @return array<int,mixed>|null  null => retry
   */
  private function attempt(
    $client,
    string $token,
    string $path,
    string $context,
    int|string $pageMarker,
    int $attempt,
    ImportNotifierInterface $notifier,
  ): ?array {
    try {
      $response = $client->get($path, [
        'headers' => [
          'accept' => 'application/json',
          'Authorization' => 'Bearer ' . $token,
        ],
        'timeout' => 30,
        'connect_timeout' => 10,
        'http_errors' => false,
      ]);

      $status = $response->getStatusCode();
      $body = (string) $response->getBody();

      if ($status < 200 || $status >= 300) {
        $this->logger->warning('HTTP @status @ctx marker=@m attempt=@a path=@path body=@body', [
          '@status' => (string) $status,
          '@ctx' => $context,
          '@m' => (string) $pageMarker,
          '@a' => (string) $attempt,
          '@path' => $path,
          '@body' => mb_substr($body, 0, 500),
        ]);
        $notifier->warning("Retry {$context} {$pageMarker} (HTTP {$status}) attempt {$attempt}");
        return null;
      }

      if (!mb_check_encoding($body, 'UTF-8')) {
        $this->logger->warning('Invalid UTF-8 @ctx marker=@m attempt=@a path=@path', [
          '@ctx' => $context,
          '@m' => (string) $pageMarker,
          '@a' => (string) $attempt,
          '@path' => $path,
        ]);
        $body = mb_convert_encoding($body, 'UTF-8', 'UTF-8');
      }

      $data = json_decode($body, true);

      if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        $this->logger->warning('JSON decode failed @ctx marker=@m attempt=@a err=@err body=@body', [
          '@ctx' => $context,
          '@m' => (string) $pageMarker,
          '@a' => (string) $attempt,
          '@err' => json_last_error_msg(),
          '@body' => mb_substr($body, 0, 500),
        ]);
        $notifier->warning("Retry {$context} {$pageMarker} (JSON decode) attempt {$attempt}");
        return null;
      }

      return $data;
    }
    catch (GuzzleException $e) {
      $this->logger->warning('Request failed @ctx marker=@m attempt=@a path=@path msg=@msg', [
        '@ctx' => $context,
        '@m' => (string) $pageMarker,
        '@a' => (string) $attempt,
        '@path' => $path,
        '@msg' => $e->getMessage(),
      ]);
      $notifier->warning("Retry {$context} {$pageMarker} (exception) attempt {$attempt}");
      return null;
    }
  }

}
