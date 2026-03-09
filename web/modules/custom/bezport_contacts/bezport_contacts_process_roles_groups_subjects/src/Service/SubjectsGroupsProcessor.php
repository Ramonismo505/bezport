<?php

declare(strict_types=1);

namespace Drupal\bezport_contacts_process_roles_groups_subjects\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\bezport_contacts_main\Service\ImportNotifierInterface;
use Drupal\bezport_contacts_main\Service\ProcessorInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy\TermStorageInterface;
use Psr\Log\LoggerInterface;

final class SubjectsGroupsProcessor implements ProcessorInterface {

  private TermStorageInterface $termStorage;

  private LoggerInterface $logger;

  public function __construct(
    private readonly Connection $database,
    EntityTypeManagerInterface $entityTypeManager,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    /** @var \Drupal\taxonomy\TermStorageInterface $storage */
    $storage = $entityTypeManager->getStorage('taxonomy_term');
    $this->termStorage = $storage;

    $this->logger = $loggerFactory->get('bezport_contacts_process_rgs');
  }

  public function id(): string {
    return 'subjects_groups';
  }

  public function label(): string {
    return 'Subjects Groups';
  }

  /**
   * @return array{ok:bool, message:string, stats:array<string,int>}
   */
  public function process(ImportNotifierInterface $notifier): array {
    $vid = 'bezport_contacts_subjects_groups';

    $stats = [
      'new' => 0,
      'deleted' => 0,
      'source_total' => 0,
    ];

    try {
      $notifier->info('Subjects Groups processing starts.');

      $source_names = $this->loadDistinctGroupNamesFromTemp();
      $stats['source_total'] = count($source_names);

      $existing = $this->loadExistingTermsByName($vid);

      $new_names = array_diff($source_names, $existing);
      $obsolete_names = array_diff($existing, $source_names);

      // NEW
      foreach ($new_names as $name) {
        $name = trim((string) $name);
        if ($name === '') {
          continue;
        }

        $term = $this->termStorage->create([
          'vid' => $vid,
          'name' => $name,
        ]);
        $term->save();
        $stats['new']++;
      }

      // DELETE obsolete (by name)
      foreach ($obsolete_names as $name) {
        $name = (string) $name;
        if ($name === '') {
          continue;
        }

        /** @var \Drupal\taxonomy\TermInterface[] $terms */
        $terms = $this->termStorage->loadByProperties([
          'vid' => $vid,
          'name' => $name,
        ]);

        foreach ($terms as $term) {
          /** @var \Drupal\taxonomy\TermInterface $term */
          try {
            $term->delete();
            $stats['deleted']++;
          }
          catch (\Throwable $e) {
            $this->logger->error('Failed to delete subjects_group term "@name" (tid=@tid): @msg', [
              '@name' => $name,
              '@tid' => (string) $term->id(),
              '@msg' => $e->getMessage(),
            ]);
            $notifier->warning('Cannot delete obsolete subjects group "' . $name . '": ' . $e->getMessage());
          }
        }
      }

      // SORT (ponecháme)
      try {
        \Drupal::service('krizport_tools.common_tools')->setTermsWeightByCz($vid);
      }
      catch (\Throwable $e) {
        $this->logger->warning('Sort failed: @msg', ['@msg' => $e->getMessage()]);
        $notifier->warning('Sort failed: ' . $e->getMessage());
      }

      $notifier->success('Subjects Groups processing complete.');
      return ['ok' => TRUE, 'message' => 'OK', 'stats' => $stats];
    }
    catch (\Throwable $e) {
      $this->logger->error('Subjects Groups processing failed: @msg', ['@msg' => $e->getMessage()]);
      $notifier->error('Subjects Groups processing failed: ' . $e->getMessage());
      return ['ok' => FALSE, 'message' => $e->getMessage(), 'stats' => $stats];
    }
  }

  /**
   * @return string[]
   */
  private function loadDistinctGroupNamesFromTemp(): array {
    $result = $this->database->select('bezport_contacts_subjects', 's')
      ->fields('s', ['group'])
      ->isNotNull('s.group')
      ->condition('s.group', '', '<>')
      ->orderBy('s.group', 'ASC')
      ->distinct()
      ->execute()
      ->fetchCol();

    $names = [];
    foreach ($result as $v) {
      $name = trim((string) $v);
      if ($name !== '') {
        $names[$name] = $name;
      }
    }

    return array_values($names);
  }

  /**
   * @return string[]
   */
  private function loadExistingTermsByName(string $vid): array {
    /** @var \Drupal\taxonomy\TermInterface[] $terms */
    $terms = $this->termStorage->loadByProperties(['vid' => $vid]);

    $names = [];
    foreach ($terms as $term) {
      /** @var \Drupal\taxonomy\TermInterface $term */
      $name = trim((string) $term->label());
      if ($name !== '') {
        $names[$name] = $name;
      }
    }

    return array_values($names);
  }

}
