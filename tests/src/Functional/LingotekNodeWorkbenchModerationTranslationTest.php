<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\workbench_moderation\Entity\ModerationState;

/**
 * Tests translating a node with workbench moderation enabled.
 *
 * @group lingotek
 */
class LingotekNodeWorkbenchModerationTranslationTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'node', 'workbench_moderation'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('page_title_block', ['region' => 'content', 'weight' => -5]);
    $this->drupalPlaceBlock('local_tasks_block', ['region' => 'content', 'weight' => -10]);

    // Create Article node types.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Page', 'new_revision' => FALSE]);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'page')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'page', TRUE);

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    // Enable content moderation for articles.
    // Enable workbench moderation.
    $this->enableModerationThroughUI('article',
      ['draft', 'needs_review', 'published'], 'draft');

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
            'download_transition' => 'draft_needs_review',
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
   * Tests that new revisions are created when processing with Lingotek.
   */
  public function testNewRevisionCreatedWhenProcessing() {
    $assert_session = $this->assertSession();
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // Clicking English must init the upload of content.
    $this->assertLingotekUploadLink();
    // And we cannot request yet a translation.
    $this->assertNoLingotekRequestTranslationLink('es_MX');
    $this->clickLink('EN');
    $this->assertSession()->pageTextContains('Node Llamas are cool has been uploaded.');
    $this->assertSame('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    $this->clickLink('Llamas are cool');

    // Only one revision stored.
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $result = $node_storage->getQuery()
      ->accessCheck(FALSE)
      ->allRevisions()
      ->condition('nid', 1)
      ->sort('vid', 'DESC')
      ->pager(50)
      ->count()
      ->execute();
    $this->assertEquals(1, $result, 'Only one revision is stored.');

    $this->goToContentBulkManagementForm();

    // There is a link for checking status.
    $this->assertLingotekCheckSourceStatusLink();
    // And we can already request a translation.
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->clickLink('EN');
    $this->assertSession()->pageTextContains('The import for node Llamas are cool is complete.');

    // Request the Spanish translation.
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->clickLink('ES');
    $this->assertSession()->pageTextContains("Locale 'es_MX' was added as a translation target for node Llamas are cool.");
    $this->assertSame('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLingotekCheckTargetStatusLink('es_MX');
    $this->clickLink('ES');
    $this->assertSame('es_MX', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertSession()->pageTextContains('The es_MX translation for node Llamas are cool is ready for download.');

    // Download the Spanish translation.
    $this->assertLingotekDownloadTargetLink('es_MX');
    $this->clickLink('ES');
    $this->assertSession()->pageTextContains('The translation of node Llamas are cool into es_MX has been downloaded.');
    $this->assertSame('es_MX', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLingotekWorkbenchLink('es_MX', 'dummy-document-hash-id', 'ES');

    // Open the node.
    $this->clickLink('Llamas are cool');

    // There is a revisions tab as the translation creates a new revision.
    $assert_session->linkExists('Revisions');
    $this->clickLink('Revisions');
    $this->drupalGet('es/node/1/revisions');
    $this->assertSession()->pageTextContains('Document translated into ES by Lingotek.');

    // Only one revision stored.
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $result = $node_storage->getQuery()
      ->accessCheck(FALSE)
      ->allRevisions()
      ->condition('nid', 1)
      ->sort('vid', 'DESC')
      ->pager(50)
      ->count()
      ->execute();
    $this->assertEquals(2, $result, 'A new revision is stored.');
  }

  /**
   * Enable moderation for a specified content type, using the UI.
   *
   * @param string $content_type_id
   *   Machine name.
   * @param string[] $allowed_states
   *   Array of allowed state IDs.
   * @param string $default_state
   *   Default state.
   */
  protected function enableModerationThroughUI($content_type_id, array $allowed_states, $default_state) {
    $this->drupalGet('admin/structure/types/manage/' . $content_type_id . '/moderation');
    $this->assertSession()->fieldExists('enable_moderation_state');
    $this->assertSession()->checkboxNotChecked('edit-enable-moderation-state');

    $edit['enable_moderation_state'] = 1;

    /** @var \Drupal\workbench_moderation\Entity\ModerationState $state */
    foreach (ModerationState::loadMultiple() as $id => $state) {
      $key = $state->isPublishedState() ? 'allowed_moderation_states_published[' . $state->id() . ']' : 'allowed_moderation_states_unpublished[' . $state->id() . ']';
      $edit[$key] = (int) in_array($id, $allowed_states);
    }

    $edit['default_moderation_state'] = $default_state;

    $this->submitForm($edit, t('Save'));
  }

  /**
   * Tests that new revisions are created when processing with Lingotek.
   */
  public function testNoNewRevisionCreatedWhenProcessing() {
    $this->drupalGet('admin/structure/types/manage/page');

    $assert_session = $this->assertSession();
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit, 'page');

    $this->goToContentBulkManagementForm();

    // Clicking English must init the upload of content.
    $this->assertLingotekUploadLink();
    // And we cannot request yet a translation.
    $this->assertNoLingotekRequestTranslationLink('es_MX');
    $this->clickLink('EN');
    $this->assertSession()->pageTextContains('Node Llamas are cool has been uploaded.');
    $this->assertSame('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    $this->clickLink('Llamas are cool');

    // Only one revision stored.
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $result = $node_storage->getQuery()
      ->accessCheck(FALSE)
      ->allRevisions()
      ->condition('nid', 1)
      ->sort('vid', 'DESC')
      ->pager(50)
      ->count()
      ->execute();
    $this->assertEquals(1, $result, 'Only one revision is stored.');

    $this->goToContentBulkManagementForm();

    // There is a link for checking status.
    $this->assertLingotekCheckSourceStatusLink();
    // And we can already request a translation.
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->clickLink('EN');
    $this->assertSession()->pageTextContains('The import for node Llamas are cool is complete.');

    // Request the Spanish translation.
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->clickLink('ES');
    $this->assertSession()->pageTextContains("Locale 'es_MX' was added as a translation target for node Llamas are cool.");
    $this->assertSame('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLingotekCheckTargetStatusLink('es_MX');
    $this->clickLink('ES');
    $this->assertSame('es_MX', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertSession()->pageTextContains('The es_MX translation for node Llamas are cool is ready for download.');

    // Download the Spanish translation.
    $this->assertLingotekDownloadTargetLink('es_MX');
    $this->clickLink('ES');
    $this->assertSession()->pageTextContains('The translation of node Llamas are cool into es_MX has been downloaded.');
    $this->assertSame('es_MX', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLingotekWorkbenchLink('es_MX', 'dummy-document-hash-id', 'ES');

    // Open the node.
    $this->clickLink('Llamas are cool');

    if (floatval(\Drupal::VERSION) > 9.2) {
      // There is a revisions tab even if the translation doesn't create a new revision.
      // In https://www.drupal.org/node/3226487 (Drupal 9.3) the Revisions tab was added again
      // even if there is only one revision.
      $assert_session->linkExists('Revisions');
    }
    else {
      // There is not a revisions tab as the translation doesn't create a new revision.
      $assert_session->linkNotExists('Revisions');
    }

    // Only one revision stored.
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $result = $node_storage->getQuery()
      ->accessCheck(FALSE)
      ->allRevisions()
      ->condition('nid', 1)
      ->sort('vid', 'DESC')
      ->pager(50)
      ->count()
      ->execute();
    $this->assertEquals(1, $result, 'A new revision has not been stored.');
  }

}
