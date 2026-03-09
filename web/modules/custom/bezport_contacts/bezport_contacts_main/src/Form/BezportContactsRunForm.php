<?php

declare(strict_types=1);

namespace Drupal\bezport_contacts_main\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\bezport_contacts_main\Service\BezportContactsOrchestrator;
use Drupal\bezport_contacts_main\Service\NullNotifier;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Manual run form (development usage).
 */
final class BezportContactsRunForm extends FormBase {

  public function __construct(
    private readonly BezportContactsOrchestrator $orchestrator,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('bezport_contacts_main.orchestrator'),
    );
  }

  public function getFormId(): string {
    return 'bezport_contacts_run_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {

    $markup  = 'Tato funkce je primárně určena pro vývojové prostředí.<br>';
    $markup .= 'Při spuštění na produkčním serveru může dojít k vyčerpání paměti nebo času potřebného pro běh skriptu.<br>';
    $markup .= 'Pokud možno, použijte spíše drush příkaz <strong>drush bezport_contacts:run</strong>, nebo aliasy <strong>drush bc-run</strong>, <strong>drush bc-r</strong>.';

    $form['info'] = [
      '#markup' => $markup,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Spustit import kontaktů'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $notifier = new NullNotifier();

    // Full run in one go => track start/end/type.
    $result = $this->orchestrator->runAll('manual', $notifier, TRUE);

    $lines = $this->orchestrator->formatResultsForHtml($result);
    $message = implode('<br>', $lines);

    if (!empty($result['ok'])) {
      $this->messenger()->addStatus($message);
    }
    else {
      $this->messenger()->addError($message);
    }
  }

}
