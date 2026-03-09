<?php

declare(strict_types=1);

namespace Drupal\bezport_contacts_main\Service;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final class BezportContactsOrchestrator {

  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly ContainerInterface $container,
    private readonly ImportStateManager $importState,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Import data to temp tables (only import runnery).
   *
   * Tracking is OFF (etapové crony, drush import).
   *
   * @return array{ok:bool, message:string, results:array<string, array>}
   */
  public function import(string $type, ImportNotifierInterface $notifier): array {
    return $this->executePipeline('import', $type, $notifier, FALSE);
  }

  /**
   * Process data (only process runnery).
   *
   * Tracking is OFF.
   *
   * @return array{ok:bool, message:string, results:array<string, array>}
   */
  public function process(string $type, ImportNotifierInterface $notifier): array {
    return $this->executePipeline('process', $type, $notifier, FALSE);
  }

  /**
   * Full run: import + process (+ optional extras).
   *
   * Tracking is ON only when the full chain runs continuously.
   * (manual form, drush run). For etapové crony pass FALSE.
   *
   * @return array{ok:bool, message:string, results:array<string, array>}
   */
  public function runAll(string $type, ImportNotifierInterface $notifier, bool $track_state = TRUE): array {
    return $this->executePipeline('run', $type, $notifier, $track_state);
  }

  /**
   * Formats results into HTML lines for messenger output.
   *
   * @param array{ok:bool, message:string, results:array<string, array>} $result
   * @return string[]
   */
  public function formatResultsForHtml(array $result): array {
    $lines = [];

    if (empty($result['results'])) {
      $lines[] = 'Nebyl spuštěn žádný krok.';
      return $lines;
    }

    foreach ($result['results'] as $step_id => $r) {
      $ok = !empty($r['ok']);
      $msg = (string) ($r['message'] ?? '');
      $stats = $r['stats'] ?? [];

      $stats_str = $this->formatStatsInline(is_array($stats) ? $stats : []);
      $status = $ok ? 'OK' : 'FAIL';

      $label = $this->getStepLabelById((string) $step_id) ?: (string) $step_id;

      $line = $label . ': ' . $status;
      if ($msg !== '' && $msg !== 'OK') {
        $line .= ' – ' . $msg;
      }
      if ($stats_str !== '') {
        $line .= ' (' . $stats_str . ')';
      }
      $lines[] = $line;
    }

    return $lines;
  }

  /**
   * @return array{ok:bool, message:string, results:array<string, array>}
   */
  private function executePipeline(string $mode, string $type, ImportNotifierInterface $notifier, bool $track_state): array {
    $type = $this->normalizeType($type);

    if ($track_state) {
      $this->importState->markStart($type);
    }

    $results = [];
    $ran_any = FALSE;
    $all_ok = TRUE;

    try {
      $steps = $this->getStepsMap($mode);

      foreach ($steps as $step_id => $step) {
        $label = (string) ($step['label'] ?? $step_id);
        $module = (string) ($step['module'] ?? '');
        $runner_service = (string) ($step['runner_service'] ?? '');

        // Skip if module not installed.
        if ($module !== '' && !$this->moduleHandler->moduleExists($module)) {
          $notifier->warning($label . ': přeskočeno (modul není nainstalován).');
          $results[$step_id] = ['ok' => TRUE, 'message' => 'skipped: module not installed', 'stats' => []];
          continue;
        }

        // Skip if runner service missing.
        if ($runner_service === '' || !$this->container->has($runner_service)) {
          $notifier->warning($label . ': přeskočeno (runner service nenalezen).');
          $results[$step_id] = ['ok' => TRUE, 'message' => 'skipped: runner service missing', 'stats' => []];
          continue;
        }

        $ran_any = TRUE;
        $notifier->info('Running: ' . $label);

        $runner = $this->container->get($runner_service);

        // Prefer strict interface, but allow legacy runner with run() for now.
        if ($runner instanceof RunnerInterface) {
          $result = $runner->run($type, $notifier);
        }
        else {
          if (!method_exists($runner, 'run')) {
            throw new \RuntimeException('Runner service "' . $runner_service . '" has no run() method.');
          }
          /** @var array{ok:bool, message:string, stats:array<string, mixed>} $result */
          $result = $runner->run($type, $notifier);
        }

        $results[$step_id] = $result;

        if (empty($result['ok'])) {
          $all_ok = FALSE;
          $notifier->error($label . ': FAIL - ' . (string) ($result['message'] ?? ''));
          // KISS: stop on first failure.
          break;
        }

        $notifier->success($label . ': OK');
      }

      if (!$ran_any) {
        $all_ok = FALSE;
        $msg = 'No steps executed (no modules/runners available).';
        $notifier->error($msg);
        $this->logger->warning($msg);
      }

      return [
        'ok' => $all_ok,
        'message' => $all_ok ? 'OK' : 'FAILED',
        'results' => $results,
      ];
    }
    catch (\Throwable $e) {
      $all_ok = FALSE;
      $notifier->error('Pipeline exception: ' . $e->getMessage());
      $this->logger->error('Pipeline exception: @msg', ['@msg' => $e->getMessage()]);

      return [
        'ok' => FALSE,
        'message' => $e->getMessage(),
        'results' => $results,
      ];
    }
    finally {
      if ($track_state) {
        $this->importState->markEnd();
      }
    }
  }

  /**
   * Steps map by mode.
   *
   * @return array<string, array{label:string, module:string, runner_service:string}>
   */
  private function getStepsMap(string $mode): array {
    // Import steps (temp tables).
    $import = [
      'import_rgs' => [
        'label' => 'Import: Roles + Groups + Relations + Subjects',
        'module' => 'bezport_contacts_import_roles_groups_subjects',
        'runner_service' => 'bezport_contacts_import_rgs.runner',
      ],
      'import_persons' => [
        'label' => 'Import: Persons + Relations (person_subject_roles)',
        'module' => 'bezport_contacts_import_persons',
        'runner_service' => 'bezport_contacts_import_persons.runner',
      ],
    ];

    // Process steps.
    $process = [
      'process_rgs' => [
        'label' => 'Process: Roles/Groups/Subjects',
        'module' => 'bezport_contacts_process_roles_groups_subjects',
        'runner_service' => 'bezport_contacts_process_rgs.runner',
      ],
      'process_persons' => [
        'label' => 'Process: Persons',
        'module' => 'bezport_contacts_process_persons',
        'runner_service' => 'bezport_contacts_process_persons.runner',
      ],
    ];

    if ($mode === 'import') {
      return $import;
    }
    if ($mode === 'process') {
      return $process;
    }

    // mode === 'run'
    return $import + $process;
  }

  private function getStepLabelById(string $step_id): string {
    $all = $this->getStepsMap('run');
    return (string) ($all[$step_id]['label'] ?? '');
  }

  private function normalizeType(string $type): string {
    $type = strtolower(trim($type));
    return in_array($type, ['cron', 'manual', 'drush'], TRUE) ? $type : 'manual';
  }

  /**
   * @param array<string, mixed> $stats
   */
  private function formatStatsInline(array $stats): string {
    if ($stats === []) {
      return '';
    }
    $out = [];
    foreach ($stats as $k => $v) {
      if (is_scalar($v)) {
        $out[] = $k . '=' . (string) $v;
      }
    }
    return $out ? implode(', ', $out) : '';
  }

}
