<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekSettingsTabTestNewTableForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\String;
use Drupal\lingotek\Form\LingotekConfigFormBase;

/**
 * Configure Lingotek
 */
class LingotekSettingsTabTestNewTableForm extends LingotekConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'lingotek.settings_tab_test_new_table_form';
  }

  /** 
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $profiles = $this->L->get('profile');
    $pullDownProfiles = array();
    foreach($profiles as $profile){
      $pullDownProfiles[] = $profile['name'];
    }
    $menuItems = array('Good', 'Bad', 'Ugly');
    $entities = array('block_content', 'block', 'page');
    $fields = array('Body', 'Title');

    $header = array(
      t('Entity'),
      t('Profile'),
      t('Fields'),
    );
    $table = array(
      '#type' => 'table',
      '#header' => $header,
      '#empty' => t('No Entries'),
    );

    foreach ($entities as $entity) {
      $row = array();
      $name = array('#markup' => $entity);
      $row[] = $name;
      $select = array(
        '#type' => 'select',
        '#options' => $pullDownProfiles,
      );
      $row[] = $select;
      $checkboxRow = array();
      
      foreach ($fields as $field) {
        $checkbox = array(
          '#type' => 'checkbox',
          '#title' => $field,
        );
        $checkboxRow[] = $checkbox;
      }
      $row[] = $checkboxRow;
      $table[$entity] = $row;
    }

    $form['table'] = $table;

     return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    dpm('Logging!');
  }

}
