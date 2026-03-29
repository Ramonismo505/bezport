<?php

declare(strict_types=1);

namespace Drupal\ra_sanitize_filenames;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Class SanitizeName.
 *
 * @package Drupal\ra_sanitize_filenames
 */
class SanitizeName {

  /**
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
  ) {}
  
  public function removableCharsArray(string $removable_chars): array {
    $removable_chars = trim($removable_chars);
    
    if ($removable_chars === '') {
      return [];
    }
    
    $pieces = explode(' ', $removable_chars);
    // Odstraní prázdné prvky a ořízne mezery
    return array_values(array_filter(array_map('trim', $pieces)));
  }
  
  public function getReplacementChar(string $replacement): string {
    return match ($replacement) {
      'remove' => '',
      'space' => ' ',
      'dash' => '-',
      'underscore' => '_',
      default => '',
    };
  }
  
  /**
   * Sanitize the file name.
   *
   * @param string $filename
   * The file name that will be sanitized.
   *
   * @return string
   * Sanitized file name.
   */
  public function sanitizeFilename(string $filename): string {
    $config = $this->configFactory->get('ra_sanitize_filenames.settings');
    $removable_chars = $config->get('removable_chars') ?? '';
    $replacement = $config->get('replacement') ?? 'remove';
    
    $removable_chars_array = $this->removableCharsArray((string) $removable_chars);
    
    if ($removable_chars_array !== []) {
      $replacement_char = $this->getReplacementChar((string) $replacement);
      $filename = str_replace($removable_chars_array, $replacement_char, $filename);
    }
    
    return $filename;
  }
}