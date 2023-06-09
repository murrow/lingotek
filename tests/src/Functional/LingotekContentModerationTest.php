<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Lingotek;
use Drupal\workflows\Entity\Workflow;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;

/**
 * Tests setting up the integration with content moderation.
 *
 * @group lingotek
 */
class LingotekContentModerationTest extends LingotekTestBase {

  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'node', 'content_moderation'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('page_title_block', ['region' => 'content', 'weight' => -5]);
    $this->drupalPlaceBlock('local_tasks_block', ['region' => 'content', 'weight' => -10]);

    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Page']);

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

    ContentLanguageSettings::loadByEntityTypeBundle('node', 'page')
      ->setLanguageAlterable(TRUE)
      ->save();
    \Drupal::service('content_translation.manager')
      ->setEnabled('node', 'page', TRUE);

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    // Enable content moderation.
    $workflow = $this->createEditorialWorkflow();
    $this->enableModerationThroughUI('article');
    $this->addReviewStateToEditorialWorkflow();

    $this->saveLingotekContentTranslationSettings([
      'node' => [
        'article' => [
          'profiles' => 'automatic',
          'fields' => [
            'title' => 1,
            'body' => 1,
          ],
          'moderation' => [
            'upload_status' => 'draft',
            'download_transition' => 'request_review',
          ],
        ],
        'page' => [
          'profiles' => 'automatic',
          'fields' => [
            'title' => 1,
            'body' => 1,
          ],
        ],
      ],
    ]);
  }

  /**
   * Tests creating an entity with automatic profile but not in upload state is not uploaded.
   */
  public function testCreateEntityWithAutomaticProfileButNotInUploadState() {
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAsRequestReviewNodeForm($edit, 'article');

    $this->assertSession()->pageTextContains('Article Llamas are cool has been created.');
    $this->assertSession()->pageTextNotContains('Llamas are cool sent to Lingotek successfully.');
  }

  /**
   * Tests creating an entity with manual profile but not in upload state is not uploaded.
   */
  public function testCreateEntityWithManualProfileButNotInUploadState() {
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAsRequestReviewNodeForm($edit, 'article');

    $this->assertSession()->pageTextContains('Article Llamas are cool has been created.');
    $this->assertSession()->pageTextNotContains('Llamas are cool sent to Lingotek successfully.');
  }

  /**
   * Tests creating an entity with automatic profile and in upload state is uploaded.
   */
  public function testCreateEntityWithAutomaticProfileAndInUploadState() {
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAsNewDraftNodeForm($edit, 'article');

    $this->assertSession()->pageTextContains('Article Llamas are cool has been created.');
    $this->assertSession()->pageTextContains('Llamas are cool sent to Lingotek successfully.');
  }

  /**
   * Tests creating an entity with manual profile and in upload state is not uploaded.
   */
  public function testCreateEntityWithManualProfileAndInUploadState() {
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAsNewDraftNodeForm($edit, 'article');

    $this->assertSession()->pageTextContains('Article Llamas are cool has been created.');
    $this->assertSession()->pageTextNotContains('Llamas are cool sent to Lingotek successfully.');
  }

  /**
   * Tests updating an entity with automatic profile but not in upload state is not uploaded.
   */
  public function testUpdateEntityWithAutomaticProfileButNotInUploadState() {
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAsNewDraftNodeForm($edit, 'article');

    $this->assertSession()->pageTextContains('Article Llamas are cool has been created.');
    $this->editAsRequestReviewNodeForm('/node/1/edit', $edit);

    $this->assertSession()->pageTextContains('Article Llamas are cool has been updated.');
    $this->assertSession()->pageTextNotContains('Llamas are cool was updated and sent to Lingotek successfully.');
  }

  /**
   * Tests updating an entity with manual profile but not in upload state is not uploaded.
   */
  public function testUpdateEntityWithManualProfileButNotInUploadState() {
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAsNewDraftNodeForm($edit, 'article');

    $this->assertSession()->pageTextContains('Article Llamas are cool has been created.');
    $this->editAsRequestReviewNodeForm('/node/1/edit', $edit);

    $this->assertSession()->pageTextContains('Article Llamas are cool has been updated.');
    $this->assertSession()->pageTextNotContains('Llamas are cool was updated and sent to Lingotek successfully.');
  }

  /**
   * Tests updating an entity with automatic profile and in upload state is uploaded.
   */
  public function testUpdateEntityWithAutomaticProfileAndInUploadState() {
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAsNewDraftNodeForm($edit, 'article');

    $this->assertSession()->pageTextContains('Article Llamas are cool has been created.');
    $edit['body[0][value]'] = 'Llamas are very cool!';
    $this->editAsNewDraftNodeForm('/node/1/edit', $edit);

    $this->assertSession()->pageTextContains('Article Llamas are cool has been updated.');
    $this->assertSession()->pageTextContains('Llamas are cool was updated and sent to Lingotek successfully.');
  }

  /**
   * Tests updating an entity with manual profile and in upload state is not uploaded.
   */
  public function testUpdateEntityWithManualProfileAndInUploadState() {
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAsNewDraftNodeForm($edit, 'article');

    $this->assertSession()->pageTextContains('Article Llamas are cool has been created.');
    $this->editAsNewDraftNodeForm('/node/1/edit', $edit);

    $this->assertSession()->pageTextContains('Article Llamas are cool has been updated.');
    $this->assertSession()->pageTextNotContains('Llamas are cool was updated and sent to Lingotek successfully.');
  }

  protected function configureNeedsReviewAsUploadState() {
    $this->saveLingotekContentTranslationSettings([
      'node' => [
        'article' => [
          'profiles' => 'automatic',
          'fields' => [
            'title' => 1,
            'body' => 1,
          ],
          'moderation' => [
            'upload_status' => 'needs_review',
            'download_transition' => 'publish',
          ],
        ],
      ],
    ]);
  }

  public function testModerationToUploadStateWithAutomaticProfileTriggersUpload() {
    $this->configureNeedsReviewAsUploadState();

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAsNewDraftNodeForm($edit, 'article');

    // Moderate.
    $edit = ['new_state' => 'needs_review'];
    $this->submitForm($edit, 'Apply');
    $this->assertSession()->pageTextContains('The moderation state has been updated.');
    $this->assertSession()->pageTextContains('Llamas are cool sent to Lingotek successfully.');
  }

  public function testModerationToNonUploadStateWithAutomaticProfileDoesntTriggerUpload() {
    $this->configureNeedsReviewAsUploadState();

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAsNewDraftNodeForm($edit, 'article');

    // Moderate.
    $edit = ['new_state' => 'published'];
    $this->submitForm($edit, 'Apply');
    $this->assertSession()->pageTextContains('The moderation state has been updated.');
    $this->assertSession()->pageTextNotContains('Llamas are cool sent to Lingotek successfully.');
  }

  public function testModerationToUploadStateWithManualProfileDoesntTriggerUpload() {
    $this->configureNeedsReviewAsUploadState();

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAsNewDraftNodeForm($edit, 'article');

    // Moderate.
    $edit = ['new_state' => 'needs_review'];
    $this->submitForm($edit, 'Apply');
    $this->assertSession()->pageTextContains('The moderation state has been updated.');
    $this->assertSession()->pageTextNotContains('Llamas are cool sent to Lingotek successfully.');
  }

  public function testModerationToNonUploadStateWithManualProfileDoesntTriggerUpload() {
    $this->configureNeedsReviewAsUploadState();

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAsNewDraftNodeForm($edit, 'article');

    // Moderate.
    $edit = ['new_state' => 'published'];
    $this->submitForm($edit, 'Apply');
    $this->assertSession()->pageTextContains('The moderation state has been updated.');
    $this->assertSession()->pageTextNotContains('Llamas are cool sent to Lingotek successfully.');
  }

  public function testDownloadFromUploadStateTriggersATransition() {
    $this->configureNeedsReviewAsUploadState();

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAsNewDraftNodeForm($edit, 'article');

    // The status is draft.
    $value = $this->xpath('//div[@id="edit-current"]/text()');
    $value = trim($value[1]->getText());
    $this->assertEquals($value, 'Draft', 'Workbench current status is draft');

    // Moderate to Needs review, so it's uploaded.
    $edit = ['new_state' => 'needs_review'];
    $this->submitForm($edit, 'Apply');

    // The status is needs review.
    $value = $this->xpath('//div[@id="edit-current"]/text()');
    $value = trim($value[1]->getText());
    $this->assertEquals($value, 'Needs Review', 'Workbench current status is Needs Review');

    $this->goToContentBulkManagementForm();
    // Request translation.
    $this->clickLink('ES');
    // Check translation.
    $this->clickLink('ES');
    // Download translation.
    $this->clickLink('ES');

    // Let's see the current status is modified.
    $this->clickLink('Llamas are cool');
    $this->assertSession()->fieldValueNotEquals('new_state', 'The transition to a new content moderation status happened (so no moderation form is shown).');
  }

  public function testDownloadWhenContentModerationWasSetupAfterLingotek() {
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit, 'page');

    $this->goToContentBulkManagementForm();
    // Request translation.
    $this->clickLink('ES');

    $this->enableModerationThroughUI('page');

    $this->goToContentBulkManagementForm();

    // Check translation.
    $this->clickLink('ES');
    // Download translation.
    $this->clickLink('ES');

    $this->assertTargetStatus('ES', Lingotek::STATUS_CURRENT);
  }

  public function testDownloadWithInvalidTransition() {
    $this->configureNeedsReviewAsUploadState();

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAsNewDraftNodeForm($edit, 'article');

    $this->config('lingotek.settings')
      ->set('translate.entity.node.article.content_moderation.download_transition', 'invalid_transition')
      ->save();

    // The status is draft.
    $value = $this->xpath('//div[@id="edit-current"]/text()');
    $value = trim($value[1]->getText());
    $this->assertEquals($value, 'Draft', 'Workbench current status is draft');

    // Moderate to Needs review, so it's uploaded.
    $edit = ['new_state' => 'needs_review'];
    $this->submitForm($edit, 'Apply');

    // The status is needs review.
    $value = $this->xpath('//div[@id="edit-current"]/text()');
    $value = trim($value[1]->getText());
    $this->assertEquals($value, 'Needs Review', 'Workbench current status is Needs Review');

    $this->goToContentBulkManagementForm();
    // Request translation.
    $this->clickLink('ES');
    // Check translation.
    $this->clickLink('ES');
    // Download translation.
    $this->clickLink('ES');
    $this->assertSession()->pageTextContains('The translation of node Llamas are cool into es_MX has been downloaded.');

    // Let's see the current status is modified.
    $this->clickLink('Llamas are cool');
    // The status didn't change.
    $value = $this->xpath('//div[@id="edit-current"]/text()');
    $value = trim($value[1]->getText());
    $this->assertEquals($value, 'Needs Review', 'Content moderation current status is Needs Review');
  }

  public function testDownloadFromNotUploadStateDoesntTriggerATransition() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+revision');
    $this->configureNeedsReviewAsUploadState();

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAsNewDraftNodeForm($edit, 'article');

    // The status is draft.
    $value = $this->xpath('//div[@id="edit-current"]/text()');
    $value = trim($value[1]->getText());
    $this->assertEquals($value, 'Draft', 'Workbench current status is draft');

    // Moderate to Needs review, so it's uploaded.
    $edit = ['new_state' => 'needs_review'];
    $this->submitForm($edit, 'Apply');

    // Moderate back to draft, so the transition won't happen on download.
    $edit = ['new_state' => 'draft'];
    $this->submitForm($edit, 'Apply');

    $this->goToContentBulkManagementForm();
    // Request translation.
    $this->clickLink('ES');
    // Check translation.
    $this->clickLink('ES');
    // Download translation.
    $this->clickLink('ES');

    // Let's see the current status is unmodified.
    $this->clickLink('Llamas are cool');
    $value = $this->xpath('//div[@id="edit-current"]/text()');
    $value = trim($value[1]->getText());
    $this->assertEquals($value, 'Draft', 'The transition to a new content moderation status didn\'t happen because the source wasn\'t the expected.');
  }

  public function testPublishedRevisionDownloadDoesntOverwriteDraftNonDefaultRevision() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()
      ->set('lingotek.uploaded_content_type', 'nodesource+revision');

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit, 'article');
    $this->assertSession()->pageTextContains('Article Llamas are cool has been created.');

    $this->goToContentBulkManagementForm();

    // Upload.
    $this->clickLink('EN');
    $this->assertSession()->pageTextContains('Node Llamas are cool has been uploaded.');

    // Request translation.
    $this->clickLink('ES');
    $this->assertSession()->pageTextContains('Locale \'es_MX\' was added as a translation target for node Llamas are cool.');
    // Check translation.
    $this->clickLink('ES');
    $this->assertSession()->pageTextContains('The es_MX translation for node Llamas are cool is ready for download.');

    // Edit the original as a new draft.
    $edit = [];
    $edit['title[0][value]'] = 'Dogs are cool';
    $edit['body[0][value]'] = 'Dogs are very cool';
    $this->editAsNewDraftNodeForm('/node/1/edit', $edit);
    $this->assertSession()->pageTextNotContains('Dogs are cool was updated and sent to Lingotek successfully.');
    $this->assertSession()->pageTextContains('Article Dogs are cool has been updated.');

    // The source published revision is the default one.
    $this->drupalGet('node/1');
    $this->assertSession()->pageTextContains('Llamas are cool');

    $this->goToContentBulkManagementForm();

    // Download translation.
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'download_translation:es',
    ];
    $this->submitForm($edit, t('Execute'));
    $this->assertSession()->pageTextContains('Operations completed.');

    // The source published revision must be the same still.
    $this->drupalGet('node/1');
    $this->assertSession()->pageTextContains('Llamas are cool');

    // But the latest revision should keep the unpublished revision content.
    $this->drupalGet('node/1/latest');
    $this->assertSession()->pageTextContains('Dogs are cool');

    // The published revision for the translated content is the right one.
    $this->drupalGet('es/node/1');
    $this->assertSession()->pageTextContains('Las llamas son chulas');

    // There's only one revision for Spanish so we cannot check the latest.
  }

  public function testPublishedRevisionMultipleDownloadsDoesntOverwriteDraftNonDefaultRevision() {
    // Add another language.
    ConfigurableLanguage::createFromLangcode('it')
      ->setThirdPartySetting('lingotek', 'locale', 'it_IT')
      ->save();

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()
      ->set('lingotek.uploaded_content_type', 'nodesource+revision');

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit, 'article');
    $this->assertSession()->pageTextContains('Article Llamas are cool has been created.');

    $this->goToContentBulkManagementForm();

    // Upload.
    $this->clickLink('EN');
    $this->assertSession()->pageTextContains('Node Llamas are cool has been uploaded.');

    // Request translation.
    $this->clickLink('ES');
    $this->assertSession()->pageTextContains('Locale \'es_MX\' was added as a translation target for node Llamas are cool.');
    // Check translation.
    $this->clickLink('ES');
    $this->assertSession()->pageTextContains('The es_MX translation for node Llamas are cool is ready for download.');

    // Request translation.
    $this->clickLink('IT');
    $this->assertSession()->pageTextContains('Locale \'it_IT\' was added as a translation target for node Llamas are cool.');
    // Check translation.
    $this->clickLink('IT');
    $this->assertSession()->pageTextContains('The it_IT translation for node Llamas are cool is ready for download.');

    // Edit the original as a new draft.
    $edit = [];
    $edit['title[0][value]'] = 'Dogs are cool';
    $edit['body[0][value]'] = 'Dogs are very cool';
    $this->editAsNewDraftNodeForm('/node/1/edit', $edit);
    $this->assertSession()->pageTextNotContains('Dogs are cool was updated and sent to Lingotek successfully.');
    $this->assertSession()->pageTextContains('Article Dogs are cool has been updated.');

    // The source published revision is the default one.
    $this->drupalGet('node/1');
    $this->assertSession()->pageTextContains('Llamas are cool');

    $this->goToContentBulkManagementForm();

    // Download translation.
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'download_translations',
    ];
    $this->submitForm($edit, t('Execute'));
    $this->assertSession()->pageTextContains('Operations completed.');

    // The source published revision must be the same still.
    $this->drupalGet('node/1');
    $this->assertSession()->pageTextContains('Llamas are cool');

    // But the latest revision should keep the unpublished revision content.
    $this->drupalGet('node/1/latest');
    $this->assertSession()->pageTextContains('Dogs are cool');

    // The published revision for the Spanish translated content is the right one.
    $this->drupalGet('es/node/1');
    $this->assertSession()->pageTextContains('Las llamas son chulas');

    // There's only one revision for Spanish so we cannot check the latest.

    // The published revision for the Italian translated content is the right one.
    $this->drupalGet('it/node/1');
    $this->assertSession()->pageTextContains('Las llamas son chulas');

    // There's only one revision for Italian too so we cannot check the latest.
  }

  public function testDraftRevisionDownloadDoesntOverwriteDraftNonDefaultRevision() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()
      ->set('lingotek.uploaded_content_type', 'nodeedited+revision');

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit, 'article');
    $this->assertSession()->pageTextContains('Article Llamas are cool has been created.');

    // Edit the original as a new draft.
    $edit = [];
    $edit['title[0][value]'] = 'Dogs are cool';
    $edit['body[0][value]'] = 'Dogs are very cool';
    $this->editAsNewDraftNodeForm('/node/1/edit', $edit);
    $this->assertSession()->pageTextNotContains('Dogs are cool was updated and sent to Lingotek successfully.');
    $this->assertSession()->pageTextContains('Article Dogs are cool has been updated.');

    $this->goToContentBulkManagementForm();

    // Upload.
    $this->clickLink('EN');
    $this->assertSession()->pageTextContains('Node Llamas are cool has been uploaded.');

    // Request translation.
    $this->clickLink('ES');
    $this->assertSession()->pageTextContains('Locale \'es_MX\' was added as a translation target for node Llamas are cool.');
    // Check translation.
    $this->clickLink('ES');
    $this->assertSession()->pageTextContains('The es_MX translation for node Llamas are cool is ready for download.');

    // The source published revision is the default one.
    $this->drupalGet('node/1');
    $this->assertSession()->pageTextContains('Llamas are cool');

    $this->goToContentBulkManagementForm();

    // Download translation.
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'download_translation:es',
    ];
    $this->submitForm($edit, t('Execute'));
    $this->assertSession()->pageTextContains('Operations completed.');

    // The source published revision must be the same still.
    $this->drupalGet('node/1');
    $this->assertSession()->pageTextContains('Llamas are cool');

    // But the latest revision should keep the unpublished revision content.
    $this->drupalGet('node/1/latest');
    $this->assertSession()->pageTextContains('Dogs are cool');

    // The translated revision is not published, so the source published
    // revision is displayed.
    $this->drupalGet('es/node/1');
    $this->assertSession()->pageTextContains('Llamas are cool');

    // And it's also the latest published revision.
    $this->drupalGet('es/node/1/latest');
    $this->assertSession()->pageTextContains('Los perros son chulos');
  }

  public function testBulkManagementUploadsLatestDraftRevision() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()
      ->set('lingotek.uploaded_content_type', 'nodeedited+revision');

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit, 'article');
    $this->assertSession()->pageTextContains('Article Llamas are cool has been created.');

    // Edit the original as a new draft.
    $edit = [];
    $edit['title[0][value]'] = 'Dogs are cool';
    $edit['body[0][value]'] = 'Dogs are very cool';
    $this->editAsNewDraftNodeForm('/node/1/edit', $edit);
    $this->assertSession()->pageTextNotContains('Dogs are cool was updated and sent to Lingotek successfully.');
    $this->assertSession()->pageTextContains('Article Dogs are cool has been updated.');

    $this->goToContentBulkManagementForm();

    // Upload.
    $this->clickLink('EN');
    $this->assertSession()->pageTextContains('Node Llamas are cool has been uploaded.');

    // Check that only the last revision fields have been uploaded.
    $data = json_decode(\Drupal::state()
      ->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 2);
    $this->assertEquals('Dogs are cool', $data['title'][0]['value']);
    $this->assertEquals(1, count($data['body'][0]));
    $this->assertEquals('Dogs are very cool', $data['body'][0]['value']);
    $this->assertSame('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));
  }

  /**
   * Tests a content entity that is enabled, but with a disabled bundle.
   */
  public function testUnconfiguredBundle() {
    $this->drupalGet('/admin/lingotek/settings');

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit, 'page');

    $this->assertSession()->pageTextContains('Page Llamas are cool has been created.');
    $this->assertSession()->pageTextContains('Llamas are cool sent to Lingotek successfully.');
  }

  /**
   * Enable moderation for a specified content type, using the UI.
   *
   * @param string $content_type_id
   *   Machine name.
   */
  protected function enableModerationThroughUI($content_type_id) {
    $this->drupalGet('/admin/config/workflow/workflows/manage/editorial/type/node');
    $this->assertSession()->fieldExists("bundles[$content_type_id]");
    $edit["bundles[$content_type_id]"] = TRUE;
    $this->submitForm($edit, t('Save'));
  }

  /**
   * Adds a review state to the editorial workflow.
   */
  protected function addReviewStateToEditorialWorkflow() {
    // Add a "Needs review" state to the editorial workflow.
    $workflow = Workflow::load('editorial');
    $definition = $workflow->getTypePlugin();
    $definition->addState('needs_review', 'Needs Review');
    $definition->addTransition('request_review', 'Request Review', ['draft'], 'needs_review');
    $definition->addTransition('publish_review', 'Publish Review', ['needs_review'], 'published');
    $definition->addTransition('back_to_draft', 'Back to Draft', ['needs_review'], 'draft');
    $workflow->save();
  }

}
