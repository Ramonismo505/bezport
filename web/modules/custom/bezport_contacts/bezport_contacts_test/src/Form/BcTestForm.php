<?php

namespace Drupal\bezport_contacts_test\Form;

use Drupal\bezport_contacts_main\Service\ControlRestApiAvailability;
use Drupal\bezport_contacts_test\Service\PaginatedEndpointChecker;
use Drupal\bezport_contacts_test\Service\RestApiRecordSearcher;
use Drupal\Component\Utility\Html;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Bezport Contacts test form.
 */
final class BcTestForm extends FormBase implements ContainerInjectionInterface {

  use DependencySerializationTrait;

  private ControlRestApiAvailability $controlRas;
  private PaginatedEndpointChecker $endpointChecker;
  private RestApiRecordSearcher $recordSearcher;

  public static function create(ContainerInterface $container): self {
    $instance = new self();

    $instance->controlRas = $container->get('bezport_contacts_main.control_ras');
    $instance->endpointChecker = $container->get('bezport_contacts_test.paginated_endpoint_checker');
    $instance->recordSearcher = $container->get('bezport_contacts_test.record_searcher');

    return $instance;
  }

  public function getFormId(): string {
    return 'bezport_contacts_test_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {

    $default_tab = $form_state->get('active_tab_id');
    if (!is_string($default_tab) || $default_tab === '') {
      $default_tab = 'edit-tab-restapi-avail';
    }

    $form['tabs'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Vyberte test'),
      '#default_tab' => $default_tab,
    ];

    /*
     * TAB: REST API Availability
     */
    $form['tab_restapi_avail'] = [
      '#type' => 'details',
      '#title' => $this->t('Dostupnost REST API serveru'),
      '#group' => 'tabs',
      '#open' => ($default_tab === 'edit-tab-restapi-avail'),
    ];

    $form['tab_restapi_avail']['result_wrapper_ras'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'restapi-avail-result-wrapper-ras'],
      '#weight' => -10,
    ];

    $result_ras = $form_state->get('result_ras');
    if ($result_ras !== NULL) {
      if ($result_ras === TRUE) {
        $form['tab_restapi_avail']['result_wrapper_ras']['msg'] = [
          '#type' => 'markup',
          '#markup' =>
            '<div class="messages messages--status">' .
            $this->t('REST API server je dostupný.') .
            '</div>',
        ];
      }
      else {
        $form['tab_restapi_avail']['result_wrapper_ras']['msg'] = [
          '#type' => 'markup',
          '#markup' =>
            '<div class="messages messages--error">' .
            $this->t('@msg', ['@msg' => (string) $result_ras]) .
            '</div>',
        ];
      }
    }

    $form['tab_restapi_avail']['actions'] = ['#type' => 'actions'];
    $form['tab_restapi_avail']['actions']['submit_tab_restapi_avail'] = [
      '#type' => 'submit',
      '#value' => $this->t('Spustit test dostupnosti'),
      '#submit' => ['::submitTabRestapiAvail'],
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => '::ajaxRestapiAvailResult',
        'wrapper' => 'restapi-avail-result-wrapper-ras',
      ],
    ];

    /*
     * TAB: Real-time pages check (test-only)
     */
    $form['tab_import_pages_check'] = [
      '#type' => 'details',
      '#title' => $this->t('Kontrola paginovaných endpointů – chybějící stránky'),
      '#group' => 'tabs',
      '#open' => ($default_tab === 'edit-tab-import-pages-check'),
    ];

    $form['tab_import_pages_check']['info'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t('Ověří v reálném čase, zda lze načíst všechny stránky z vybraných endpointů (parametr page).') . '</p>',
    ];

    $form['tab_import_pages_check']['check_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Co kontrolovat'),
      '#options' => [
        'roles' => $this->t('Role (GET /api/roles?page=)'),
        'groups' => $this->t('Skupiny (GET /api/groups?page=)'),
        'subjects' => $this->t('Subjekty (GET /api/subjects?page=)'),
        'persons' => $this->t('Osoby (GET /api/people?page=)'),
      ],
    ];

    $form['tab_import_pages_check']['result_wrapper_pages'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'import-pages-result-wrapper'],
      '#weight' => 10,
    ];

    $result_pages_html = $form_state->get('result_pages_html');
    if (is_string($result_pages_html) && $result_pages_html !== '') {
      $form['tab_import_pages_check']['result_wrapper_pages']['msg'] = [
        '#type' => 'markup',
        '#markup' => Markup::create($result_pages_html),
      ];
    }

    $form['tab_import_pages_check']['actions'] = ['#type' => 'actions'];
    $form['tab_import_pages_check']['actions']['submit_tab_import_pages_check'] = [
      '#type' => 'submit',
      '#value' => $this->t('Spustit kontrolu stránek'),
      '#submit' => ['::submitTabImportPagesCheck'],
      // Bez #ajax (může běžet déle).
    ];

    /*
     * TAB: Real-time record search
     */
    $form['tab_record_search'] = [
      '#type' => 'details',
      '#title' => $this->t('Vyhledávání záznamů v REST API'),
      '#group' => 'tabs',
      '#open' => ($default_tab === 'edit-tab-record-search'),
    ];

    $endpoints_map = $this->endpointChecker->getEndpointsMap();
    $radio_options = [];
    foreach ($endpoints_map as $k => $meta) {
      $radio_options[$k] = $this->t((string) $meta['label']);
    }

    $selected_endpoint = (string) ($form_state->getValue('search_endpoint') ?? 'subjects');
    if (!isset($radio_options[$selected_endpoint])) {
      $selected_endpoint = 'subjects';
    }

    $form['tab_record_search']['search_endpoint'] = [
      '#type' => 'radios',
      '#title' => $this->t('Vyberte endpoint'),
      '#options' => $radio_options,
      '#default_value' => $selected_endpoint,
      '#required' => FALSE,
      '#ajax' => [
        'callback' => '::ajaxUpdateSearchField',
        'wrapper' => 'record-search-field-wrapper',
      ],
    ];

    // Wrapper for dynamic textfield title/description (updated via AJAX).
    $form['tab_record_search']['search_field_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'record-search-field-wrapper'],
    ];

    $query_param = ($selected_endpoint === 'persons') ? 'lastName' : 'name';
    $title = match ($selected_endpoint) {
      'roles', 'groups' => $this->t('Název'),
      'subjects' => $this->t('Jméno'),
      'persons' => $this->t('Příjmení'),
      default => $this->t('Hledaný text'),
    };

    $form['tab_record_search']['search_field_wrapper']['search_name'] = [
      '#type' => 'textfield',
      '#title' => $title,
      '#description' => $this->t('Zadejte hledaný výraz nebo jen jeho část.<br>Dotaz se posílá jako parametr @param na stránku page=1.', ['@param' => $query_param]),
      '#size' => 40,
    ];

    $form['tab_record_search']['result_wrapper_search'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'record-search-result-wrapper'],
      '#weight' => 10,
    ];

    $result_search_html = $form_state->get('result_search_html');
    if (is_string($result_search_html) && $result_search_html !== '') {
      $form['tab_record_search']['result_wrapper_search']['msg'] = [
        '#type' => 'markup',
        '#markup' => Markup::create($result_search_html),
      ];
    }

    $form['tab_record_search']['actions'] = ['#type' => 'actions'];
    $form['tab_record_search']['actions']['submit_tab_record_search'] = [
      '#type' => 'submit',
      '#value' => $this->t('Vyhledat'),
      '#submit' => ['::submitTabRecordSearch'],
      // Bez #ajax – submit je OK.
    ];

    return $form;
  }

  /**
   * Submit: REST API availability test.
   */
  public function submitTabRestapiAvail(array &$form, FormStateInterface $form_state): void {
    $result_ras = $this->controlRas->testRestApiServer();

    $form_state->set('result_ras', $result_ras);
    $form_state->set('active_tab_id', 'edit-tab-restapi-avail');
    $form_state->setRebuild(TRUE);
  }

  /**
   * AJAX callback.
   */
  public function ajaxRestapiAvailResult(array &$form, FormStateInterface $form_state) {
    return $form['tab_restapi_avail']['result_wrapper_ras'];
  }

  /**
   * AJAX callback for dynamic search field.
   */
  public function ajaxUpdateSearchField(array &$form, FormStateInterface $form_state) {
    return $form['tab_record_search']['search_field_wrapper'];
  }

  /**
   * Submit: Real-time check of paginated endpoints.
   */
  public function submitTabImportPagesCheck(array &$form, FormStateInterface $form_state): void {
    $form_state->set('active_tab_id', 'edit-tab-import-pages-check');

    $input = (array) $form_state->getUserInput();
    $raw = (array) ($input['check_types'] ?? []);
    $selected = array_keys(array_filter($raw));

    $check = $this->endpointChecker->check($selected);

    if (!$check['ok']) {
      $form_state->set(
        'result_pages_html',
        '<div class="messages messages--error">' .
        $this->t('@msg', ['@msg' => $check['message']]) .
        '</div>'
      );
      $form_state->setRebuild(TRUE);
      return;
    }

    $form_state->set('result_pages_html', $this->renderPagesCheckResults($check['results']));
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit: Real-time record search.
   */
  public function submitTabRecordSearch(array &$form, FormStateInterface $form_state): void {
    $form_state->set('active_tab_id', 'edit-tab-record-search');

    $endpoint = (string) ($form_state->getValue('search_endpoint') ?? '');
    $term = (string) ($form_state->getValue('search_name') ?? '');

    if ($endpoint === '') {
      $form_state->set(
        'result_search_html',
        '<div class="messages messages--warning">' .
        $this->t('Není vybrán žádný endpoint.') .
        '</div>'
      );
      $form_state->setRebuild(TRUE);
      return;
    }

    $result = $this->recordSearcher->search($endpoint, $term);
    $form_state->set('result_search_html', $this->renderSearchResult($result));
    $form_state->setRebuild(TRUE);
  }

  /**
   * Render pages check results.
   *
   * @param array<string, array{
   *   label: string,
   *   total_pages: int,
   *   missing_pages: int[],
   *   missing_count: int,
   *   note: string
   * }> $results
   */
  private function renderPagesCheckResults(array $results): string {
    $html = '';

    foreach ($results as $row) {
      $label = (string) ($row['label'] ?? '');
      $total = (int) ($row['total_pages'] ?? 0);
      $missing = (array) ($row['missing_pages'] ?? []);
      $missing_count = (int) ($row['missing_count'] ?? 0);
      $note = (string) ($row['note'] ?? '');

      $html .= '<h3>' . Html::escape($label) . '</h3>';

      if ($total === 0 && $missing_count === 0) {
        $html .= '<div class="messages messages--warning">' .
          $this->t('Endpoint nevrátil žádná data (0 stránek).') .
          '</div>';
        $html .= '<p>' . $this->t('Celkem stránek: 0') . '</p>';
        if ($note !== '') {
          $html .= '<p><em>' . Html::escape($note) . '</em></p>';
        }
        $html .= '<hr>';
        continue;
      }

      if ($missing_count === 0) {
        $html .= '<div class="messages messages--status">' .
          $this->t('Všechny stránky načteny') .
          '</div>';
        $html .= '<p>' . $this->t('Celkem stránek: @n', ['@n' => $total]) . '</p>';
      }
      else {
        $missing_list = implode(', ', array_map('intval', $missing));
        $html .= '<div class="messages messages--error">' .
          $this->t('Nenačteny stránky: @pages', ['@pages' => $missing_list]) .
          '</div>';
        $html .= '<p>' . $this->t('Celkem stránek: @n', ['@n' => $total]) . '</p>';
        $html .= '<p>' . $this->t('Nenačteno: @n', ['@n' => $missing_count]) . '</p>';
      }

      if ($note !== '') {
        $html .= '<p><em>' . Html::escape($note) . '</em></p>';
      }

      $html .= '<hr>';
    }

    return $html;
  }

  /**
   * Render record search result to a readable HTML output.
   *
   * @param array{
   *   ok: bool,
   *   message: string,
   *   endpoint_label: string,
   *   query_param: string,
   *   query_value: string,
   *   items: array<int, mixed>
   * } $result
   */
  private function renderSearchResult(array $result): string {
    $ok = (bool) ($result['ok'] ?? false);
    $message = (string) ($result['message'] ?? '');
    $label = (string) ($result['endpoint_label'] ?? '');
    $param = (string) ($result['query_param'] ?? '');
    $value = (string) ($result['query_value'] ?? '');
    $items = (array) ($result['items'] ?? []);

    if (!$ok) {
      return '<div class="messages messages--error">' .
        $this->t('@msg', ['@msg' => $message]) .
        '</div>';
    }

    $html = '<h3>' . Html::escape($label) . '</h3>';
    $html .= '<p><strong>' . $this->t('Dotaz') . ':</strong> ' .
      Html::escape($param) . '=' . Html::escape($value) . ' (page=1)</p>';

    if ($items === []) {
      $html .= '<div class="messages messages--warning">' .
        $this->t('@msg', ['@msg' => $message !== '' ? $message : 'Nenalezeno.']) .
        '</div>';
      return $html;
    }

    $html .= '<div class="messages messages--status">' .
      $this->t('@msg', ['@msg' => $message !== '' ? $message : 'Nalezeno.']) .
      '</div>';

    $slice = array_slice($items, 0, 3);
    $json = json_encode($slice, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if (!is_string($json)) {
      $json = '[Nelze serializovat JSON]';
    }

    if (mb_strlen($json) > 8000) {
      $json = mb_substr($json, 0, 8000) . "\n…(zkráceno)…";
    }

    $html .= '<pre style="white-space: pre-wrap;">' . Html::escape($json) . '</pre>';

    if (count($items) > 3) {
      $html .= '<p><em>' . $this->t('Zobrazeny první 3 záznamy (celkem na stránce 1: @n).', ['@n' => count($items)]) . '</em></p>';
    }

    return $html;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Not used.
  }

}
