<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\Entity\Node;

/**
 * Tests translating a node.
 *
 * @group lingotek
 */
class LingotekNodeTranslationDownloadStatusTest extends LingotekTestBase {

  const UNPUBLISHED = 'Save as unpublished';
  const PUBLISHED = 'Save and publish';

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['block', 'node'];

  /**
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('page_title_block');

    // Create Article node types.
    if ($this->profile != 'standard') {
      $this->drupalCreateContentType([
        'type' => 'article',
        'name' => 'Article',
      ]);
    }

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')
      ->setThirdPartySetting('lingotek', 'locale', 'es_MX')
      ->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')
      ->setLanguageAlterable(TRUE)
      ->save();
    \Drupal::service('content_translation.manager')
      ->setEnabled('node', 'article', TRUE);

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->saveLingotekContentTranslationSettingsForNodeTypes();
  }

  /**
   * Tests that a node can be translated and set to unpublished
   * based on the lingotek setting.
   */
  public function testNodeTargetDownloadUnpublishedStatusTranslation() {
    $assert_session = $this->assertSession();

    $this->drupalGet('admin/lingotek/settings');
    $edit = ['target_download_status' => 'unpublished'];
    $this->submitForm($edit, 'Save', 'lingoteksettings-tab-preferences-form');

    // Assert the settings are saved successfully.
    $assert_session->optionExists('edit-target-download-status', 'unpublished');

    // Create a node and complete the translation with Lingotek.
    $this->createAndDownloadANodeTranslation(self::PUBLISHED);

    // Ensure that there is one and only one unpublished content.
    $this->assertSession()->pageTextContains('Not published');
    $this->assertSession()->pageTextContainsOnce('Not published');
  }

  /**
   * Tests that a node can be translated and set to published
   * based on the lingotek setting.
   */
  public function testNodeTargetDownloadPublishedStatusTranslation() {
    $assert_session = $this->assertSession();

    $this->drupalGet('admin/lingotek/settings');
    $edit = ['target_download_status' => 'published'];
    $this->submitForm($edit, 'Save', 'lingoteksettings-tab-preferences-form');

    // Assert the settings are saved successfully.
    $assert_session->optionExists('edit-target-download-status', 'published');

    // Create a node and complete the translation with Lingotek.
    $this->createAndDownloadANodeTranslation(self::UNPUBLISHED);

    // Ensure that there is one and only one published content.
    $this->assertSession()->pageTextContains('Published');
    $this->assertSession()->pageTextContainsOnce('Published');
  }

  /**
   * Tests that a node can be translated as published when using same as source
   * as the lingotek setting.
   */
  public function testNodeTargetDownloadSameAsSourcePublishedStatusTranslation() {
    $assert_session = $this->assertSession();

    $this->drupalGet('admin/lingotek/settings');
    $edit = ['target_download_status' => 'same-as-source'];
    $this->submitForm($edit, 'Save', 'lingoteksettings-tab-preferences-form');

    // Assert the settings are saved successfully.
    $assert_session->optionExists('edit-target-download-status', 'same-as-source');

    // Create a node and complete the translation with Lingotek.
    $this->createAndDownloadANodeTranslation(self::PUBLISHED);

    // Ensure that there is more than one published content.
    $this->assertSession()->pageTextNotContains('Not published');
    $page_text = $this->getSession()->getPage()->getText();
    $nr_found = substr_count($page_text, 'Published');
    $this->assertGreaterThan(1, $nr_found, "'Published' found more than once on the page");
  }

  /**
   * Tests that a node can be translated as published when using same as source
   * as the lingotek setting.
   */
  public function testNodeTargetDownloadSameAsSourceUnpublishedStatusTranslation() {
    $assert_session = $this->assertSession();

    $this->drupalGet('admin/lingotek/settings');
    $edit = ['target_download_status' => 'same-as-source'];
    $this->submitForm($edit, 'Save', 'lingoteksettings-tab-preferences-form');

    // Assert the settings are saved successfully.
    $assert_session->optionExists('edit-target-download-status', 'same-as-source');

    // Create a node and complete the translation with Lingotek.
    $this->createAndDownloadANodeTranslation(self::UNPUBLISHED);

    // Ensure that there is more than one unpublished content.
    $this->assertSession()->pageTextNotContains('Published');
    $page_text = $this->getSession()->getPage()->getText();
    $nr_found = substr_count($page_text, 'Not published');
    $this->assertGreaterThan(1, $nr_found, "'Not published' found more than once on the page");
  }

  /**
   * Helper method for creating and downloading a translation.
   */
  protected function createAndDownloadANodeTranslation($status) {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';

    if ($status === self::PUBLISHED) {
      $this->saveAndPublishNodeForm($edit);
    }
    else {
      $this->saveAsUnpublishedNodeForm($edit);
    }

    $this->node = Node::load(1);

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()
      ->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 2);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEquals(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    $this->assertSame('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertSame('automatic', $used_profile, 'The automatic profile was used.');

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Check Upload Status');
    $this->assertSession()->pageTextContains('The import for node Llamas are cool is complete.');

    // Request translation.
    $this->clickLink('Request translation');
    $this->assertSession()->pageTextContains("Locale 'es_MX' was added as a translation target for node Llamas are cool.");
    $this->assertSame('es_MX', \Drupal::state()
      ->get('lingotek.added_target_locale'));

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertSame('es_MX', \Drupal::state()
      ->get('lingotek.checked_target_locale'));
    $this->assertSession()->pageTextContains('The es_MX translation for node Llamas are cool is ready for download.');

    // Check that the Edit link points to the workbench and it is opened in a new tab.
    $this->assertLingotekWorkbenchLink('es_MX', 'dummy-document-hash-id', 'Edit in Ray Enterprise Workbench');

    // Download translation.
    $this->clickLink('Download completed translation');
    $this->assertSession()->pageTextContains('The translation of node Llamas are cool into es_MX has been downloaded.');
    $this->assertSame('es_MX', \Drupal::state()
      ->get('lingotek.downloaded_locale'));
  }

}
