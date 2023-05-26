<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\Lingotek;
use Drupal\node\Entity\NodeType;

/**
 * Tests translating a content type.
 *
 * @group lingotek
 */
class LingotekContentTypeTranslationTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'node', 'image'];

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
    $this->drupalPlaceBlock('page_title_block', ['region' => 'header', 'weight' => -5]);
    $this->drupalPlaceBlock('local_tasks_block', ['region' => 'header', 'weight' => -10]);

    // Create Article node types.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);
    $this->saveLingotekConfigTranslationSettings([
      'node_type' => 'automatic',
    ]);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();
  }

  /**
   * Tests that a node can be translated.
   */
  public function testContentTypeTranslation() {
    $assert_session = $this->assertSession();
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'content_type');

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->drupalGet('/admin/config/regional/config-translation');
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    $this->clickLink(t('Upload'));
    $this->assertSession()->pageTextContains(t('Article uploaded successfully'));

    // Check that only the translatable fields have been uploaded.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertEquals(3, count($data));
    $this->assertTrue(array_key_exists('name', $data));
    // Cannot use isset, the key exists but we are not providing values, so NULL.
    $this->assertTrue(array_key_exists('description', $data));
    $this->assertTrue(array_key_exists('help', $data));

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertSame('automatic', $used_profile, 'The automatic profile was used.');

    $this->clickLink(t('Check upload status'));
    $this->assertSession()->pageTextContains(t('Article status checked successfully'));

    $this->clickLink(t('Request translation'));
    $this->assertSession()->pageTextContains(t('Translation to es_MX requested successfully'));
    $this->assertSame('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    $this->clickLink(t('Check Download'));
    $this->assertSession()->pageTextContains(t('Translation to es_MX status checked successfully'));

    $this->clickLink('Download');
    $this->assertSession()->pageTextContains(t('Translation to es_MX downloaded successfully'));

    // Check that the edit link is there.
    $basepath = \Drupal::request()->getBasePath();
    $assert_session->linkByHrefExists($basepath . '/admin/structure/types/manage/article/translate/es/edit');
  }

  /**
   * Tests that a config can be translated after edited.
   */
  public function testEditedContentTypeTranslation() {
    $assert_session = $this->assertSession();
    // We need a config with translations first.
    $this->testContentTypeTranslation();

    // Add a language so we can check that it's not marked as dirty if there are
    // no translations.
    ConfigurableLanguage::createFromLangcode('eu')->setThirdPartySetting('lingotek', 'locale', 'eu_ES')->save();

    // Edit the content type.
    $edit['name'] = 'Blogpost';
    $this->drupalGet('/admin/structure/types/manage/article');
    $this->submitForm($edit, t('Save content type'));
    $this->assertSession()->pageTextContains('The content type Blogpost has been updated.');

    $this->clickLink(t('Translate'));

    // Check the status is not edited for Vasque, but available to request
    // translation.
    $assert_session->linkByHrefExists('admin/lingotek/config/request/node_type/article/eu_ES');
    $assert_session->linkByHrefNotExists('admin/lingotek/config/request/node_type/article/es_MX');

    // Recheck status.
    $this->clickLink('Check Download');
    $this->assertSession()->pageTextContains('Translation to es_MX status checked successfully');

    // Download the translation.
    $this->clickLink('Download');
    $this->assertSession()->pageTextContains('Translation to es_MX downloaded successfully');
  }

  /**
   * Tests that no translation can be requested if the language is disabled.
   */
  public function testLanguageDisabled() {
    $assert_session = $this->assertSession();
    // Add a language.
    $italian = ConfigurableLanguage::createFromLangcode('it')
      ->setThirdPartySetting('lingotek', 'locale', 'it_IT');
    $italian->save();

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->drupalGet('/admin/config/regional/config-translation');
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    $this->clickLink(t('Upload'));
    $this->assertSession()->pageTextContains(t('Article uploaded successfully'));

    // Check that only the translatable fields have been uploaded.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertEquals(3, count($data));
    $this->assertTrue(array_key_exists('name', $data));
    // Cannot use isset, the key exists but we are not providing values, so NULL.
    $this->assertTrue(array_key_exists('description', $data));
    $this->assertTrue(array_key_exists('help', $data));
    $this->assertSame('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertSame('automatic', $used_profile, 'The automatic profile was used.');

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Check upload status');
    $this->assertSession()->pageTextContains(t('Article status checked successfully'));

    // There are two links for requesting translations, or we can add them
    // manually.
    $assert_session->linkByHrefExists('/admin/lingotek/config/request/node_type/article/it_IT');
    $assert_session->linkByHrefExists('/admin/lingotek/config/request/node_type/article/es_MX');
    $assert_session->linkByHrefExists('/admin/structure/types/manage/article/translate/it/add');
    $assert_session->linkByHrefExists('/admin/structure/types/manage/article/translate/es/add');

    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_config */
    $lingotek_config = \Drupal::service('lingotek.configuration');
    $lingotek_config->disableLanguage($italian);

    // Check that the translate tab is in the node.
    $this->drupalGet('/admin/structure/types/manage/article/translate');

    // Italian is not present anymore, but still can add a translation.
    $assert_session->linkByHrefNotExists('/admin/lingotek/config/request/node_type/article/it_IT');
    $assert_session->linkByHrefExists('/admin/lingotek/config/request/node_type/article/es_MX');
    $assert_session->linkByHrefExists('/admin/structure/types/manage/article/translate/it/add');
    $assert_session->linkByHrefExists('/admin/structure/types/manage/article/translate/es/add');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUploadingWithAnError() {
    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    // Check that the translate tab is in the node type.
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    // Upload the document, which must fail.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextContains('Article upload failed. Please try again.');

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEquals(Lingotek::STATUS_ERROR, $source_status, 'The node type has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_error_in_upload', FALSE);
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextContains('Article uploaded successfully');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUploadingWithAPaymentRequiredError() {
    \Drupal::state()->set('lingotek.must_payment_required_error_in_upload', TRUE);

    // Check that the translate tab is in the node type.
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    // Upload the document, which must fail.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextContains('Community has been disabled. Please contact support@lingotek.com to re-enable your community.');

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEquals(Lingotek::STATUS_ERROR, $source_status, 'The node type has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_payment_required_error_in_upload', FALSE);
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextContains('Article uploaded successfully');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUploadingWithAPaymentRequiredErrorViaAutomaticUpload() {
    $this->saveLingotekConfigTranslationSettings([
      'node_type' => 'automatic',
    ]);

    \Drupal::state()->set('lingotek.must_payment_required_error_in_upload', TRUE);

    // Create a content type.
    $edit = ['name' => 'Landing Page', 'type' => 'landing_page'];
    $this->drupalGet('admin/structure/types/add');
    $this->submitForm($edit, 'Save content type');

    // The document was uploaded automatically and failed.
    $this->assertSession()->pageTextContains('Community has been disabled. Please contact support@lingotek.com to re-enable your community.');

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('landing_page');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEquals(Lingotek::STATUS_ERROR, $source_status, 'The node type has been marked as error.');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUpdatingWithAPaymentRequiredError() {
    // Check that the translate tab is in the node type.
    $this->drupalGet('/admin/config/regional/config-translation');
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    // Upload the document, which must succeed.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextContains('Article uploaded successfully');

    // Check that the upload succeeded.
    $this->clickLink('Check upload status');
    $this->assertSession()->pageTextContains('Article status checked successfully');

    // Edit the content type.
    $edit['name'] = 'Blogpost';
    $this->drupalGet('/admin/structure/types/manage/article');
    $this->submitForm($edit, t('Save content type'));
    $this->assertSession()->pageTextContains('The content type Blogpost has been updated.');

    // Go back to the form.
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    \Drupal::state()->set('lingotek.must_payment_required_error_in_update', TRUE);

    // Re-upload. Must fail now.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextContains('Community has been disabled. Please contact support@lingotek.com to re-enable your community.');

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEquals(Lingotek::STATUS_ERROR, $source_status, 'The node type has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_payment_required_error_in_update', FALSE);
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextContains('Blogpost has been updated.');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUpdatingWithADocumentNotFoundError() {
    // Check that the translate tab is in the node type.
    $this->drupalGet('/admin/config/regional/config-translation');
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    // Upload the document, which must succeed.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextContains('Article uploaded successfully');

    // Check that the upload succeeded.
    $this->clickLink('Check upload status');
    $this->assertSession()->pageTextContains('Article status checked successfully');

    // Edit the content type.
    $edit['name'] = 'Blogpost';
    $this->drupalGet('/admin/structure/types/manage/article');
    $this->submitForm($edit, t('Save content type'));
    $this->assertSession()->pageTextContains('The content type Blogpost has been updated.');

    // Go back to the form.
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    \Drupal::state()->set('lingotek.must_document_not_found_error_in_update', TRUE);

    // Re-upload. Must fail now.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEquals(Lingotek::STATUS_UNTRACKED, $source_status);
    $this->assertSession()->pageTextContains('Document Blogpost was not found. Please upload again.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_document_not_found_error_in_update', FALSE);
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextContains('Blogpost uploaded successfully');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUpdatingWithAPaymentRequiredErrorViaAutomaticUpload() {
    // Check that the translate tab is in the node type.
    $this->drupalGet('/admin/config/regional/config-translation');
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    // Upload the document, which must succeed.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextContains('Article uploaded successfully');

    // Check that the upload succeeded.
    $this->clickLink('Check upload status');
    $this->assertSession()->pageTextContains('Article status checked successfully');

    \Drupal::state()->set('lingotek.must_payment_required_error_in_update', TRUE);

    // Edit the content type.
    $edit['name'] = 'Blogpost';
    $this->drupalGet('/admin/structure/types/manage/article');
    $this->submitForm($edit, t('Save content type'));
    $this->assertSession()->pageTextContains('The content type Blogpost has been updated.');

    $this->assertSession()->pageTextContains('Community has been disabled. Please contact support@lingotek.com to re-enable your community.');

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEquals(Lingotek::STATUS_ERROR, $source_status, 'The node type has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_payment_required_error_in_update', FALSE);
    $this->clickLink(t('Translate'));
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextContains('Blogpost has been updated.');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUploadingWithAProcessedWordsLimitError() {
    \Drupal::state()->set('lingotek.must_processed_words_limit_error_in_upload', TRUE);

    // Check for translate tab in the node type.
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    // Upload the document, will fail
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextContains('Processed word limit exceeded. Please contact your local administrator or Lingotek Client Success (sales@lingotek.com) for assistance.');

    // The node type has been marked with the error status.
    $nodeType = Nodetype::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEquals(Lingotek::STATUS_ERROR, $source_status, 'The node type has been marked as error.');

  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUploadingWithAProcessedWordsLimitErrorViaAutomaticUpload() {
    $this->saveLingotekConfigTranslationSettings([
      'node_type' => 'automatic',
    ]);

    \Drupal::state()->set('lingotek.must_processed_words_limit_error_in_upload', TRUE);

    // Create content type
    $edit = ['name' => 'Landing Page', 'type' => 'landing_page'];
    $this->drupalGet('admin/structure/types/add');
    $this->submitForm($edit, 'Save content type');

    // The document was uploaded automatically and failed.
    $this->assertSession()->pageTextContains('Processed word limit exceeded. Please contact your local administrator or Lingotek Client Success (sales@lingotek.com) for assistance.');

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('landing_page');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEquals(Lingotek::STATUS_ERROR, $source_status, 'The node type has been marked as error.');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUpdatingWithAProcessedWordsLimitError() {
    // Check that the translate tab is in the node type.
    $this->drupalGet('/admin/config/regional/config-translation');
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    // Upload the document successfully
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextContains('Article uploaded successfully');

    // Check that upload succeeded.
    $this->clickLink('Check upload status');
    $this->assertSession()->pageTextContains('Article status checked successfully');

    // Edit the content type.
    $edit['name'] = 'Blogpost';
    $this->drupalGet('/admin/structure/types/manage/article');
    $this->submitForm($edit, t('Save content type'));
    $this->assertSession()->pageTextContains('The content type Blogpost has been updated');

    // Go back to the form
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    \Drupal::state()->set('lingotek.must_processed_words_limit_error_in_update', TRUE);

    // Re-upload. Must fail now.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextContains('Processed word limit exceeded. Please contact your local administrator or Lingotek Client Success (sales@lingotek.com) for assistance.');

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEquals(Lingotek::STATUS_ERROR, $source_status, 'The node type has been marked as error.');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUpdatingWithAProcessedWordsLimitErrorViaAutomaticUpload() {
    // Check that the translate tab is in the node type.
    $this->drupalGet('/admin/config/regional/config-translation');
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    // Upload the document, which must succeed.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextContains('Article uploaded successfully');

    // Check that the upload succeeded.
    $this->clickLink('Check upload status');
    $this->assertSession()->pageTextContains('Article status checked successfully');

    \Drupal::state()->set('lingotek.must_processed_words_limit_error_in_update', TRUE);

    // Edit the content type.
    $edit['name'] = 'Blogpost';
    $this->drupalGet('/admin/structure/types/manage/article');
    $this->submitForm($edit, t('Save content type'));
    $this->assertSession()->pageTextContains('The content type Blogpost has been updated.');

    $this->assertSession()->pageTextContains('Processed word limit exceeded. Please contact your local administrator or Lingotek Client Success (sales@lingotek.com) for assistance.');

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEquals(Lingotek::STATUS_ERROR, $source_status, 'The node type has been marked as error.');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUpdatingWithADocumentNotFoundErrorViaAutomaticUpload() {
    // Check that the translate tab is in the node type.
    $this->drupalGet('/admin/config/regional/config-translation');
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    // Upload the document, which must succeed.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextContains('Article uploaded successfully');

    // Check that the upload succeeded.
    $this->clickLink('Check upload status');
    $this->assertSession()->pageTextContains('Article status checked successfully');

    \Drupal::state()->set('lingotek.must_document_not_found_error_in_update', TRUE);

    // Edit the content type.
    $edit['name'] = 'Blogpost';
    $this->drupalGet('/admin/structure/types/manage/article');
    $this->submitForm($edit, t('Save content type'));
    $this->assertSession()->pageTextContains('The content type Blogpost has been updated.');

    $nodeType = NodeType::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEquals(Lingotek::STATUS_UNTRACKED, $source_status);
    $this->assertSession()->pageTextContains('Document node_type Blogpost was not found. Please upload again.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_document_not_found_error_in_update', FALSE);
    $this->clickLink(t('Translate'));
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextContains('Blogpost uploaded successfully');
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithAnError() {
    // Check that the translate tab is in the node type.
    $this->drupalGet('/admin/config/regional/config-translation');
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    // Upload the document, which must succeed.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextContains('Article uploaded successfully');

    // Check that the upload succeeded.
    $this->clickLink('Check upload status');
    $this->assertSession()->pageTextContains('Article status checked successfully');

    // Edit the content type.
    $edit['name'] = 'Blogpost';
    $this->drupalGet('/admin/structure/types/manage/article');
    $this->submitForm($edit, t('Save content type'));
    $this->assertSession()->pageTextContains('The content type Blogpost has been updated.');

    // Go back to the form.
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    // Re-upload. Must fail now.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextContains('Blogpost update failed. Please try again.');

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEquals(Lingotek::STATUS_ERROR, $source_status, 'The node type has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_error_in_upload', FALSE);
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextContains('Blogpost has been updated.');
  }

  /**
   * Test that we handle errors in update.
   */
  public function testCheckSourceStatusWithAnError() {
    \Drupal::state()->set('lingotek.must_error_in_check_source_status', TRUE);

    // Check that the translate tab is in the node type.
    $this->drupalGet('/admin/config/regional/config-translation');
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    // Upload the document, which must succeed.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextContains('Article uploaded successfully');

    // Check that the upload succeeded.
    $this->clickLink('Check upload status');

    // We failed at checking status, but we don't know what happened.
    // So we don't mark as error but keep it on importing.
    $this->assertSession()->pageTextContains('Article status check failed. Please try again.');

    $nodeType = NodeType::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEquals(Lingotek::STATUS_IMPORTING, $source_status);
  }

  /**
   * Test that we handle errors in update.
   */
  public function testCheckSourceStatusNotCompletedAndStillImporting() {
    // Check that the translate tab is in the node type.
    $this->drupalGet('/admin/config/regional/config-translation');
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    // Upload the document, which must succeed.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextContains('Article uploaded successfully');

    // The document has not been imported yet.
    \Drupal::state()->set('lingotek.document_status_completion', FALSE);

    // Check that the upload succeeded.
    $this->clickLink('Check upload status');

    $this->assertSession()->pageTextContains('The import for Article is still pending.');

    $nodeType = NodeType::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEquals(Lingotek::STATUS_IMPORTING, $source_status);
  }

  /**
   * Test that we handle errors in update.
   */
  public function testCheckSourceStatusCompletedAndContentMissing() {
    \Drupal::state()->set('lingotek.must_document_not_found_error_in_check_source_status', TRUE);

    // Check that the translate tab is in the node type.
    $this->drupalGet('/admin/config/regional/config-translation');
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    // Upload the document, which must succeed.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextContains('Article uploaded successfully');

    // Check that the upload succeeded.
    $this->clickLink('Check upload status');

    $this->assertSession()->pageTextContains('Document Article was not found. Please upload again.');

    $nodeType = NodeType::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEquals(Lingotek::STATUS_UNTRACKED, $source_status);
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUpdatingWithAnErrorViaAutomaticUpload() {
    // Create a content type.
    $edit = ['name' => 'Landing Page', 'type' => 'landing_page'];
    $this->drupalGet('admin/structure/types/add');
    $this->submitForm($edit, 'Save content type');

    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    // Edit the content type.
    $edit['name'] = 'Landing Page EDITED';
    $this->drupalGet('/admin/structure/types/manage/landing_page');
    $this->submitForm($edit, t('Save content type'));

    // The document was updated automatically and failed.
    $this->assertSession()->pageTextContains('The update for node_type Landing Page EDITED failed. Please try again.');

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('landing_page');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEquals(Lingotek::STATUS_ERROR, $source_status, 'The node type has been marked as error.');
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithADocumentArchivedError() {
    // Check that the translate tab is in the node type.
    $this->drupalGet('/admin/config/regional/config-translation');
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    // Upload the document, which must succeed.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextContains('Article uploaded successfully');

    // Check that the upload succeeded.
    $this->clickLink('Check upload status');
    $this->assertSession()->pageTextContains('Article status checked successfully');

    // Edit the content type.
    $edit['name'] = 'Blogpost';
    $this->drupalGet('/admin/structure/types/manage/article');
    $this->submitForm($edit, t('Save content type'));
    $this->assertSession()->pageTextContains('The content type Blogpost has been updated.');

    // Go back to the form.
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    \Drupal::state()->set('lingotek.must_document_archived_error_in_update', TRUE);

    // Re-upload. Must fail now.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextContains('Document Blogpost has been archived. Uploading again.');

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEquals(Lingotek::STATUS_IMPORTING, $source_status);

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_document_archived_error_in_update', FALSE);
    $this->clickLink('Check upload');
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextContains('Blogpost status checked successfully');
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithADocumentLockedError() {
    // Check that the translate tab is in the node type.
    $this->drupalGet('/admin/config/regional/config-translation');
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    // Upload the document, which must succeed.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextContains('Article uploaded successfully');

    // Check that the upload succeeded.
    $this->clickLink('Check upload status');
    $this->assertSession()->pageTextContains('Article status checked successfully');

    // Edit the content type.
    $edit['name'] = 'Blogpost';
    $this->drupalGet('/admin/structure/types/manage/article');
    $this->submitForm($edit, t('Save content type'));
    $this->assertSession()->pageTextContains('The content type Blogpost has been updated.');

    // Go back to the form.
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    \Drupal::state()->set('lingotek.must_document_locked_error_in_update', TRUE);

    // Re-upload. Must fail now.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextContains('Document node_type Blogpost has a new version. The document id has been updated for all future interactions. Please try again.');

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEquals(Lingotek::STATUS_EDITED, $source_status, 'The node type has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_document_locked_error_in_update', FALSE);
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextContains('Blogpost has been updated.');
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithADocumentLockedErrorViaAutomaticUpload() {
    // Check that the translate tab is in the node type.
    $this->drupalGet('/admin/config/regional/config-translation');
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    // Upload the document, which must succeed.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextContains('Article uploaded successfully');

    // Check that the upload succeeded.
    $this->clickLink('Check upload status');
    $this->assertSession()->pageTextContains('Article status checked successfully');

    \Drupal::state()->set('lingotek.must_document_locked_error_in_update', TRUE);

    // Edit the content type.
    $edit['name'] = 'Blogpost';
    $this->drupalGet('/admin/structure/types/manage/article');
    $this->submitForm($edit, t('Save content type'));
    $this->assertSession()->pageTextContains('The content type Blogpost has been updated.');

    $this->assertSession()->pageTextContains('Document node_type Blogpost has a new version. The document id has been updated for all future interactions. Please try again.');

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEquals(Lingotek::STATUS_EDITED, $source_status, 'The node type has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_document_locked_error_in_update', FALSE);
    $this->clickLink(t('Translate'));
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextContains('Blogpost has been updated.');
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithADocumentArchivedErrorViaAutomaticUpload() {
    // Check that the translate tab is in the node type.
    $this->drupalGet('/admin/config/regional/config-translation');
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    // Upload the document, which must succeed.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextContains('Article uploaded successfully');

    // Check that the upload succeeded.
    $this->clickLink('Check upload status');
    $this->assertSession()->pageTextContains('Article status checked successfully');

    \Drupal::state()->set('lingotek.must_document_archived_error_in_update', TRUE);

    // Edit the content type.
    $edit['name'] = 'Blogpost';
    $this->drupalGet('/admin/structure/types/manage/article');
    $this->submitForm($edit, t('Save content type'));
    $this->assertSession()->pageTextContains('The content type Blogpost has been updated.');

    $this->assertSession()->pageTextContains('Document node_type Blogpost has been archived. Uploading again.');

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('article');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEquals(Lingotek::STATUS_IMPORTING, $source_status);

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_document_archived_error_in_update', FALSE);
    $this->clickLink(t('Translate'));
    $this->clickLink('Check upload');
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextContains('Blogpost status checked successfully');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUploadingWithAnErrorViaAutomaticUpload() {
    $this->saveLingotekConfigTranslationSettings([
      'node_type' => 'automatic',
    ]);

    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    $this->drupalGet('admin/lingotek/settings');

    // Create a content type.
    $edit = ['name' => 'Landing Page', 'type' => 'landing_page'];
    $this->drupalGet('admin/structure/types/add');
    $this->submitForm($edit, 'Save content type');

    // The document was uploaded automatically and failed.
    $this->assertSession()->pageTextContains('The upload for node_type Landing Page failed. Please try again.');

    // The node type has been marked with the error status.
    $nodeType = NodeType::load('landing_page');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.config_translation');
    $source_status = $translation_service->getSourceStatus($nodeType);
    $this->assertEquals(Lingotek::STATUS_ERROR, $source_status, 'The node type has been marked as error.');
  }

  /**
   * Test trying translating a config entity which language doesn't exist.
   */
  public function testTranslatingFromUnexistingLocale() {
    // Create a node type.
    $this->drupalCreateContentType([
      'type' => 'aaa_test_content_type',
      'name' => 'AAA Test Content Type',
      'langcode' => 'nap',
    ]);
    $this->drupalGet('/admin/config/regional/config-translation');
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink('Translate');
    $this->assertSession()->pageTextContains('Translations for AAA Test Content Type content type');
    $this->assertSession()->pageTextContains('Unknown (nap) (original)');
  }

}
