<?php

namespace Drupal\Tests\lingotek\FunctionalJavascript;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;

/**
 * @group lingotek
 */
class LingotekSettingsTabContentFormWithLotsOfContentTest extends LingotekFunctionalJavascriptTestBase {

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

    foreach (range(1, 150) as $i) {
      $this->drupalCreateContentType([
        'type' => 'content_type_' . $i,
        'name' => 'Content Type ' . $i,
      ]);
    }

    $this->createImageField('field_image', 'article');
    $this->createImageField('user_picture', 'user', 'user');

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')
      ->setLanguageAlterable(TRUE)
      ->save();
    \Drupal::service('content_translation.manager')
      ->setEnabled('node', 'article', TRUE);

    foreach (range(1, 150) as $i) {
      ContentLanguageSettings::loadByEntityTypeBundle('node', 'content_type_' . $i)
        ->setLanguageAlterable(TRUE)
        ->save();
      \Drupal::service('content_translation.manager')
        ->setEnabled('node', 'content_type_' . $i, TRUE);
    }

    ContentLanguageSettings::loadByEntityTypeBundle('user', 'user')
      ->setLanguageAlterable(TRUE)
      ->save();
    \Drupal::service('content_translation.manager')
      ->setEnabled('user', 'user', TRUE);

  }

  public function testWhenEnabledNodeArticleDefaultsAreSet() {
    $this->drupalGet('/admin/lingotek/settings');

    $page = $this->getSession()->getPage();
    $contentTabDetails = $page->find('css', '#edit-parent-details');
    $contentTabDetails->click();
    $nodeTabDetails = $page->find('css', '#edit-entity-node');
    $nodeTabDetails->click();

    $this->assertSession()->checkboxNotChecked('edit-node-article-readonly-enabled');
    $this->assertSession()->checkboxNotChecked('edit-node-article-readonly-fields-title');
    $this->assertSession()->checkboxNotChecked('edit-node-article-readonly-fields-body');
    $this->assertSession()->checkboxNotChecked('edit-node-article-readonly-fields-field-image');
    $this->assertSession()->checkboxNotChecked('edit-node-article-readonly-fields-field-imageproperties-file');
    $this->assertSession()->checkboxNotChecked('edit-node-article-readonly-fields-field-imageproperties-alt');
    $this->assertSession()->checkboxNotChecked('edit-node-article-readonly-fields-field-imageproperties-title');

    $this->assertSession()->fieldDisabled('edit-node-article-readonly-enabled');
    $this->assertSession()->fieldDisabled('edit-node-article-readonly-fields-title');
    $this->assertSession()->fieldDisabled('edit-node-article-readonly-fields-body');
    $this->assertSession()->fieldDisabled('edit-node-article-readonly-fields-field-image');
    $this->assertSession()->fieldDisabled('edit-node-article-readonly-fields-field-imageproperties-file');
    $this->assertSession()->fieldDisabled('edit-node-article-readonly-fields-field-imageproperties-alt');
    $this->assertSession()->fieldDisabled('edit-node-article-readonly-fields-field-imageproperties-title');

    $linkToOpenDialog = $page->find('css', '#edit-node-article-content-type-edit');
    $linkToOpenDialog->click();

    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->waitForElementVisible('css', '#drupal-modal');

    $this->assertSession()->checkboxNotChecked('node[article][enabled]');
    $this->assertSession()->checkboxNotChecked('node[article][fields][title]');
    $this->assertSession()->checkboxNotChecked('node[article][fields][body]');
    $this->assertSession()->checkboxNotChecked('node[article][fields][field_image]');
    $this->assertSession()->checkboxNotChecked('node[article][fields][field_image:properties][file]');
    $this->assertSession()->checkboxNotChecked('node[article][fields][field_image:properties][alt]');
    $this->assertSession()->checkboxNotChecked('node[article][fields][field_image:properties][title]');

    $fieldEnabled = $page->find('css', 'input[name="node[article][enabled]"]');
    $fieldEnabled->click();

    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertSession()->checkboxChecked('node[article][enabled]');
    $this->assertSession()->checkboxChecked('node[article][fields][title]');
    $this->assertSession()->checkboxChecked('node[article][fields][body]');
    $this->assertSession()->checkboxChecked('node[article][fields][field_image]');
    $this->assertSession()->checkboxNotChecked('node[article][fields][field_image:properties][file]');
    $this->assertSession()->checkboxChecked('node[article][fields][field_image:properties][alt]');
    $this->assertSession()->checkboxChecked('node[article][fields][field_image:properties][title]');

    $button_pane_buttons = $this->getSession()->getPage()->findAll('css', '.ui-dialog-buttonpane button');
    $this->assertCount(1, $button_pane_buttons);
    $button = $button_pane_buttons[0];
    $button->press();

    $this->assertSession()
      ->elementTextContains('css', '.messages.messages--status', 'The configuration options have been saved.');

    $page = $this->getSession()->getPage();
    $contentTabDetails = $page->find('css', '#edit-parent-details');
    $contentTabDetails->click();
    $nodeTabDetails = $page->find('css', '#edit-entity-node');
    $nodeTabDetails->click();

    $this->assertSession()->checkboxChecked('edit-node-article-readonly-enabled');
    $this->assertSession()->checkboxChecked('edit-node-article-readonly-fields-title');
    $this->assertSession()->checkboxChecked('edit-node-article-readonly-fields-body');
    $this->assertSession()->checkboxChecked('edit-node-article-readonly-fields-field-image');
    $this->assertSession()->checkboxNotChecked('edit-node-article-readonly-fields-field-imageproperties-file');
    $this->assertSession()->checkboxChecked('edit-node-article-readonly-fields-field-imageproperties-alt');
    $this->assertSession()->checkboxChecked('edit-node-article-readonly-fields-field-imageproperties-title');

    $this->assertSession()->fieldDisabled('edit-node-article-readonly-enabled');
    $this->assertSession()->fieldDisabled('edit-node-article-readonly-fields-title');
    $this->assertSession()->fieldDisabled('edit-node-article-readonly-fields-body');
    $this->assertSession()->fieldDisabled('edit-node-article-readonly-fields-field-image');
    $this->assertSession()->fieldDisabled('edit-node-article-readonly-fields-field-imageproperties-file');
    $this->assertSession()->fieldDisabled('edit-node-article-readonly-fields-field-imageproperties-alt');
    $this->assertSession()->fieldDisabled('edit-node-article-readonly-fields-field-imageproperties-title');
  }

}
