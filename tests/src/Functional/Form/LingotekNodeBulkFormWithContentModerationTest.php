<?php

namespace Drupal\Tests\lingotek\Functional\Form;

use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\workflows\Entity\Workflow;

/**
 * Tests the bulk management form.
 *
 * @group lingotek
 */
class LingotekNodeBulkFormWithContentModerationTest extends LingotekNodeBulkFormTest {

  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'node', 'content_moderation'];

  /**
   * A node used for testing.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalPlaceBlock('page_title_block', ['region' => 'content', 'weight' => -5]);
    $this->drupalPlaceBlock('local_tasks_block', ['region' => 'content', 'weight' => -10]);
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
            'upload_status' => 'published',
            'download_transition' => 'request_review',
          ],
        ],
      ],
    ]);

  }

  /**
   * Tests if content state filter works correctly
   */
  public function testContentStateFilter() {
    $assert_session = $this->assertSession();

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAsNewDraftNodeForm($edit);

    // Go to the bulk management form.
    $this->goToContentBulkManagementForm();
    $assert_session->optionExists('filters[advanced_options][moderation_state]', 'All');
    $assert_session->optionExists('filters[advanced_options][moderation_state]', 'archived');
    $assert_session->optionExists('filters[advanced_options][moderation_state]', 'published');
    $assert_session->optionExists('filters[advanced_options][moderation_state]', 'draft');

    // After we filter by "draft", there is no pager and the rows
    // selected are the ones expected.
    $update = [
      'filters[advanced_options][moderation_state]' => 'draft',
    ];
    $this->submitForm($update, 'edit-filters-actions-submit');
    $assert_session->linkExists('Llamas are cool');

    $this->assertSession()->fieldValueEquals('filters[advanced_options][moderation_state]', 'draft');

    // Change the content moderation state to published
    $this->saveAndKeepPublishedNodeForm($edit, 1);
    $this->goToContentBulkManagementForm();

    $update = [
      'filters[advanced_options][moderation_state]' => 'published',
    ];
    $this->submitForm($update, 'edit-filters-actions-submit');
    $assert_session->linkExists('Llamas are cool');

    $this->assertSession()->fieldValueEquals('filters[advanced_options][moderation_state]', 'published');

    // Change the content moderation state to archived
    $this->saveAndArchiveNodeForm($edit, 1);
    $this->goToContentBulkManagementForm();

    $update = [
      'filters[advanced_options][moderation_state]' => 'archived',
    ];
    $this->submitForm($update, 'edit-filters-actions-submit');
    $assert_session->linkExists('Llamas are cool');

    $this->assertSession()->fieldValueEquals('filters[advanced_options][moderation_state]', 'archived');

    $update = [
      'filters[advanced_options][moderation_state]' => 'published',
    ];
    $this->submitForm($update, 'edit-filters-actions-submit');

    // Make sure the document does not show up when we filter by published and the document is archived
    $assert_session->linkNotExists('Llamas are cool');
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
