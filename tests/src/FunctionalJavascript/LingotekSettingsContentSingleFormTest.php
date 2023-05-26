<?php

namespace Drupal\Tests\lingotek\FunctionalJavascript;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;

/**
 * @group lingotek
 */
class LingotekSettingsContentSingleFormTest extends LingotekFunctionalJavascriptTestBase {

  protected static $modules = ['block', 'node', 'field_ui', 'image'];

  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('page_title_block', [
      'region' => 'content',
      'weight' => -5,
    ]);
    $this->drupalPlaceBlock('local_actions_block', [
      'region' => 'content',
      'weight' => -10,
    ]);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')
      ->setThirdPartySetting('lingotek', 'locale', 'es_MX')
      ->save();

    // Create Article node types.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

    $this->createImageField('field_image', 'article');
    $this->createImageField('user_picture', 'user', 'user');

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')
      ->setLanguageAlterable(TRUE)
      ->save();
    \Drupal::service('content_translation.manager')
      ->setEnabled('node', 'article', TRUE);
    ContentLanguageSettings::loadByEntityTypeBundle('user', 'user')
      ->setLanguageAlterable(TRUE)
      ->save();
    \Drupal::service('content_translation.manager')
      ->setEnabled('user', 'user', TRUE);

  }

  public function testWhenEnabledNodeArticleDefaultsAreSet() {
    $this->drupalGet('/admin/lingotek/settings/content/node/article/edit');

    $page = $this->getSession()->getPage();

    $this->assertSession()->checkboxNotChecked('edit-node-article-enabled');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-title');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-body');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-image');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-file');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-alt');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-title');

    $fieldEnabled = $page->find('css', '#edit-node-article-enabled');
    $fieldEnabled->click();

    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertSession()->checkboxChecked('edit-node-article-enabled');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-title');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-body');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-field-image');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-file');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-field-imageproperties-alt');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-field-imageproperties-title');

    $this->submitForm([], 'Save', 'lingoteksettings-content-single-form');

    $this->assertSession()
      ->elementTextContains('css', '.messages.messages--status', 'The configuration options have been saved.');

    $this->assertSession()->checkboxChecked('edit-node-article-enabled');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-title');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-body');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-field-image');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-file');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-field-imageproperties-alt');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-field-imageproperties-title');
  }

  public function testWhenDisabledAndEnabledBackNodeArticleFieldsAreKept() {
    $this->saveLingotekContentTranslationSettings([
      'node' => [
        'article' => [
          'profiles' => 'manual',
          'fields' => [
            'title' => 1,
            'uid' => 1,
            'field_image' => ['alt'],
          ],
        ],
      ],
    ]);

    $this->drupalGet('/admin/lingotek/settings/content/node/article/edit');

    $page = $this->getSession()->getPage();

    $this->assertSession()->checkboxChecked('edit-node-article-enabled');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-title');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-body');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-uid');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-field-image');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-file');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-field-imageproperties-alt');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-title');

    $fieldEnabled = $page->find('css', '#edit-node-article-enabled');
    $fieldEnabled->click();

    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertSession()->checkboxNotChecked('edit-node-article-enabled');

    $fieldEnabled = $page->find('css', '#edit-node-article-enabled');
    $fieldEnabled->click();

    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertSession()->checkboxChecked('edit-node-article-enabled');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-title');
    // We marked body and kept the others as they were.
    $this->assertSession()->checkboxChecked('edit-node-article-fields-body');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-uid');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-field-image');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-file');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-field-imageproperties-alt');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-title');
  }

  public function testFieldPropertiesDisabledIfFieldDisabled() {
    $this->drupalGet('/admin/lingotek/settings/content/node/article/edit');

    $page = $this->getSession()->getPage();

    $this->assertSession()->checkboxNotChecked('edit-node-article-enabled');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-image');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-file');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-alt');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-title');

    $imageCheckbox = $page->find('css', '#edit-node-article-fields-field-image');
    $imageCheckbox->click();

    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertSession()->checkboxChecked('edit-node-article-fields-field-image');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-file');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-field-imageproperties-alt');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-field-imageproperties-title');
  }

}
