<?php

declare(strict_types=1);

namespace Drupal\bezport_tools\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

#[Block(
  id: 'bezport_tools_info_above_footer',
  admin_label: new TranslatableMarkup('Info nad patičkou'),
  category: new TranslatableMarkup('Bezport Tools')
)]
final class BezportToolsInfoAboveFooter extends BlockBase {

  public function build(): array {
    return [
      '#markup' => $this->t('Info nad patičkou'),
    ];
  }

}