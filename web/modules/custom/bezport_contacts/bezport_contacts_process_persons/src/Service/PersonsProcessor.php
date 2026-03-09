<?php

declare(strict_types=1);

namespace Drupal\bezport_contacts_process_persons\Service;

use Drupal\bezport_contacts_main\Service\ImportNotifierInterface;
use Drupal\bezport_contacts_main\Service\ProcessorInterface;
use Drupal\Component\Utility\EmailValidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\taxonomy\TermInterface;
use Psr\Log\LoggerInterface;

final class PersonsProcessor implements ProcessorInterface {

  private LoggerInterface $logger;

  /** @var \Drupal\Core\Entity\EntityStorageInterface */
  private EntityStorageInterface $nodeStorage;

  /** @var \Drupal\Core\Entity\EntityStorageInterface */
  private EntityStorageInterface $paragraphStorage;

  /** @var \Drupal\Core\Entity\EntityStorageInterface */
  private EntityStorageInterface $termStorage;

  public function __construct(
    private readonly Connection $database,
    EntityTypeManagerInterface $entityTypeManager,
    private readonly EmailValidatorInterface $emailValidator,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('bezport_contacts_process_persons');
    $this->nodeStorage = $entityTypeManager->getStorage('node');
    $this->paragraphStorage = $entityTypeManager->getStorage('paragraph');
    $this->termStorage = $entityTypeManager->getStorage('taxonomy_term');
  }

  public function id(): string {
    return 'persons';
  }

  public function label(): string {
    return 'Persons';
  }

  /**
   * @return array{ok:bool, message:string, stats:array<string,int>}
   */
  public function process(ImportNotifierInterface $notifier): array {
    $type = 'contact_person';
    $admin_uid = 34;

    $stats = [
      'source_total' => 0,
      'new' => 0,
      'updated' => 0,
      'deleted' => 0,
      'paragraphs_deleted' => 0,
      'paragraphs_created' => 0,
      'relations_skipped_missing_refs' => 0,
    ];

    try {
      $notifier->info('Persons processing starts.');

      // 1) Load all persons from temp.
      $rows = $this->database->select('bezport_contacts_persons', 'p')
        ->fields('p')
        ->orderBy('p.id', 'ASC')
        ->execute()
        ->fetchAll();

      $source_ids = [];
      foreach ($rows as $row) {
        $sid = (int) ($row->person_id ?? 0);
        if ($sid > 0) {
          $source_ids[$sid] = $sid;
        }
      }
      $source_ids = array_values($source_ids);
      $stats['source_total'] = count($source_ids);

      // 2) Existing nodes map: source_id => NodeInterface.
      $existing = $this->loadExistingPersonsBySourceId($type);

      $existing_ids = array_keys($existing);

      $new_ids = array_diff($source_ids, $existing_ids);
      $update_ids = array_intersect($source_ids, $existing_ids);
      $obsolete_ids = array_diff($existing_ids, $source_ids);

      // 3) Prepare fast lookup maps (NO per-row entityQuery()).
      $role_tid_by_source = $this->loadTermTidMapBySourceId('bezport_contacts_roles');
      $group_tid_by_source = $this->loadTermTidMapBySourceId('bezport_contacts_groups');
      $subject_nid_by_source = $this->loadNodeNidMapBySourceId('contact_subject');

      // 4) Process NEW.
      foreach ($new_ids as $sid) {
        $person = $this->loadPersonRow((int) $sid);
        if (!$person) {
          continue;
        }

        $rel = $this->buildRelations(
          (int) $sid,
          $role_tid_by_source,
          $group_tid_by_source,
          $subject_nid_by_source,
          $stats
        );

        $node = $this->nodeStorage->create([
          'type' => $type,
          'status' => TRUE,
          'uid' => $admin_uid,
          'created' => time(),

          'field_source_id' => (string) $sid,

          'field_c_first_name' => (string) ($person->first_name ?? ''),
          'field_c_last_name' => (string) ($person->last_name ?? ''),
          'field_c_degree_before_name' => (string) ($person->degree_before ?? ''),
          'field_c_degree_after_name' => (string) ($person->degree_after ?? ''),

          'field_c_emails' => $this->parseEmails((string) ($person->email ?? '')),
          'field_c_mobile_phones' => $this->parsePhones((string) ($person->mobile ?? '')),
          'field_c_landlines' => $this->parsePhones((string) ($person->phone ?? '')),
          'field_c_faxes' => $this->parsePhones((string) ($person->fax ?? '')),

          'field_c_catg_group' => $rel['group_tids'],
          'field_c_subject_role' => $rel['subject_role_paragraph_items'],

          // Tyhle dvě pole jsi původně nastavoval jen při UPDATE → sjednoceno.
          'field_c_roles' => $rel['role_tids'],
          'field_c_subjects' => $rel['subject_nids'],

          'field_c_hzs_role' => (string) ($person->role ?? ''),
          'field_c_street' => (string) ($person->street ?? ''),
          'field_c_street_number' => (string) ($person->street_number ?? ''),
          'field_c_street_number2' => (string) ($person->street_number2 ?? ''),
          'field_c_municipality' => (string) ($person->city ?? ''),
          'field_c_region' => (string) ($person->region ?? ''),
        ]);

        $node->save();
        $stats['new']++;
        $stats['paragraphs_created'] += $rel['paragraphs_created'];
      }

      // 5) Process UPDATE.
      foreach ($update_ids as $sid) {
        $sid = (int) $sid;
        /** @var \Drupal\node\NodeInterface|null $node */
        $node = $existing[$sid] ?? NULL;
        if (!$node instanceof NodeInterface) {
          continue;
        }

        $person = $this->loadPersonRow($sid);
        if (!$person) {
          continue;
        }

        // Delete existing referenced paragraphs before rebuilding.
        $stats['paragraphs_deleted'] += $this->deleteReferencedSubjectRoleParagraphs($node);

        $rel = $this->buildRelations(
          $sid,
          $role_tid_by_source,
          $group_tid_by_source,
          $subject_nid_by_source,
          $stats
        );

        // Přepisujeme hodnoty (jednodušší, stabilní, odpovídá původní logice).
        $node->setOwnerId($admin_uid);

        $node->set('field_c_first_name', (string) ($person->first_name ?? ''));
        $node->set('field_c_last_name', (string) ($person->last_name ?? ''));
        $node->set('field_c_degree_before_name', (string) ($person->degree_before ?? ''));
        $node->set('field_c_degree_after_name', (string) ($person->degree_after ?? ''));

        $node->set('field_c_emails', $this->parseEmails((string) ($person->email ?? '')));
        $node->set('field_c_mobile_phones', $this->parsePhones((string) ($person->mobile ?? '')));
        $node->set('field_c_landlines', $this->parsePhones((string) ($person->phone ?? '')));
        $node->set('field_c_faxes', $this->parsePhones((string) ($person->fax ?? '')));

        $node->set('field_c_catg_group', $rel['group_tids']);
        $node->set('field_c_subject_role', $rel['subject_role_paragraph_items']);

        $node->set('field_c_roles', $rel['role_tids']);
        $node->set('field_c_subjects', $rel['subject_nids']);

        $node->set('field_c_hzs_role', (string) ($person->role ?? ''));
        $node->set('field_c_street', (string) ($person->street ?? ''));
        $node->set('field_c_street_number', (string) ($person->street_number ?? ''));
        $node->set('field_c_street_number2', (string) ($person->street_number2 ?? ''));
        $node->set('field_c_municipality', (string) ($person->city ?? ''));
        $node->set('field_c_region', (string) ($person->region ?? ''));

        $node->save();

        $stats['updated']++;
        $stats['paragraphs_created'] += $rel['paragraphs_created'];
      }

      // 6) DELETE obsolete.
      foreach ($obsolete_ids as $sid) {
        $sid = (int) $sid;
        $node = $existing[$sid] ?? NULL;
        if (!$node instanceof NodeInterface) {
          continue;
        }

        // Optional: delete referenced paragraphs too (bez orphanů).
        $stats['paragraphs_deleted'] += $this->deleteReferencedSubjectRoleParagraphs($node);

        $node->delete();
        $stats['deleted']++;
      }

      // 7) SORT (ponecháme dle zadání).
      try {
        \Drupal::service('krizport_tools.common_tools')->setNodeWeightByCz($type, 'field_c_last_name');
      }
      catch (\Throwable $e) {
        $this->logger->warning('Sort failed: @msg', ['@msg' => $e->getMessage()]);
        $notifier->warning('Sort failed: ' . $e->getMessage());
      }

      $notifier->success('Persons processing complete.');
      return ['ok' => TRUE, 'message' => 'OK', 'stats' => $stats];
    }
    catch (\Throwable $e) {
      $this->logger->error('Persons processing failed: @msg', ['@msg' => $e->getMessage()]);
      $notifier->error('Persons processing failed: ' . $e->getMessage());
      return ['ok' => FALSE, 'message' => $e->getMessage(), 'stats' => $stats];
    }
  }

  /**
   * @return array<int,\Drupal\node\NodeInterface> source_id => node
   */
  private function loadExistingPersonsBySourceId(string $type): array {
    $nodes = $this->nodeStorage->loadByProperties(['type' => $type]);

    $map = [];
    foreach ($nodes as $node) {
      if (!$node instanceof NodeInterface) {
        continue;
      }
      $sid_raw = $node->get('field_source_id')->value ?? '';
      $sid = (int) $sid_raw;
      if ($sid > 0) {
        $map[$sid] = $node;
      }
    }
    return $map;
  }

  private function loadPersonRow(int $person_id): ?object {
    $rows = $this->database->select('bezport_contacts_persons', 'p')
      ->fields('p')
      ->condition('p.person_id', $person_id, '=')
      ->range(0, 1)
      ->execute()
      ->fetchAll();

    return $rows[0] ?? NULL;
  }

  /**
   * @return array<int,int> source_id => tid
   */
  private function loadTermTidMapBySourceId(string $vid): array {
    $terms = $this->termStorage->loadByProperties(['vid' => $vid]);

    $map = [];
    foreach ($terms as $term) {

      // Pro VS Code/Intelephense: zúžení typu, aby byla metoda get() jasná.
      if (!$term instanceof TermInterface) {
        continue;
      }

      // field_source_id musí existovat a být fieldovatelný.
      // (TermInterface už je FieldableEntityInterface, takže get() ok.)
      $sid_raw = $term->get('field_source_id')->value ?? '';
      $sid = (int) $sid_raw;

      if ($sid > 0) {
        $map[$sid] = (int) $term->id();
      }
    }

    return $map;
  }

  /**
   * @return array<int,int> subject_source_id => nid
   */
  private function loadNodeNidMapBySourceId(string $type): array {
    $nodes = $this->nodeStorage->loadByProperties(['type' => $type]);

    $map = [];
    foreach ($nodes as $node) {
      if (!$node instanceof NodeInterface) {
        continue;
      }
      $sid_raw = $node->get('field_source_id')->value ?? '';
      $sid = (int) $sid_raw;
      if ($sid > 0) {
        $map[$sid] = (int) $node->id();
      }
    }
    return $map;
  }

  /**
   * Build relation fields + create paragraphs (subject-role).
   *
   * @param array<int,int> $role_tid_by_source
   * @param array<int,int> $group_tid_by_source
   * @param array<int,int> $subject_nid_by_source
   * @param array<string,int> &$stats
   *
   * @return array{
   *   group_tids: array<int,int>,
   *   role_tids: array<int,int>,
   *   subject_nids: array<int,int>,
   *   subject_role_paragraph_items: array<int,array{target_id:int,target_revision_id:int}>,
   *   paragraphs_created:int
   * }
   */
  private function buildRelations(
    int $person_id,
    array $role_tid_by_source,
    array $group_tid_by_source,
    array $subject_nid_by_source,
    array &$stats,
  ): array {
    // Groups.
    $group_tids = [];
    $rows_groups = $this->database->select('bezport_contacts_persons_groups_rls', 'g')
      ->fields('g', ['group_id'])
      ->condition('g.person_id', $person_id, '=')
      ->execute()
      ->fetchCol();

    foreach ($rows_groups as $group_source_id) {
      $gsid = (int) $group_source_id;
      $tid = $group_tid_by_source[$gsid] ?? 0;
      if ($tid > 0) {
        $group_tids[$tid] = $tid;
      }
    }
    $group_tids = array_values($group_tids);

    // Subject-role paragraphs.
    $role_tids = [];
    $subject_nids = [];
    $items = [];
    $created = 0;

    $rows_sr = $this->database->select('bezport_contacts_persons_subjects_roles_rls', 'sr')
      ->fields('sr', ['role_id', 'subject_id'])
      ->condition('sr.person_id', $person_id, '=')
      ->orderBy('sr.id', 'ASC')
      ->execute()
      ->fetchAll();

    foreach ($rows_sr as $row) {
      $role_source = (int) ($row->role_id ?? 0);
      $subject_source = (int) ($row->subject_id ?? 0);

      $role_tid = $role_tid_by_source[$role_source] ?? 0;
      $subject_nid = $subject_nid_by_source[$subject_source] ?? 0;

      if ($role_tid <= 0 || $subject_nid <= 0) {
        $stats['relations_skipped_missing_refs']++;
        continue;
      }

      $role_tids[$role_tid] = $role_tid;
      $subject_nids[$subject_nid] = $subject_nid;

      /** @var \Drupal\paragraphs\ParagraphInterface $p */
      $p = $this->paragraphStorage->create([
        'type' => 'contact_subject_role',
        'field_contact_pg_subject' => $subject_nid,
        'field_contact_pg_role' => $role_tid,
      ]);
      $p->save();

      $items[] = [
        'target_id' => (int) $p->id(),
        'target_revision_id' => (int) $p->getRevisionId(),
      ];
      $created++;
    }

    return [
      'group_tids' => $group_tids,
      'role_tids' => array_values($role_tids),
      'subject_nids' => array_values($subject_nids),
      'subject_role_paragraph_items' => $items,
      'paragraphs_created' => $created,
    ];
  }

  /**
   * Delete paragraphs referenced in field_c_subject_role on person node.
   */
  private function deleteReferencedSubjectRoleParagraphs(NodeInterface $node): int {
    if (!$node->hasField('field_c_subject_role')) {
      return 0;
    }

    $items = $node->get('field_c_subject_role')->getValue();
    if ($items === []) {
      return 0;
    }

    $pids = [];
    foreach ($items as $item) {
      $pid = (int) ($item['target_id'] ?? 0);
      if ($pid > 0) {
        $pids[$pid] = $pid;
      }
    }
    $pids = array_values($pids);
    if ($pids === []) {
      return 0;
    }

    $paragraphs = $this->paragraphStorage->loadMultiple($pids);
    $deleted = 0;

    foreach ($paragraphs as $p) {
      if ($p instanceof ParagraphInterface) {
        try {
          $p->delete();
          $deleted++;
        }
        catch (\Throwable $e) {
          $this->logger->warning('Failed to delete paragraph @id: @msg', [
            '@id' => (string) $p->id(),
            '@msg' => $e->getMessage(),
          ]);
        }
      }
    }

    return $deleted;
  }

  /**
   * Parse "a@b.cz; c@d.cz" to array of values for email field.
   *
   * @return string[]
   */
  private function parseEmails(string $raw): array {
    $raw = trim($raw);
    if ($raw === '') {
      return [];
    }

    $parts = array_map('trim', explode(';', $raw));
    $out = [];

    foreach ($parts as $email) {
      if ($email === '') {
        continue;
      }
      if ($this->emailValidator->isValid($email)) {
        $out[$email] = $email;
      }
    }

    return array_values($out);
  }

  /**
   * Parse "123; 456" to array, remove spaces.
   *
   * @return string[]
   */
  private function parsePhones(string $raw): array {
    $raw = trim($raw);
    if ($raw === '') {
      return [];
    }

    $parts = array_map('trim', explode(';', $raw));
    $out = [];

    foreach ($parts as $p) {
      $p = preg_replace('/\s+/', '', $p ?? '');
      $p = (string) $p;
      if ($p !== '') {
        $out[$p] = $p;
      }
    }

    return array_values($out);
  }

}
