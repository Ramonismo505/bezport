<?php

declare(strict_types=1);

namespace Drupal\Tests\ra_sanitize_filenames\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\ra_sanitize_filenames\SanitizeName;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Testuje logiku sanitizace názvů souborů.
 *
 * @coversDefaultClass \Drupal\ra_sanitize_filenames\SanitizeName
 * @group ra_sanitize_filenames
 */
class SanitizeNameTest extends UnitTestCase {

  private SanitizeName $sanitizeName;
  private ConfigFactoryInterface|MockObject $configFactoryMock;

  /**
   * Tato metoda se spustí před každým jednotlivým testem.
   * Slouží k přípravě prostředí.
   */
  protected function setUp(): void {
    parent::setUp();

    // 1. Vytvoříme "falešnou" službu pro ConfigFactory.
    $this->configFactoryMock = $this->createMock(ConfigFactoryInterface::class);
    
    // 2. Předáme ji naší třídě přesně tak, jak by to udělal autowiring v Drupalu.
    $this->sanitizeName = new SanitizeName($this->configFactoryMock);
  }

  /**
   * Testuje, zda metoda správně rozbíjí řetězec na pole znaků.
   *
   * @covers ::removableCharsArray
   */
  public function testRemovableCharsArray(): void {
    $this->assertSame([], $this->sanitizeName->removableCharsArray(''));
    $this->assertSame(['a', 'b', 'c'], $this->sanitizeName->removableCharsArray('a b c'));
    // Testujeme i to, zda ignoruje vícenásobné mezery.
    $this->assertSame(['x', 'y'], $this->sanitizeName->removableCharsArray('  x   y  '));
  }

  /**
   * Testuje překlad zástupných slov na reálné znaky.
   *
   * @covers ::getReplacementChar
   */
  public function testGetReplacementChar(): void {
    $this->assertSame('', $this->sanitizeName->getReplacementChar('remove'));
    $this->assertSame(' ', $this->sanitizeName->getReplacementChar('space'));
    $this->assertSame('-', $this->sanitizeName->getReplacementChar('dash'));
    $this->assertSame('_', $this->sanitizeName->getReplacementChar('underscore'));
    // Test fallbacku z match výrazu.
    $this->assertSame('', $this->sanitizeName->getReplacementChar('neexistuje'));
  }

  /**
   * Testuje hlavní metodu s využitím mockované konfigurace.
   *
   * @covers ::sanitizeFilename
   */
  public function testSanitizeFilename(): void {
    // Vytvoříme falešný konfigurační objekt.
    $configMock = $this->createMock(ImmutableConfig::class);
    
    // Nanonfigurujeme ho tak, aby při volání ->get() vrátil naše testovací data.
    $configMock->method('get')->willReturnCallback(function (string $key) {
      return match ($key) {
        'removable_chars' => 'a b c',
        'replacement' => 'dash',
        default => null,
      };
    });

    // Falešné ConfigFactory řekneme, ať při požadavku vrátí náš falešný config.
    $this->configFactoryMock->method('get')
      ->with('ra_sanitize_filenames.settings')
      ->willReturn($configMock);

    // a, b, c chceme nahradit pomlčkou (-).
    $filename = 'abracadabra.txt';
    $expected = '--r---d--r-.txt';

    $this->assertSame($expected, $this->sanitizeName->sanitizeFilename($filename));
  }
}