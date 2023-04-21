<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\taxonomy\Entity\Term;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Tests translating a taxonomy term with different download status settings.
 *
 * @group lingotek
 */
class LingotekTaxonomyTermTranslationDownloadStatusTest extends LingotekTestBase {

  use TaxonomyTestTrait;

  const UNPUBLISHED = 'Save as unpublished';
  const PUBLISHED = 'Save and publish';

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'node', 'taxonomy'];

  /**
   * Vocabulary for testing.
   *
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected $vocabulary;

  /**
   * The term that should be translated.
   *
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $term;

  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('page_title_block');

    $this->vocabulary = $this->createVocabulary();

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')
      ->setThirdPartySetting('lingotek', 'locale', 'es_MX')
      ->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('taxonomy_term', $this->vocabulary->id())->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('taxonomy_term', $this->vocabulary->id(), TRUE);

    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $bundle = $this->vocabulary->id();
    $this->saveLingotekContentTranslationSettings([
      'taxonomy_term' => [
        $bundle => [
          'profiles' => 'automatic',
          'fields' => [
            'name' => 1,
            'description' => 1,
          ],
        ],
      ],
    ]);
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'taxonomy_term');
  }

  /**
   * Tests that a a taxonomy term can be translated and set to unpublished
   * based on the lingotek setting.
   */
  public function testTargetDownloadUnpublishedStatusTranslation() {
    $assert_session = $this->assertSession();

    $this->drupalGet('admin/lingotek/settings');
    $edit = ['target_download_status' => 'unpublished'];
    $this->submitForm($edit, 'Save', 'lingoteksettings-tab-preferences-form');

    // Assert the settings are saved successfully.
    $assert_session->optionExists('edit-target-download-status', 'unpublished');

    // Create a node and complete the translation with Lingotek.
    $this->createAndDownloadATaxonomyTermTranslation(self::PUBLISHED);

    // Ensure that there is one and only one unpublished content.
    $this->assertText('Not published');
    $this->assertUniqueText('Not published');
  }

  /**
   * Tests that a taxonomy term can be translated and set to published
   * based on the lingotek setting.
   */
  public function testTargetDownloadPublishedStatusTranslation() {
    $assert_session = $this->assertSession();

    $this->drupalGet('admin/lingotek/settings');
    $edit = ['target_download_status' => 'published'];
    $this->submitForm($edit, 'Save', 'lingoteksettings-tab-preferences-form');

    // Assert the settings are saved successfully.
    $assert_session->optionExists('edit-target-download-status', 'published');

    // Create a node and complete the translation with Lingotek.
    $this->createAndDownloadATaxonomyTermTranslation(self::UNPUBLISHED);

    // Ensure that there is one and only one published content.
    $this->assertText('Published');
    $this->assertUniqueText('Published');
  }

  /**
   * Tests that a taxonomy term can be translated as published when using same as source
   * as the lingotek setting.
   */
  public function testTargetDownloadSameAsSourcePublishedStatusTranslation() {
    $assert_session = $this->assertSession();

    $this->drupalGet('admin/lingotek/settings');
    $edit = ['target_download_status' => 'same-as-source'];
    $this->submitForm($edit, 'Save', 'lingoteksettings-tab-preferences-form');

    // Assert the settings are saved successfully.
    $assert_session->optionExists('edit-target-download-status', 'same-as-source');

    // Create a node and complete the translation with Lingotek.
    $this->createAndDownloadATaxonomyTermTranslation(self::PUBLISHED);

    // Ensure that there is more than one published content.
    $this->assertNoText('Not published');
    $this->assertNoUniqueText('Published');
  }

  /**
   * Tests that a taxonomy term can be translated as published when using same as source
   * as the lingotek setting.
   */
  public function testTargetDownloadSameAsSourceUnpublishedStatusTranslation() {
    $assert_session = $this->assertSession();

    $this->drupalGet('admin/lingotek/settings');
    $edit = ['target_download_status' => 'same-as-source'];
    $this->submitForm($edit, 'Save', 'lingoteksettings-tab-preferences-form');

    // Assert the settings are saved successfully.
    $assert_session->optionExists('edit-target-download-status', 'same-as-source');

    // Create a node and complete the translation with Lingotek.
    $this->createAndDownloadATaxonomyTermTranslation(self::UNPUBLISHED);

    // Ensure that there is more than one unpublished content.
    $this->assertNoText('Published');
    $this->assertNoUniqueText('Not published');
  }

  /**
   * Helper method for creating and downloading a translation.
   */
  protected function createAndDownloadATaxonomyTermTranslation($status) {
    $bundle = $this->vocabulary->id();

    // Create a term.
    $edit = [];
    $edit['name[0][value]'] = 'Llamas are cool';
    $edit['description[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';

    if ($status === self::PUBLISHED) {
      $edit['status[value]'] = TRUE;
    }
    else {
      $edit['status[value]'] = FALSE;
    }

    $this->drupalPostForm("admin/structure/taxonomy/manage/$bundle/add", $edit, t('Save'));

    $this->term = Term::load(1);

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()
      ->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 2);
    $this->assertTrue(isset($data['name'][0]['value']));
    $this->assertEqual(1, count($data['description'][0]));
    $this->assertTrue(isset($data['description'][0]['value']));
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('automatic', $used_profile, 'The automatic profile was used.');

    // Check that the translate tab is in the term.
    $this->drupalGet('taxonomy/term/1');
    $this->clickLink('Translate');

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Check Upload Status');
    $this->assertText('The import for taxonomy_term Llamas are cool is complete.');

    // Request translation.
    $this->clickLink('Request translation');
    $this->assertText("Locale 'es_MX' was added as a translation target for taxonomy_term Llamas are cool.");
    $this->assertIdentical('es_MX', \Drupal::state()
      ->get('lingotek.added_target_locale'));

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertIdentical('es_MX', \Drupal::state()
      ->get('lingotek.checked_target_locale'));
    $this->assertText('The es_MX translation for taxonomy_term Llamas are cool is ready for download.');

    // Check that the Edit link points to the workbench and it is opened in a new tab.
    $this->assertLingotekWorkbenchLink('es_MX', 'dummy-document-hash-id', 'Edit in Ray Enterprise Workbench');

    // Download translation.
    $this->clickLink('Download completed translation');
    $this->assertText('The translation of taxonomy_term Llamas are cool into es_MX has been downloaded.');
    $this->assertIdentical('es_MX', \Drupal::state()
      ->get('lingotek.downloaded_locale'));
  }

}
