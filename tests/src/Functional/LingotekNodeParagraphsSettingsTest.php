<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;

/**
 * Tests unsetting translatability in paragraphs does not disable Lingotek.
 *
 * @group lingotek
 * @group legacy
 */
class LingotekNodeParagraphsSettingsTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'node', 'image', 'paragraphs', 'lingotek_paragraphs_test'];

  /**
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('page_title_block', ['region' => 'content', 'weight' => -5]);
    $this->drupalPlaceBlock('local_tasks_block', ['region' => 'content', 'weight' => -10]);

    // Add locales.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_ES')->save();
    ConfigurableLanguage::createFromLangcode('es-ar')->setThirdPartySetting('lingotek', 'locale', 'es_AR')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'paragraphed_content_demo')->setLanguageAlterable(TRUE)->save();
    ContentLanguageSettings::loadByEntityTypeBundle('paragraph', 'image_text')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'paragraphed_content_demo', TRUE);
    \Drupal::service('content_translation.manager')->setEnabled('paragraph', 'image_text', TRUE);

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->setParagraphFieldsTranslatability();

    $this->saveLingotekContentTranslationSettings([
      'node' => [
        'paragraphed_content_demo' => [
          'profiles' => 'automatic',
          'fields' => [
            'title' => 1,
            'field_paragraphs_demo' => 1,
          ],
        ],
      ],
      'paragraph' => [
        'image_text' => [
          'fields' => [
            'field_image_demo' => ['title', 'alt'],
            'field_text_demo' => 1,
          ],
        ],
      ],
    ]);

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+paragraphs');
  }

  /**
   * Tests that disabling content translation doesn't change lingotek translation settings for paragraphs.
   */
  public function testDisablingContentTranslationDoesntDisableLingotekTranslationForParagraphs() {
    $this->drupalGet('admin/lingotek/settings');
    $this->assertSession()->fieldValueEquals('node[paragraphed_content_demo][fields][field_paragraphs_demo]', TRUE);
    $this->assertSession()->fieldValueEquals('paragraph[image_text][fields][field_text_demo]', TRUE);

    $edit = [];
    $edit['settings[node][paragraphed_content_demo][fields][field_paragraphs_demo]'] = FALSE;
    $edit['settings[paragraph][image_text][fields][field_text_demo]'] = FALSE;
    $this->drupalGet('/admin/config/regional/content-language');
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->responseContains('Settings successfully updated.');

    $this->drupalGet('admin/lingotek/settings');
    // The paragraph is still enabled.
    $this->assertSession()->fieldValueEquals('node[paragraphed_content_demo][fields][field_paragraphs_demo]', TRUE);
    // The text field was disabled, and it's not even present.
    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotekConfig */
    $lingotekConfig = \Drupal::service('lingotek.configuration');
    $this->assertFalse($lingotekConfig->isFieldLingotekEnabled('paragraph', 'image_text', 'field_text_demo'));
    $this->assertSession()->fieldValueNotEquals('paragraph[image_text][fields][field_text_demo]', '');
  }

  protected function setParagraphFieldsTranslatability(): void {
    $edit = [];
    $edit['settings[node][paragraphed_content_demo][fields][field_paragraphs_demo]'] = 1;
    $edit['settings[paragraph][image_text][fields][field_text_demo]'] = 1;
    $this->drupalGet('/admin/config/regional/content-language');
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->responseContains('Settings successfully updated.');
  }

}
