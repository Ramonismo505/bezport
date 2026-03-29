<?php

declare(strict_types=1);

namespace Drupal\ra_sanitize_filenames\EventSubscriber;

use Drupal\Core\File\Event\FileUploadSanitizeNameEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\ra_sanitize_filenames\SanitizeName;

/**
 * Class FileUploadSubscriber.
 */
class FileUploadSubscriber implements EventSubscriberInterface {
  
  /**
   * @param \Drupal\ra_sanitize_filenames\SanitizeName $sanitizeName
   */
  public function __construct(
    private readonly SanitizeName $sanitizeName,
  ) {}
  
  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      FileUploadSanitizeNameEvent::class => [['raSanitizeName', -100]],
    ];
  }
  
  /**
   * Custom sanitization the upload's filename.
   *
   * @param \Drupal\Core\File\Event\FileUploadSanitizeNameEvent $event
   * File upload sanitize name event.
   */
  public function raSanitizeName(FileUploadSanitizeNameEvent $event): void {
    $filename = $event->getFilename();
    $sanitized_filename = $this->sanitizeName->sanitizeFilename($filename);
    $event->setFilename($sanitized_filename);
  }
}