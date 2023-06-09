<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Entity\LingotekContentMetadata;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;

/**
 * Tests translating a node with multiple locales including paragraphs.
 *
 * @group lingotek
 * @group legacy
 */
class LingotekNodeParagraphsTranslationTest extends LingotekTestBase {

  use ContentModerationTestTrait;

  protected $paragraphsTranslatable = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'content_moderation', 'workflows', 'node', 'image', 'comment', 'paragraphs', 'lingotek_paragraphs_test'];

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

    // Enable content moderation for articles.
    $workflow = $this->createEditorialWorkflow();
    $this->configureContentModeration('editorial', ['node' => ['paragraphed_content_demo']]);

    if ($this->paragraphsTranslatable) {
      $this->setParagraphFieldsTranslatability();
    }

    $this->saveLingotekContentTranslationSettings([
      'node' => [
        'paragraphed_content_demo' => [
          'profiles' => 'automatic',
          'fields' => [
            'title' => 1,
            'field_paragraphs_demo' => 1,
          ],
          'moderation' => [
            'upload_status' => 'published',
            'download_transition' => 'publish',
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
   * Tests that a node can be translated.
   */
  public function testNodeWithParagraphsTranslation() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Add paragraphed content.
    $this->drupalGet('node/add/paragraphed_content_demo');

    $this->submitForm(NULL, t('Add Image + Text'));

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_paragraphs_demo[0][subform][field_text_demo][0][value]'] = 'Llamas are very cool';
    $edit['moderation_state[0][state]'] = 'published';
    $this->submitForm($edit, t('Save'));

    $this->node = Node::load(1);

    // Check that only the configured fields have been uploaded, including metatags.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    dump(var_export($data, TRUE));
    $this->assertUploadedDataFieldCount($data, 2);
    $this->assertEquals($data['title'][0]['value'], 'Llamas are cool');
    $this->assertEquals($data['field_paragraphs_demo'][0]['field_text_demo'][0]['value'], 'Llamas are very cool');

    // Check that the url used was the right one.
    $uploaded_url = \Drupal::state()->get('lingotek.uploaded_url');
    $this->assertSame(\Drupal::request()->getUriForPath('/node/1'), $uploaded_url, 'The node url was used.');

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
    $link = $this->xpath('//a[normalize-space()="Request translation" and contains(@href,"es_AR")]');
    $link[0]->click();
    $this->assertSession()->pageTextContains("Locale 'es_AR' was added as a translation target for node Llamas are cool.");

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertSession()->pageTextContains('The es_AR translation for node Llamas are cool is ready for download.');

    // Check that the Edit link points to the workbench and it is opened in a new tab.
    $this->assertLingotekWorkbenchLink('es_AR', 'dummy-document-hash-id', 'Edit in Ray Enterprise Workbench');

    // Download translation.
    $this->clickLink('Download completed translation');
    $this->assertSession()->pageTextContains('The translation of node Llamas are cool into es_AR has been downloaded.');

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas');
    $this->assertSession()->pageTextContains('Las llamas son chulas');
    $this->assertSession()->pageTextContains('Las llamas son muy chulas');
  }

  /**
   * Tests that the metadata of the node and the embedded paragraphs is included.
   */
  public function testContentEntityMetadataIsIncluded() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Add paragraphed content.
    $this->drupalGet('node/add/paragraphed_content_demo');
    $this->submitForm(NULL, t('Add Image + Text'));
    $this->submitForm(NULL, t('Add Image + Text'));

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_paragraphs_demo[0][subform][field_text_demo][0][value]'] = 'Llamas are very cool for the first time';
    $edit['field_paragraphs_demo[1][subform][field_text_demo][0][value]'] = 'Llamas are very cool for the second time';

    $edit['moderation_state[0][state]'] = 'published';
    $this->submitForm($edit, t('Save'));

    $this->node = Node::load(1);

    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');

    $serialized_node = $translation_service->getSourceData($this->node);
    dump(var_export($serialized_node, TRUE));
    // Main node metadata is there.
    $this->assertTrue(isset($serialized_node['_lingotek_metadata']), 'The Lingotek metadata is included in the extracted data.');
    $this->assertEquals('node', $serialized_node['_lingotek_metadata']['_entity_type_id'], 'Entity type id is included as metadata.');
    $this->assertEquals(1, $serialized_node['_lingotek_metadata']['_entity_id'], 'Entity id is included as metadata.');
    $this->assertEquals(1, $serialized_node['_lingotek_metadata']['_entity_revision'], 'Entity revision id is included as metadata.');
  }

  /**
   * Paragraphs don't have a title, so we should disallow filtering by it.
   */
  public function testBulkManagementParagraphsDontAllowFilteringByLabel() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Add paragraphed content.
    $this->drupalGet('node/add/paragraphed_content_demo');
    $this->submitForm(NULL, t('Add Image + Text'));
    $this->submitForm(NULL, t('Add Image + Text'));

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_paragraphs_demo[0][subform][field_text_demo][0][value]'] = 'Llamas are very cool for the first time';
    $edit['field_paragraphs_demo[1][subform][field_text_demo][0][value]'] = 'Llamas are very cool for the second time';

    $edit['moderation_state[0][state]'] = 'published';
    $this->submitForm($edit, t('Save'));

    $this->goToContentBulkManagementForm('paragraph');
    $this->assertSession()->fieldNotExists('filters[wrapper][label]', 'There is no filter by label as paragraphs have no label.');
  }

  /**
   * Paragraphs don't have a title, so we ignore a label filter if it exists.
   */
  public function testBulkManagementParagraphsIgnoreFilterByLabel() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Add paragraphed content.
    $this->drupalGet('node/add/paragraphed_content_demo');
    $this->submitForm(NULL, t('Add Image + Text'));
    $this->submitForm(NULL, t('Add Image + Text'));

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_paragraphs_demo[0][subform][field_text_demo][0][value]'] = 'Llamas are very cool for the first time';
    $edit['field_paragraphs_demo[1][subform][field_text_demo][0][value]'] = 'Llamas are very cool for the second time';

    $edit['moderation_state[0][state]'] = 'published';
    $this->submitForm($edit, t('Save'));
    $this->drupalGet('admin/lingotek/settings', []);

    // Ensure paragraphs tab is enabled.
    $this->submitForm(['contrib[paragraphs][enable_bulk_management]' => 1], 'Save settings', 'lingoteksettings-integrations-form');

    $this->goToContentBulkManagementForm('paragraph');
    // Assert there is at least one paragraph in the list.
    $this->assertSession()->pageTextContains('Image + Text');

    // Set a filter, and there should still be paragraphs.
    /** @var \Drupal\user\PrivateTempStore $tempStore */
    $tempStore = \Drupal::service('tempstore.private')->get('lingotek.management.filter.paragraph');
    $tempStore->set('label', 'Llamas');

    $this->goToContentBulkManagementForm('paragraph');
    $this->assertSession()->pageTextContains('Image + Text');
  }

  public function testParagraphEditsAreKeptWhenTranslating() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+paragraphs_multiple');

    // Add paragraphed content.
    $this->drupalGet('node/add/paragraphed_content_demo');

    $this->submitForm(NULL, t('Add Image + Text'));
    $this->submitForm(NULL, t('Add Image + Text'));

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_paragraphs_demo[0][subform][field_text_demo][0][value]'] = 'Llamas are very cool for the first time';
    $edit['field_paragraphs_demo[1][subform][field_text_demo][0][value]'] = 'Llamas are very cool for the second time';
    $edit['moderation_state[0][state]'] = 'published';
    $this->submitForm($edit, t('Save'));

    $this->node = Node::load(1);

    // Check that only the configured fields have been uploaded, including metatags.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    dump(var_export($data, TRUE));
    $this->assertUploadedDataFieldCount($data, 2);
    $this->assertEquals($data['title'][0]['value'], 'Llamas are cool');
    $this->assertEquals($data['field_paragraphs_demo'][0]['field_text_demo'][0]['value'], 'Llamas are very cool for the first time');
    $this->assertEquals($data['field_paragraphs_demo'][1]['field_text_demo'][0]['value'], 'Llamas are very cool for the second time');

    // Check that the url used was the right one.
    $uploaded_url = \Drupal::state()->get('lingotek.uploaded_url');
    $this->assertSame(\Drupal::request()->getUriForPath('/node/1'), $uploaded_url, 'The node url was used.');

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
    $link = $this->xpath('//a[normalize-space()="Request translation" and contains(@href,"es_AR")]');
    $link[0]->click();
    $this->assertSession()->pageTextContains("Locale 'es_AR' was added as a translation target for node Llamas are cool.");

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertSession()->pageTextContains('The es_AR translation for node Llamas are cool is ready for download.');

    // Check that the Edit link points to the workbench and it is opened in a new tab.
    $this->assertLingotekWorkbenchLink('es_AR', 'dummy-document-hash-id', 'Edit in Ray Enterprise Workbench');

    // Edit the original node.
    $this->drupalGet('node/1');
    $this->clickLink('Edit');

    $edit = [];
    $edit['title[0][value]'] = 'Dogs are cool';
    $edit['field_paragraphs_demo[0][subform][field_text_demo][0][value]'] = 'Dogs are very cool for the first time';
    $edit['field_paragraphs_demo[1][subform][field_text_demo][0][value]'] = 'Dogs are very cool for the second time';

    $this->saveAndKeepPublishedNodeForm($edit, 1, FALSE);

    $this->assertSession()->pageTextContains('Paragraphed article Dogs are cool has been updated.');
    $this->assertSession()->pageTextContains('Dogs are very cool for the first time');
    $this->assertSession()->pageTextContains('Dogs are very cool for the second time');

    // Go back to translations.
    $this->clickLink('Translate');

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertSession()->pageTextContains('The es_AR translation for node Dogs are cool is ready for download.');

    // Download translation.
    $this->clickLink('Download completed translation');
    $this->assertSession()->pageTextContains('The translation of node Dogs are cool into es_AR has been downloaded.');

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas');
    $this->assertSession()->pageTextContains('Las llamas son chulas');
    $this->assertSession()->pageTextContains('Las llamas son chulas por primera vez');
    $this->assertSession()->pageTextContains('Las llamas son chulas por segunda vez');

    // The saved revision is kept.
    $this->clickLink('Translate');
    $this->clickLink('Dogs are cool');
    $this->assertSession()->pageTextContains('Dogs are very cool for the first time');
    $this->assertSession()->pageTextContains('Dogs are very cool for the second time');
  }

  public function testParagraphRevisionsAreKeptWhenTranslating() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+paragraphs_multiple');

    // Add paragraphed content.
    $this->drupalGet('node/add/paragraphed_content_demo');

    $this->submitForm(NULL, t('Add Image + Text'));
    $this->submitForm(NULL, t('Add Image + Text'));

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_paragraphs_demo[0][subform][field_text_demo][0][value]'] = 'Llamas are very cool for the first time';
    $edit['field_paragraphs_demo[1][subform][field_text_demo][0][value]'] = 'Llamas are very cool for the second time';
    $edit['moderation_state[0][state]'] = 'published';
    $this->submitForm($edit, t('Save'));

    $this->node = Node::load(1);

    // Check that only the configured fields have been uploaded, including metatags.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    dump(var_export($data, TRUE));
    $this->assertUploadedDataFieldCount($data, 2);
    $this->assertEquals($data['title'][0]['value'], 'Llamas are cool');
    $this->assertEquals($data['field_paragraphs_demo'][0]['field_text_demo'][0]['value'], 'Llamas are very cool for the first time');
    $this->assertEquals($data['field_paragraphs_demo'][1]['field_text_demo'][0]['value'], 'Llamas are very cool for the second time');

    // Check that the url used was the right one.
    $uploaded_url = \Drupal::state()->get('lingotek.uploaded_url');
    $this->assertSame(\Drupal::request()->getUriForPath('/node/1'), $uploaded_url, 'The node url was used.');

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
    $link = $this->xpath('//a[normalize-space()="Request translation" and contains(@href,"es_AR")]');
    $link[0]->click();
    $this->assertSession()->pageTextContains("Locale 'es_AR' was added as a translation target for node Llamas are cool.");

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertSession()->pageTextContains('The es_AR translation for node Llamas are cool is ready for download.');

    // Check that the Edit link points to the workbench and it is opened in a new tab.
    $this->assertLingotekWorkbenchLink('es_AR', 'dummy-document-hash-id', 'Edit in Ray Enterprise Workbench');

    // Edit the original node.
    $this->drupalGet('node/1');
    $this->clickLink('Edit');

    $edit = [];
    $edit['title[0][value]'] = 'Dogs are cool';
    $edit['field_paragraphs_demo[0][subform][field_text_demo][0][value]'] = 'Dogs are very cool for the first time';
    $edit['field_paragraphs_demo[1][subform][field_text_demo][0][value]'] = 'Dogs are very cool for the second time';
    $edit['revision'] = 1;
    $this->saveAndUnpublishNodeForm($edit, 1, FALSE);

    $this->assertSession()->pageTextContains('Paragraphed article Dogs are cool has been updated.');
    $this->assertSession()->pageTextContains('Dogs are very cool for the first time');
    $this->assertSession()->pageTextContains('Dogs are very cool for the second time');

    // Go back to translations.
    $this->clickLink('Translate');

    // Re-upload, as drafts are not re-uploaded automatically.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextContains('Llamas are cool was updated and sent to Lingotek successfully.');

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertSession()->pageTextContains('The es_AR translation for node Llamas are cool is ready for download.');

    // Download translation.
    $this->clickLink('Download completed translation');
    $this->assertSession()->pageTextContains('The translation of node Llamas are cool into es_AR has been downloaded.');

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas');
    $this->assertSession()->pageTextContains('Las llamas son chulas');
    $this->assertSession()->pageTextContains('Las llamas son chulas por primera vez');
    $this->assertSession()->pageTextContains('Las llamas son chulas por segunda vez');

    // The published revision is the one visible.
    $this->clickLink('Translate');
    $this->clickLink('Dogs are cool');
    $this->assertSession()->pageTextContains('Llamas are cool');
    $this->assertSession()->pageTextContains('Llamas are very cool for the first time');
    $this->assertSession()->pageTextContains('Llamas are very cool for the second time');

    // The pending revision is not updated.
    $this->drupalGet('node/1/latest');
    $this->assertSession()->pageTextContains('Dogs are very cool for the first time');
    $this->assertSession()->pageTextContains('Dogs are very cool for the second time');
  }

  /**
   * Tests that when we remove a paragraph from a translated source node, when
   * reuploading and translating the target doesn't contain the paragraph.
   */
  public function testParagraphIsRemovedIfTranslationIsRemoved() {
    $this->testNodeWithParagraphsTranslation();

    $this->drupalGet('node/1/edit');
    $this->submitForm([], 'Remove');
    $this->submitForm([], 'Confirm removal');
    $this->submitForm([], 'Save (this translation)');

    // Check that only the configured fields have been uploaded, including metatags.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertTrue(isset($data['field_paragraphs_demo']));
    $this->assertEmpty($data['field_paragraphs_demo']);

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+paragraphs_removed');

    $this->clickLink('Translate');

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertSession()->pageTextContains('The es_AR translation for node Llamas are cool is ready for download.');

    // Download translation.
    $this->clickLink('Download completed translation');
    $this->assertSession()->pageTextContains('The translation of node Llamas are cool into es_AR has been downloaded.');

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas');
    $this->assertSession()->pageTextContains('Las llamas son chulas');
    $this->assertSession()->pageTextNotContains('Las llamas son muy chulas');
  }

  /**
   * Tests that when we entity_reference_revisions perform a delete on a paragraph
   * for syncing with its parent, the lingotek_entity_translation_delete() hook
   * doesn't check statuses for a document without document id.
   */
  public function testParagraphIsNotCheckedIfTranslationIsRemoved() {
    ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_DE')->save();

    $this->testNodeWithParagraphsTranslation();

    /** @var \Drupal\paragraphs\ParagraphInterface $paragraph */
    $node = Node::load(1);
    $node->save();

    $paragraph = Paragraph::load(1);
    $paragraph->addTranslation('de');
    $paragraph->save();

    $this->drupalGet('node/1/edit');
    $this->submitForm([], 'Remove');
    $this->submitForm([], 'Confirm removal');
    $this->submitForm([], 'Save');

    // The content is edited successfully.
    $this->assertSession()->pageTextContains('Llamas are cool');
  }

  /**
   * Paragraphs don't have a title, so we should disallow filtering by it.
   */
  public function testParagraphIsRemovedFromTranslationIfSourceIsRemoved() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+paragraphs_multiple_before_removal');

    // Add paragraphed content.
    $this->drupalGet('node/add/paragraphed_content_demo');
    $this->submitForm(NULL, t('Add Image + Text'));
    $this->submitForm(NULL, t('Add Image + Text'));
    $this->submitForm(NULL, t('Add Image + Text'));

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_paragraphs_demo[0][subform][field_text_demo][0][value]'] = 'Llamas are very cool for the first time';
    $edit['field_paragraphs_demo[1][subform][field_text_demo][0][value]'] = 'Llamas are very cool for the second time';
    $edit['field_paragraphs_demo[2][subform][field_text_demo][0][value]'] = 'Llamas are very cool for the third time';
    $edit['moderation_state[0][state]'] = 'published';
    $this->submitForm($edit, t('Save'));

    // Check that only the configured fields have been uploaded, including metatags.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    \Drupal::messenger()->addStatus(var_export($data, TRUE));
    $this->assertUploadedDataFieldCount($data, 2);
    $this->assertEquals($data['title'][0]['value'], 'Llamas are cool');
    $this->assertEquals($data['field_paragraphs_demo'][0]['field_text_demo'][0]['value'], 'Llamas are very cool for the first time');
    $this->assertEquals($data['field_paragraphs_demo'][1]['field_text_demo'][0]['value'], 'Llamas are very cool for the second time');
    $this->assertEquals($data['field_paragraphs_demo'][2]['field_text_demo'][0]['value'], 'Llamas are very cool for the third time');

    // Check that the url used was the right one.
    $uploaded_url = \Drupal::state()->get('lingotek.uploaded_url');
    $this->assertSame(\Drupal::request()->getUriForPath('/node/1'), $uploaded_url, 'The node url was used.');

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
    $link = $this->xpath('//a[normalize-space()="Request translation" and contains(@href,"es_AR")]');
    $link[0]->click();
    $this->assertSession()->pageTextContains("Locale 'es_AR' was added as a translation target for node Llamas are cool.");

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertSession()->pageTextContains('The es_AR translation for node Llamas are cool is ready for download.');

    // Check that the Edit link points to the workbench and it is opened in a new tab.
    $this->assertLingotekWorkbenchLink('es_AR', 'dummy-document-hash-id', 'Edit in Ray Enterprise Workbench');

    // Download translation.
    $this->clickLink('Download completed translation');
    $this->assertSession()->pageTextContains('The translation of node Llamas are cool into es_AR has been downloaded.');

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas');
    $this->assertSession()->pageTextContains('Las llamas son chulas');
    $this->assertSession()->pageTextContains('Las llamas son muy chulas por primera vez');
    $this->assertSession()->pageTextContains('Las llamas son muy chulas por segunda vez');
    $this->assertSession()->pageTextContains('Las llamas son muy chulas por tercera vez');

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+paragraphs_multiple_after_removal');

    $this->drupalGet('node/1/edit');

    $this->submitForm(NULL, 'field_paragraphs_demo_1_remove');
    $this->submitForm(NULL, 'field_paragraphs_demo_1_confirm_remove');

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool EDITED';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_paragraphs_demo[0][subform][field_text_demo][0][value]'] = 'Llamas are very cool for the first time EDITED';
    $edit['field_paragraphs_demo[2][subform][field_text_demo][0][value]'] = 'Llamas are very cool for the third time EDITED';
    $edit['revision'] = TRUE;
    $this->submitForm($edit, t('Save (this translation)'));

    $this->assertSession()->pageTextContains('Llamas are cool EDITED');
    $this->assertSession()->pageTextContains('Llamas are very cool for the first time EDITED');
    $this->assertSession()->pageTextNotContains('Llamas are very cool for the second time EDITED');
    $this->assertSession()->pageTextContains('Llamas are very cool for the third time EDITED');

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // The document should have been automatically updated, so let's check
    // the upload status.
    $this->clickLink('Check Upload Status');
    $this->assertSession()->pageTextContains('The import for node Llamas are cool EDITED is complete.');

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertSession()->pageTextContains('The es_AR translation for node Llamas are cool EDITED is ready for download.');

    // Download translation.
    $this->clickLink('Download completed translation');
    $this->assertSession()->pageTextContains('The translation of node Llamas are cool EDITED into es_AR has been downloaded.');

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas EDITADO');
    $this->assertSession()->pageTextContains('Las llamas son chulas EDITADO');
    $this->assertSession()->pageTextContains('Las llamas son muy chulas por primera vez EDITADO');
    $this->assertSession()->pageTextNotContains('Las llamas son muy chulas por segunda vez EDITADO');
    $this->assertSession()->pageTextContains('Las llamas son muy chulas por tercera vez EDITADO');

    $paragraphs = $this->xpath('//div[contains(@class, "paragraph")]');
    $this->assertCount(2, $paragraphs);
  }

  /**
   * Tests that metadata is created when a paragraph is added.
   */
  public function testParagraphContentMetadataIsSavedWhenContentAdded() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Add paragraphed content.
    $this->drupalGet('node/add/paragraphed_content_demo');

    $this->submitForm(NULL, t('Add Image + Text'));

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_paragraphs_demo[0][subform][field_text_demo][0][value]'] = 'Llamas are very cool';
    $edit['moderation_state[0][state]'] = 'published';
    $this->submitForm($edit, t('Save'));

    $metadata = LingotekContentMetadata::loadMultiple();
    $this->assertEquals(2, count($metadata), 'There is metadata saved for the parent entity and the child entity.');
  }

  /**
   * Tests that orphan paragraph references don't break the upload or download.
   */
  public function testMissingParagraphDoesntBreakUploadOrDownload() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Add paragraphed content.
    $this->drupalGet('node/add/paragraphed_content_demo');

    $this->submitForm(NULL, t('Add Image + Text'));

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_paragraphs_demo[0][subform][field_text_demo][0][value]'] = 'Llamas are very cool';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $edit['moderation_state[0][state]'] = 'published';
    $this->submitForm($edit, t('Save'));

    Paragraph::load(1)->delete();

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    $this->clickLink('Upload');
    $this->checkForMetaRefresh();

    // Check that only the configured fields have been uploaded,
    // but not the missing one.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    dump(var_export($data, TRUE));
    $this->assertUploadedDataFieldCount($data, 1);
    $this->assertEquals($data['title'][0]['value'], 'Llamas are cool');

    // Check that the url used was the right one.
    $uploaded_url = \Drupal::state()->get('lingotek.uploaded_url');
    $this->assertSame(\Drupal::request()->getUriForPath('/node/1'), $uploaded_url, 'The node url was used.');

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertSame('manual', $used_profile, 'The manual profile was used.');

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Check Upload Status');
    $this->assertSession()->pageTextContains('The import for node Llamas are cool is complete.');

    // Request translation.
    $link = $this->xpath('//a[normalize-space()="Request translation" and contains(@href,"es_AR")]');
    $link[0]->click();
    $this->assertSession()->pageTextContains("Locale 'es_AR' was added as a translation target for node Llamas are cool.");

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertSession()->pageTextContains('The es_AR translation for node Llamas are cool is ready for download.');

    // Check that the Edit link points to the workbench and it is opened in a new tab.
    $this->assertLingotekWorkbenchLink('es_AR', 'dummy-document-hash-id', 'Edit in Ray Enterprise Workbench');

    // Download translation.
    $this->clickLink('Download completed translation');
    $this->assertSession()->pageTextContains('The translation of node Llamas are cool into es_AR has been downloaded.');

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas');
    $this->assertSession()->pageTextContains('Las llamas son chulas');
    $this->assertSession()->pageTextNotContains('Las llamas son muy chulas');
  }

  /**
   * Tests that paragraph references aren't removed on download.
   */
  public function testParagraphedNodeDownloadDoesntChangeReferencesOnSource() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+paragraphs_multiple');

    // Add paragraphed content.
    $this->drupalGet('node/add/paragraphed_content_demo');

    $this->submitForm(NULL, t('Add Image + Text'));
    $this->submitForm(NULL, t('Add Image + Text'));

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_paragraphs_demo[0][subform][field_text_demo][0][value]'] = 'Llamas are cool for the first time';
    $edit['field_paragraphs_demo[1][subform][field_text_demo][0][value]'] = 'Llamas are cool for the second time';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $edit['moderation_state[0][state]'] = 'published';
    $this->submitForm($edit, t('Save'));

    $this->goToContentBulkManagementForm();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->submitForm($edit, $this->getApplyActionsButtonLabel());

    // Check that only the configured fields have been uploaded,
    // but not the missing one.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    dump(var_export($data, TRUE));
    $this->assertUploadedDataFieldCount($data, 2);
    $this->assertEquals($data['title'][0]['value'], 'Llamas are cool');
    $this->assertEquals($data['field_paragraphs_demo'][0]['field_text_demo'][0]['value'], 'Llamas are cool for the first time');
    $this->assertEquals($data['field_paragraphs_demo'][1]['field_text_demo'][0]['value'], 'Llamas are cool for the second time');

    // Check that the url used was the right one.
    $uploaded_url = \Drupal::state()->get('lingotek.uploaded_url');
    $this->assertSame(\Drupal::request()->getUriForPath('/node/1'), $uploaded_url, 'The node url was used.');

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertSame('manual', $used_profile, 'The manual profile was used.');

    // Request translation.
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslation('es-ar', 'node'),
    ];
    $this->submitForm($edit, $this->getApplyActionsButtonLabel());

    $this->drupalGet('node/1');
    $this->clickLink('Edit');
    $this->submitForm(NULL, t('Remove'));
    $this->submitForm(NULL, t('Add Image + Text'));

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are very cool';
    $edit['field_paragraphs_demo[1][subform][field_text_demo][0][value]'] = 'Llamas are very cool for the second time';
    $edit['field_paragraphs_demo[2][subform][field_text_demo][0][value]'] = 'Llamas are very cool for the third time';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndKeepPublishedNodeForm($edit, 1, FALSE);

    // Download translation.
    $this->goToContentBulkManagementForm();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => 'download_translation:es-ar',
    ];
    $this->submitForm($edit, $this->getApplyActionsButtonLabel());
    $this->assertSame('es_AR', \Drupal::state()->get('lingotek.downloaded_locale'));

    $this->drupalGet('node/1/translations');

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas');
    $this->assertSession()->pageTextContains('Las llamas son chulas');
    if ($this->paragraphsTranslatable) {
      $this->assertSession()->pageTextContains('Las llamas son chulas por primera vez');
      $this->assertSession()->pageTextContains('Las llamas son chulas por segunda vez');
      $this->assertSession()->pageTextNotContains('Las llamas son chulas por tercera vez');
      $this->assertSession()->pageTextNotContains('Llamas are very cool for the third time');
    }
    else {
      $this->assertSession()->pageTextNotContains('Las llamas son chulas por primera vez');
      $this->assertSession()->pageTextContains('Las llamas son chulas por segunda vez');
      $this->assertSession()->pageTextContains('Llamas are very cool for the third time');
    }

    $this->clickLink('Translate');
    $this->clickLink('Llamas are very cool');

    $this->assertSession()->pageTextContains('Llamas are very cool');
    $this->assertSession()->pageTextNotContains('Llamas are very cool for the first time');
    $this->assertSession()->pageTextContains('Llamas are very cool for the second time');
    $this->assertSession()->pageTextContains('Llamas are very cool for the third time');
  }

  public function testEditingAfterNodeWithParagraphsTranslation() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->testNodeWithParagraphsTranslation();

    $this->drupalGet('es-ar/node/1/edit');
    $assert_session->fieldValueEquals('field_paragraphs_demo[0][subform][field_text_demo][0][value]', 'Las llamas son muy chulas');

    $this->drupalGet('node/1/edit');
    $assert_session->fieldValueEquals('field_paragraphs_demo[0][subform][field_text_demo][0][value]', 'Llamas are very cool');

    $this->submitForm(NULL, t('Remove'));
    $this->submitForm(NULL, t('Confirm removal'));

    $page->pressButton('Save (this translation)');
    $assert_session->pageTextContains('Llamas are cool has been updated.');
  }

  public function testEditingAfterNodeWithParagraphsTranslationWithExistingParagraphTranslation() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Add paragraphed content.
    $this->drupalGet('node/add/paragraphed_content_demo');

    $this->submitForm(NULL, t('Add Image + Text'));

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_paragraphs_demo[0][subform][field_text_demo][0][value]'] = 'Llamas are very cool';
    $edit['moderation_state[0][state]'] = 'published';
    $this->submitForm($edit, t('Save'));

    $this->node = Node::load(1);

    // Check that only the configured fields have been uploaded, including metatags.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    dump(var_export($data, TRUE));
    $this->assertUploadedDataFieldCount($data, 2);
    $this->assertEquals($data['title'][0]['value'], 'Llamas are cool');
    $this->assertEquals($data['field_paragraphs_demo'][0]['field_text_demo'][0]['value'], 'Llamas are very cool');

    // Create a translation.
    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
    $paragraph = Paragraph::load(1);
    $paragraphTranslation = $paragraph->addTranslation('es-ar', $paragraph->toArray());
    $paragraphTranslation->save();

    // Check that the url used was the right one.
    $uploaded_url = \Drupal::state()->get('lingotek.uploaded_url');
    $this->assertSame(\Drupal::request()->getUriForPath('/node/1'), $uploaded_url, 'The node url was used.');

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
    $link = $this->xpath('//a[normalize-space()="Request translation" and contains(@href,"es_AR")]');
    $link[0]->click();
    $this->assertSession()->pageTextContains("Locale 'es_AR' was added as a translation target for node Llamas are cool.");

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertSession()->pageTextContains('The es_AR translation for node Llamas are cool is ready for download.');

    // Check that the Edit link points to the workbench and it is opened in a new tab.
    $this->assertLingotekWorkbenchLink('es_AR', 'dummy-document-hash-id', 'Edit in Ray Enterprise Workbench');

    // Download translation.
    $this->clickLink('Download completed translation');
    $this->assertSession()->pageTextContains('The translation of node Llamas are cool into es_AR has been downloaded.');

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas');
    $this->assertSession()->pageTextContains('Las llamas son chulas');
    $this->assertSession()->pageTextContains('Las llamas son muy chulas');

    $this->drupalGet('es-ar/node/1/edit');
    $assert_session->fieldValueEquals('field_paragraphs_demo[0][subform][field_text_demo][0][value]', 'Las llamas son muy chulas');

    $this->drupalGet('node/1/edit');
    $assert_session->fieldValueEquals('field_paragraphs_demo[0][subform][field_text_demo][0][value]', 'Llamas are very cool');

    $this->submitForm(NULL, t('Remove'));
    $this->submitForm(NULL, t('Confirm removal'));

    $page->pressButton('Save (this translation)');
    $assert_session->pageTextContains('Llamas are cool has been updated.');
  }

  public function testTranslationsKeptInLastRevisionWhenDownloadingAll() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Add an additional language.
    ConfigurableLanguage::createFromLangcode('it')->save();

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+paragraphs_multiple');

    // Add paragraphed content.
    $this->drupalGet('node/add/paragraphed_content_demo');

    $this->submitForm(NULL, t('Add Image + Text'));
    $this->submitForm(NULL, t('Add Image + Text'));

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_paragraphs_demo[0][subform][field_text_demo][0][value]'] = 'Llamas are cool for the first time';
    $edit['field_paragraphs_demo[1][subform][field_text_demo][0][value]'] = 'Llamas are cool for the second time';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $edit['moderation_state[0][state]'] = 'published';
    $this->submitForm($edit, t('Save'));

    $this->goToContentBulkManagementForm();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->submitForm($edit, $this->getApplyActionsButtonLabel());

    // Check that only the configured fields have been uploaded,
    // but not the missing one.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    dump(var_export($data, TRUE));
    $this->assertUploadedDataFieldCount($data, 2);
    $this->assertEquals($data['title'][0]['value'], 'Llamas are cool');
    $this->assertEquals($data['field_paragraphs_demo'][0]['field_text_demo'][0]['value'], 'Llamas are cool for the first time');
    $this->assertEquals($data['field_paragraphs_demo'][1]['field_text_demo'][0]['value'], 'Llamas are cool for the second time');

    // Check that the url used was the right one.
    $uploaded_url = \Drupal::state()->get('lingotek.uploaded_url');
    $this->assertSame(\Drupal::request()->getUriForPath('/node/1'), $uploaded_url, 'The node url was used.');

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertSame('manual', $used_profile, 'The manual profile was used.');

    $this->drupalGet('node/1');
    $this->clickLink('Edit');

    $edit = [];
    $edit['title[0][value]'] = 'Dogs are cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_paragraphs_demo[0][subform][field_text_demo][0][value]'] = 'Dogs are cool for the first time';
    $edit['field_paragraphs_demo[1][subform][field_text_demo][0][value]'] = 'Dogs are cool for the second time';
    $edit['moderation_state[0][state]'] = 'published';
    $this->saveAndKeepPublishedNodeForm($edit, 1);

    $this->goToContentBulkManagementForm();

    // Request all translations.
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslations('es-ar'),
    ];
    $this->submitForm($edit, $this->getApplyActionsButtonLabel());

    // Download all translations.
    $this->goToContentBulkManagementForm();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForDownloadTranslations('node'),
    ];
    $this->submitForm($edit, $this->getApplyActionsButtonLabel());

    $this->drupalGet('node/1/translations');

    // The content is translated and published in all languages.
    $this->assertSession()->linkExists('I lama sono belle');
    $this->assertSession()->linkExists('Las llamas son chulas es-ES');
    $this->assertSession()->pageTextContains('Las llamas son chulas');
    $this->assertSession()->linkExists('Dogs are cool');

    $this->clickLink('I lama sono belle');

    $this->assertSession()->pageTextContains('I lama sono belle');
    $this->assertSession()->pageTextContains('I lama sono belle la prima volta');
    $this->assertSession()->pageTextContains('I lama sono belle la seconda volta');

    $this->drupalGet('node/1/translations');
    $this->clickLink('Las llamas son chulas es-ES');

    $this->assertSession()->pageTextContains('Las llamas son chulas es-ES');
    $this->assertSession()->pageTextContains('Las llamas son chulas por primera vez es-ES');
    $this->assertSession()->pageTextContains('Las llamas son chulas por segunda vez es-ES');

    $this->drupalGet('node/1/translations');
    $this->clickLink('Dogs are cool');

    $this->assertSession()->pageTextContains('Dogs are cool');
    $this->assertSession()->pageTextNotContains('Llamas are cool for the first time');
    $this->assertSession()->pageTextNotContains('Llamas are cool for the second time');
    $this->assertSession()->pageTextContains('Dogs are cool for the first time');
    $this->assertSession()->pageTextContains('Dogs are cool for the second time');
  }

  /**
   * Tests that a paragraph is published when its parent node has been published.
   */
  public function testStatusChangeNodeWithParagraphsTranslation() {
    $this->saveLingotekContentTranslationSettings([
      'node' => [
        'paragraphed_content_demo' => [
          'profiles' => 'automatic',
          'fields' => [
            'title' => 1,
            'field_paragraphs_demo' => 1,
          ],
          'moderation' => [
            'upload_status' => 'published',
            'download_transition' => 'create_new_draft',
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
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Set the translation download status as "unpublished".
    $assert_session = $this->assertSession();
    $this->drupalGet('admin/lingotek/settings');
    $edit = ['target_download_status' => 'unpublished'];
    $this->submitForm($edit, 'Save', 'lingoteksettings-tab-preferences-form');

    // Assert the settings are saved successfully.
    $assert_session->fieldValueEquals('edit-target-download-status', 'unpublished');

    // Add paragraphed content.
    $this->drupalGet('node/add/paragraphed_content_demo');
    $this->submitForm(NULL, t('Add Image + Text'));
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_paragraphs_demo[0][subform][field_text_demo][0][value]'] = 'Llamas are very cool';
    $edit['moderation_state[0][state]'] = 'published';
    $this->submitForm($edit, t('Save'));
    $this->node = Node::load(1);

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    dump(var_export($data, TRUE));
    $this->assertUploadedDataFieldCount($data, 2);
    $this->assertEquals($data['title'][0]['value'], 'Llamas are cool');
    $this->assertEquals($data['field_paragraphs_demo'][0]['field_text_demo'][0]['value'], 'Llamas are very cool');

    // Check that the url used was the right one.
    $uploaded_url = \Drupal::state()->get('lingotek.uploaded_url');
    $this->assertSame(\Drupal::request()->getUriForPath('/node/1'), $uploaded_url, 'The node url was used.');

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertSame('automatic', $used_profile, 'The automatic profile was used.');

    // Check that the content is published.
    $this->drupalGet('node/1');
    $this->clickLink('Edit');
    $publish_checkbox = $this->xpath('//option[@value="published" and @selected="selected"]');
    $this->assertEquals(1, count($publish_checkbox));

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Translate');
    $this->clickLink('Check Upload Status');
    $this->assertSession()->pageTextContains('The import for node Llamas are cool is complete.');

    // Request translation.
    $link = $this->xpath('//a[normalize-space()="Request translation" and contains(@href,"es_AR")]');
    $link[0]->click();
    $this->assertSession()->pageTextContains("Locale 'es_AR' was added as a translation target for node Llamas are cool.");

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertSession()->pageTextContains('The es_AR translation for node Llamas are cool is ready for download.');

    // Check that the Edit link points to the workbench and it is opened in a new tab.
    $this->assertLingotekWorkbenchLink('es_AR', 'dummy-document-hash-id', 'Edit in Ray Enterprise Workbench');

    // Download completed translation.
    $this->clickLink('Translate');
    $this->clickLink('Download completed translation');
    $this->assertSession()->pageTextContains('The translation of node Llamas are cool into es_AR has been downloaded.');

    // The source should stay published, but the new translation is not.
    $this->assertSession()->pageTextContains('Not published');
    $this->assertSession()->pageTextContains('Published');

    // The last revision for the translation has the changed texts.
    $this->clickLink('Las llamas son chulas');
    $this->clickLink('Latest version');

    $this->assertSession()->pageTextContains('Las llamas son chulas');
    $this->assertSession()->pageTextContains('Las llamas son muy chulas');

    // We check this is a draft and publish the translation.
    $this->clickLink('Edit');
    $draft_option = $this->xpath('//option[@value="draft" and @selected="selected"]');
    $this->assertEquals(1, count($draft_option));

    $edit = ['moderation_state[0][state]' => 'published'];
    $this->submitForm($edit, 'Save (this translation)');

    $this->clickLink('Edit');
    $publish_option = $this->xpath('//option[@value="published" and @selected="selected"]');
    $this->assertEquals(1, count($publish_option));

    // Check no paragraph is unpublished after publishing the content.
    $this->clickLink('View');
    $unpublished_article = $this->xpath("//*[contains(@class, 'paragraph--unpublished')]");
    $this->assertEquals(0, count($unpublished_article));
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
