<?php

declare(strict_types=1);

/**
 * Implements hook_views_data_alter().
 */
function ra_import_record_views_data_alter(array &$data): void {
  // 1. Propojení pro Node
  $data['node_field_data']['ra_import_record_rel'] = [
    'title' => t('Import Record (Node)'),
    'help' => t('Propojí node se záznamem o importu.'),
    'relationship' => [
      'base' => 'ra_import_record',
      'base field' => 'target_entity_id',
      'field' => 'nid',
      'id' => 'standard',
      'label' => t('Data Importu (Node)'),
      'extra' => [
        [
          'field' => 'target_entity_type',
          'value' => 'node',
        ],
      ],
    ],
  ];

  // 2. Propojení pro Taxonomii
  $data['taxonomy_term_field_data']['ra_import_record_rel'] = [
    'title' => t('Import Record (Taxonomy)'),
    'help' => t('Propojí termín taxonomie se záznamem o importu.'),
    'relationship' => [
      'base' => 'ra_import_record',
      'base field' => 'target_entity_id',
      'field' => 'tid', // ID termínu
      'id' => 'standard',
      'label' => t('Data Importu (Taxonomy)'),
      'extra' => [
        [
          'field' => 'target_entity_type',
          'value' => 'taxonomy_term', // Filtrujeme pouze záznamy pro taxonomii
        ],
      ],
    ],
  ];
}
