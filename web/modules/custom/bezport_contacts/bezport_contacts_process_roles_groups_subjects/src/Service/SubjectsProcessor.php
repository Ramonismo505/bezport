<?php

declare(strict_types=1);

namespace Drupal\bezport_contacts_process_roles_groups_subjects\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\bezport_contacts_main\Service\ImportNotifierInterface;
use Drupal\bezport_contacts_main\Service\ProcessorInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\node\NodeInterface;
use Drupal\node\NodeStorageInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy\TermStorageInterface;
use Drupal\Component\Utility\EmailValidatorInterface;
use Psr\Log\LoggerInterface;

final class SubjectsProcessor implements ProcessorInterface {

  private LoggerInterface $logger;

  private NodeStorageInterface $nodeStorage;

  private TermStorageInterface $termStorage;

  public function __construct(
    private readonly Connection $database,
    EntityTypeManagerInterface $entityTypeManager,
    private readonly EmailValidatorInterface $emailValidator,
    LoggerChannelFactoryInterface $loggerFactory,
    // Default jako v původním kódu.
    private readonly int $adminUid = 34,
  ) {
    /** @var \Drupal\node\NodeStorageInterface $ns */
    $ns = $entityTypeManager->getStorage('node');
    $this->nodeStorage = $ns;

    /** @var \Drupal\taxonomy\TermStorageInterface $ts */
    $ts = $entityTypeManager->getStorage('taxonomy_term');
    $this->termStorage = $ts;

    $this->logger = $loggerFactory->get('bezport_contacts_process_rgs');
  }

  public function id(): string {
    return 'subjects';
  }

  public function label(): string {
    return 'Subjects';
  }

  /**
   * @return array{ok:bool, message:string, stats:array<string,int>}
   */
  public function process(ImportNotifierInterface $notifier): array {
    $type = 'contact_subject';

    $stats = [
      'new' => 0,
      'updated' => 0,
      'deleted' => 0,
      'source_total' => 0,
    ];

    try {
      $notifier->info('Subjects processing starts.');

      // 1) Načti vše z temp tabulky (mapa subject_id => row).
      $subjects_map = $this->loadSubjectsMap();
      $stats['source_total'] = count($subjects_map);

      // 2) Načti existující nodes podle field_source_id.
      $existing = $this->loadExistingNodesBySourceId($type);

      $source_ids = array_keys($subjects_map);
      $existing_ids = array_keys($existing);

      $new_ids = array_diff($source_ids, $existing_ids);
      $update_ids = array_intersect($source_ids, $existing_ids);
      $obsolete_ids = array_diff($existing_ids, $source_ids);

      // NEW
      foreach ($new_ids as $sid) {
        $row = $subjects_map[(int) $sid] ?? NULL;
        if (!$row) {
          continue;
        }
        $node = $this->nodeStorage->create($this->buildNodeValuesFromRow($row, $type, TRUE));
        $node->save();
        $stats['new']++;
      }

      // UPDATE (jen pokud se něco změnilo)
      foreach ($update_ids as $sid) {
        $row = $subjects_map[(int) $sid] ?? NULL;
        $node = $existing[(int) $sid] ?? NULL;
        if (!$row || !$node) {
          continue;
        }

        $changed = $this->applyRowToExistingNode($node, $row);
        if ($changed) {
          $node->save();
          $stats['updated']++;
        }
      }

      // DELETE obsolete
      foreach ($obsolete_ids as $sid) {
        $node = $existing[(int) $sid] ?? NULL;
        if (!$node) {
          continue;
        }

        try {
          $node->delete();
          $stats['deleted']++;
        }
        catch (\Throwable $e) {
          $this->logger->error('Failed to delete subject node source_id=@sid: @msg', [
            '@sid' => (string) $sid,
            '@msg' => $e->getMessage(),
          ]);
          $notifier->warning('Cannot delete obsolete subject source_id ' . $sid . ': ' . $e->getMessage());
        }
      }

      // SORT (ponecháme)
      try {
        \Drupal::service('krizport_tools.common_tools')->setNodeWeightByCz($type);
      }
      catch (\Throwable $e) {
        $this->logger->warning('Sort failed: @msg', ['@msg' => $e->getMessage()]);
        $notifier->warning('Sort failed: ' . $e->getMessage());
      }

      $notifier->success('Subjects processing complete.');
      return ['ok' => TRUE, 'message' => 'OK', 'stats' => $stats];
    }
    catch (\Throwable $e) {
      $this->logger->error('Subjects processing failed: @msg', ['@msg' => $e->getMessage()]);
      $notifier->error('Subjects processing failed: ' . $e->getMessage());
      return ['ok' => FALSE, 'message' => $e->getMessage(), 'stats' => $stats];
    }
  }

  /**
   * Načte temp data jako mapu subject_id => row(object).
   *
   * @return array<int,object>
   */
  private function loadSubjectsMap(): array {
    $rows = $this->database->select('bezport_contacts_subjects', 's')
      ->fields('s')
      ->orderBy('s.subject_id', 'ASC')
      ->execute()
      ->fetchAll();

    $map = [];
    foreach ($rows as $row) {
      $sid = (int) ($row->subject_id ?? 0);
      if ($sid > 0) {
        $map[$sid] = $row;
      }
    }
    return $map;
  }

  /**
   * @return array<int,\Drupal\node\NodeInterface>
   */
  private function loadExistingNodesBySourceId(string $type): array {
    /** @var \Drupal\node\NodeInterface[] $nodes */
    $nodes = $this->nodeStorage->loadByProperties(['type' => $type]);

    $map = [];
    foreach ($nodes as $node) {
      $sid_raw = (string) $node->get('field_source_id')->value;
      $sid = (int) $sid_raw;
      if ($sid <= 0) {
        continue;
      }
      if (isset($map[$sid])) {
        $this->logger->warning('Duplicate node field_source_id=@sid for type=@type (nids: @a, @b)', [
          '@sid' => (string) $sid,
          '@type' => $type,
          '@a' => (string) $map[$sid]->id(),
          '@b' => (string) $node->id(),
        ]);
        continue;
      }
      $map[$sid] = $node;
    }

    return $map;
  }

  /**
   * Vytvoří pole hodnot pro vytvoření node z row temp tabulky.
   *
   * @param object $row
   * @return array<string,mixed>
   */
  private function buildNodeValuesFromRow(object $row, string $type, bool $is_new): array {
    $emails_values = $this->deserializeEmails((string) ($row->emails_serialized ?? ''));
    $mobiles_values = $this->deserializePhones((string) ($row->mobile_phones_serialized ?? ''));
    $landlines_values = $this->deserializePhones((string) ($row->landlines_serialized ?? ''));
    $subject_group_tid = $this->resolveSubjectGroupTid((string) ($row->group ?? ''));

    $values = [
      'type' => $type,
      'title' => (string) ($row->name ?? ''),
      'status' => TRUE,
      'uid' => $this->adminUid,
      'field_source_id' => (int) ($row->subject_id ?? 0),

      'field_c_street' => $row->street ?? NULL,
      'field_c_street_number' => $row->street_number ?? NULL,
      'field_c_municipality' => $row->municipality ?? NULL,
      'field_c_municipality_region' => $row->municipality_region ?? NULL,
      'field_c_zip' => $row->zip ?? NULL,
      'field_c_whole_address' => $row->whole_address ?? NULL,

      'field_c_emails' => $emails_values,
      'field_c_mobile_phones' => $mobiles_values,
      'field_c_landlines' => $landlines_values,

      'field_c_group' => $row->group ?? NULL,
      'field_c_catg_subject_group' => $subject_group_tid ? (int) $subject_group_tid : NULL,
      'field_c_company_id' => $row->company_id ?? NULL,
    ];

    // Jen pro NEW nastavíme created (u update ne).
    if ($is_new) {
      $values['created'] = \time();
    }

    return $values;
  }

  /**
   * Přenese hodnoty z row do existujícího node, ale jen pokud se liší.
   *
   * @return bool
   *   TRUE pokud se něco změnilo.
   */
  private function applyRowToExistingNode(NodeInterface $node, object $row): bool {
    $changed = FALSE;

    $emails_values = $this->deserializeEmails((string) ($row->emails_serialized ?? ''));
    $mobiles_values = $this->deserializePhones((string) ($row->mobile_phones_serialized ?? ''));
    $landlines_values = $this->deserializePhones((string) ($row->landlines_serialized ?? ''));
    $subject_group_tid = $this->resolveSubjectGroupTid((string) ($row->group ?? ''));

    $changed = $this->setIfDifferent($node, 'title', (string) ($row->name ?? ''), TRUE) || $changed;

    $changed = $this->setIfDifferent($node, 'field_c_street', $row->street ?? NULL) || $changed;
    $changed = $this->setIfDifferent($node, 'field_c_street_number', $row->street_number ?? NULL) || $changed;
    $changed = $this->setIfDifferent($node, 'field_c_municipality', $row->municipality ?? NULL) || $changed;
    $changed = $this->setIfDifferent($node, 'field_c_municipality_region', $row->municipality_region ?? NULL) || $changed;
    $changed = $this->setIfDifferent($node, 'field_c_zip', $row->zip ?? NULL) || $changed;
    $changed = $this->setIfDifferent($node, 'field_c_whole_address', $row->whole_address ?? NULL) || $changed;

    $changed = $this->setIfDifferent($node, 'field_c_emails', $emails_values) || $changed;
    $changed = $this->setIfDifferent($node, 'field_c_mobile_phones', $mobiles_values) || $changed;
    $changed = $this->setIfDifferent($node, 'field_c_landlines', $landlines_values) || $changed;

    $changed = $this->setIfDifferent($node, 'field_c_group', $row->group ?? NULL) || $changed;

    $tid_val = $subject_group_tid ? (int) $subject_group_tid : NULL;
    $changed = $this->setIfDifferent($node, 'field_c_catg_subject_group', $tid_val) || $changed;

    $changed = $this->setIfDifferent($node, 'field_c_company_id', $row->company_id ?? NULL) || $changed;

    return $changed;
  }

  /**
   * Nastaví field jen když se liší (pokrývá i title).
   *
   * @param bool $is_title
   *   TRUE => nastavuje title přes setTitle().
   */
  private function setIfDifferent(NodeInterface $node, string $field_name, mixed $new_value, bool $is_title = FALSE): bool {
    if ($is_title) {
      $current = (string) $node->label();
      $new = (string) $new_value;
      if ($current !== $new) {
        $node->setTitle($new);
        return TRUE;
      }
      return FALSE;
    }

    // Pro multivalue (emails/phones) použijeme normalizaci na array.
    if (in_array($field_name, ['field_c_emails', 'field_c_mobile_phones', 'field_c_landlines'], TRUE)) {
      $current = $node->get($field_name)->getValue();
      $current_values = [];
      foreach ($current as $item) {
        if (isset($item['value']) && $item['value'] !== '') {
          $current_values[] = (string) $item['value'];
        }
      }

      $new_values = [];
      if (is_array($new_value)) {
        foreach ($new_value as $v) {
          $v = (string) $v;
          if ($v !== '') {
            $new_values[] = $v;
          }
        }
      }

      if ($current_values !== $new_values) {
        $node->set($field_name, $new_values);
        return TRUE;
      }
      return FALSE;
    }

    // Reference na term (tid).
    if ($field_name === 'field_c_catg_subject_group') {
      $current_tid = $node->get($field_name)->target_id ?? NULL;
      $new_tid = $new_value !== NULL ? (int) $new_value : NULL;
      $current_tid = $current_tid !== NULL ? (int) $current_tid : NULL;

      if ($current_tid !== $new_tid) {
        // NULL => vyčistit.
        if ($new_tid === NULL) {
          $node->set($field_name, NULL);
        }
        else {
          $node->set($field_name, $new_tid);
        }
        return TRUE;
      }
      return FALSE;
    }

    // Jednoduché value pole.
    $current = $node->get($field_name)->value ?? NULL;

    // Normalizace pro porovnání.
    $cur_norm = $current === NULL ? NULL : (string) $current;
    $new_norm = $new_value === NULL ? NULL : (string) $new_value;

    if ($cur_norm !== $new_norm) {
      $node->set($field_name, $new_value);
      return TRUE;
    }

    return FALSE;
  }

  /**
   * @return string[]
   */
  private function deserializeEmails(string $serialized): array {
    if ($serialized === '') {
      return [];
    }

    $arr = @unserialize($serialized);
    if (!is_array($arr)) {
      return [];
    }

    $values = [];
    foreach ($arr as $email) {
      $email = trim((string) $email);
      if ($email !== '' && $this->emailValidator->isValid($email)) {
        $values[] = $email;
      }
    }

    return $values;
  }

  /**
   * @return string[]
   */
  private function deserializePhones(string $serialized): array {
    if ($serialized === '') {
      return [];
    }

    $arr = @unserialize($serialized);
    if (!is_array($arr)) {
      return [];
    }

    $values = [];
    foreach ($arr as $phone) {
      $phone = trim((string) $phone);
      $phone = preg_replace('/\s+/', '', $phone) ?? '';
      if ($phone !== '') {
        $values[] = $phone;
      }
    }

    return $values;
  }

  /**
   * Vrátí TID termu ve vocab "bezport_contacts_subjects_groups" podle name.
   */
  private function resolveSubjectGroupTid(string $group_name): ?int {
    $group_name = trim($group_name);
    if ($group_name === '') {
      return NULL;
    }

    /** @var \Drupal\taxonomy\TermInterface[] $terms */
    $terms = $this->termStorage->loadByProperties([
      'vid' => 'bezport_contacts_subjects_groups',
      'name' => $group_name,
    ]);

    if ($terms === []) {
      return NULL;
    }

    $term = reset($terms);
    return $term ? (int) $term->id() : NULL;
  }

}
