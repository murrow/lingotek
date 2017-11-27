<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;

/**
 * Tests setting up the integration with workbench moderation.
 *
 * @group lingotek
 */
class LingotekWorkbenchModerationCustomMenuLinkTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node', 'menu_ui', 'menu_link_content', 'workbench_moderation'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('page_title_block', ['region' => 'content', 'weight' => -5]);
    $this->drupalPlaceBlock('local_tasks_block', ['region' => 'content', 'weight' => -10]);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')
      ->setThirdPartySetting('lingotek', 'locale', 'es_MX')
      ->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('menu_link_content', 'menu_link_content')
      ->setLanguageAlterable(TRUE)
      ->save();
    \Drupal::service('content_translation.manager')
      ->setEnabled('menu_link_content', 'menu_link_content', TRUE);

    drupal_static_reset();
    \Drupal::entityManager()->clearCachedDefinitions();
    \Drupal::service('entity.definition_update_manager')->applyUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $edit = [
      'menu_link_content[menu_link_content][enabled]' => 1,
      'menu_link_content[menu_link_content][profiles]' => 'automatic',
      'menu_link_content[menu_link_content][fields][title]' => 1,
      'menu_link_content[menu_link_content][fields][description]' => 1,
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-content-form');
  }


  /**
   * Entity creation with automatic profile in upload state triggers the upload.
   */
  public function testCreateCustomMenuLink() {
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['description[0][value]'] = 'Llamas are very cool';
    $edit['link[0][uri]'] = '<front>';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'automatic';
    $this->drupalPostForm('/admin/structure/menu/manage/main/add', $edit, t('Save'));

    $this->assertText('The menu link has been saved.');
    $this->assertText('Llamas are cool sent to Lingotek successfully.');
  }

}
