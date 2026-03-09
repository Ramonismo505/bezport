<?php

namespace Drupal\bezport_contacts_export\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Cache\Cache;
use Drupal\Component\Utility\UrlHelper;


/**
 * @Block(
 *  id = "bezport_contacts_export_persons_button_block",
 *  admin_label = @Translation("Export kontaktů (osoby) (Tlačítko)")
 * )
 */
class BezportContactExportPersonsButtonBlock extends BlockBase {
  
    public function build() {    
    $markup  = '';
    $query_all = \Drupal::request()->query->all();
    $query_string = UrlHelper::buildQuery($query_all);
    if(!empty($query_string)){
      $query_string = '?'.$query_string;
    }    
    $markup .= '<div class="contacts-export-wrapper">';
    $markup .= '<a href="/kontakty-osoby-export/excel'.$query_string.'">Export kontaktů ve formátu Excel</a> ';
    $markup .= '</div>';    
    $output = ['#markup' => $markup];    
    return $output;    
  }
  
  public function getCacheMaxAge(){
    return 0;    
  }
  
}