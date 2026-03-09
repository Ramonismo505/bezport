<?php

declare(strict_types=1);

namespace Drupal\rabp\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[Block(
  id: 'rabp_block',
  admin_label: new TranslatableMarkup('Ra Breakpoint Box')
)]
final class RaBreakpointBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
    );
  }

  public function build(): array {
    $config = $this->configFactory->get('rabp.settings');

    if (!(bool) $config->get('status')) {
      return [];
    }

    $position = (string) ($config->get('position') ?? 'bottomleft');

    $markup  = '<div class="rabp-container ' . $position . '">';
    $markup .= '<p class="content">';
    $markup .= '<span class="query rabp-small">Small</span>';
    $markup .= '<span class="query rabp-smedium">Smedium</span>';
    $markup .= '<span class="query rabp-medium">Medium</span>';
    $markup .= '<span class="query rabp-large">Large</span>';
    $markup .= '<span class="query rabp-xlarge">X-large</span>';
    $markup .= '<span class="query rabp-xxlarge">XX-large</span>';
    $markup .= '<span class="query rabp-xxxlarge">XXX-large</span>';
    $markup .= '<span class="size"></span>';
    $markup .= '</p>';
    $markup .= '</div>';

    return [
      '#markup' => $markup,
      '#attached' => [
        'library' => [
          'rabp/preview',
        ],
      ],
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

  public function blockAccess(AccountInterface $account): AccessResult {
    return AccessResult::allowedIfHasPermission($account, 'view rabp');
  }

}
