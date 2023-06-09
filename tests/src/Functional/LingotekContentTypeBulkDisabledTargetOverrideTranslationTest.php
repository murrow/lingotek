<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\Entity\LingotekProfile;
use Drupal\lingotek\Lingotek;

/**
 * Tests translating a content type using the bulk management form.
 *
 * @group lingotek
 */
class LingotekContentTypeBulkDisabledTargetOverrideTranslationTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'node'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create Article node types.
    $type = $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')
      ->setThirdPartySetting('lingotek', 'locale', 'es_MX')
      ->save();
    ConfigurableLanguage::createFromLangcode('ca')
      ->setThirdPartySetting('lingotek', 'locale', 'ca_ES')
      ->save();

    $profile = LingotekProfile::create([
      'label' => 'Profile with disabled targets',
      'id' => 'profile_with_disabled_targets',
      'project' => 'test_project',
      'vault' => 'test_vault',
      'auto_upload' => FALSE,
      'workflow' => 'test_workflow',
      'language_overrides' => [
        'es' => ['overrides' => 'custom', 'custom' => ['auto_request' => TRUE, 'workflow' => 'test_workflow', 'vault' => 'test_vault']],
        'ca' => ['overrides' => 'disabled'],
      ],
    ]);
    $profile->save();

    $this->saveLingotekConfigTranslationSettings([
      'node_type' => 'profile_with_disabled_targets',
    ]);
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'content_type');
  }

  /**
   * Tests that a content type can be translated using the links on the management page.
   */
  public function testContentTypeTranslationUsingLinks() {
    $assert_session = $this->assertSession();

    $basepath = \Drupal::request()->getBasePath();
    $this->goToConfigBulkManagementForm('node_type');

    // Clicking English must init the upload of content.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we cannot request yet a translation.
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/request/node_type/article/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/request/node_type/article/ca_ES?destination=' . $basepath . '/admin/lingotek/config/manage');

    $this->clickLink('EN');
    $this->assertSession()->pageTextContains('Article uploaded successfully');
    $this->assertSame('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // There is a link for checking status.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/check_upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we can already request a translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/node_type/article/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/request/node_type/article/ca_ES?destination=' . $basepath . '/admin/lingotek/config/manage');

    $this->clickLink('EN');
    $this->assertSession()->pageTextContains('Article status checked successfully');

    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/request/node_type/article/ca_ES?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/node_type/article/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');

    $this->assertTargetStatus('es', Lingotek::STATUS_REQUEST);
    $this->assertTargetStatus('ca', Lingotek::STATUS_DISABLED);
  }

  /**
   * Tests that a field can be translated using the actions on the management page.
   */
  public function testContentTypeTranslationUsingActions() {
    $assert_session = $this->assertSession();
    $basepath = \Drupal::request()->getBasePath();

    $this->goToConfigBulkManagementForm('node_type');

    // Clicking English must init the upload of content.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we cannot request yet a translation.
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/request/node_type/article/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/request/node_type/article/ca_ES?destination=' . $basepath . '/admin/lingotek/config/manage');

    // I can init the upload of content.
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->submitForm($edit, $this->getApplyActionsButtonLabel());
    $this->assertSame('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    $this->assertSession()->pageTextContains('Operations completed.');

    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/node_type/article/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/request/node_type/article/ca_ES?destination=' . $basepath . '/admin/lingotek/config/manage');

    $this->assertTargetStatus('es', Lingotek::STATUS_REQUEST);
    $this->assertTargetStatus('ca', Lingotek::STATUS_DISABLED);

    // Request the disabled target translation.
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslation('ca', 'node_type'),
    ];
    $this->submitForm($edit, $this->getApplyActionsButtonLabel());
    $this->assertSame(NULL, \Drupal::state()
      ->get('lingotek.added_target_locale'));
    $this->assertSame(NULL, \Drupal::state()
      ->get('lingotek.requested_locales'));

    $this->assertSession()->pageTextContains('Operations completed.');

    $this->assertTargetStatus('es', Lingotek::STATUS_REQUEST);
    $this->assertTargetStatus('ca', Lingotek::STATUS_DISABLED);

    // Check status of the disabled target translation.
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/check_download/node_type/article/ca_ES?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => 'check_translation:ca',
    ];
    $this->submitForm($edit, $this->getApplyActionsButtonLabel());
    $this->assertSame(NULL, \Drupal::state()
      ->get('lingotek.checked_target_locale'));

    $this->assertTargetStatus('es', Lingotek::STATUS_REQUEST);
    $this->assertTargetStatus('ca', Lingotek::STATUS_DISABLED);

    // Download the Catalan translation.
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/download/node_type/article/ca_ES?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => 'download_translation:ca',
    ];
    $this->submitForm($edit, $this->getApplyActionsButtonLabel());
    $this->assertSame(NULL, \Drupal::state()
      ->get('lingotek.downloaded_locale'));

    $this->assertTargetStatus('es', Lingotek::STATUS_REQUEST);
    $this->assertTargetStatus('ca', Lingotek::STATUS_DISABLED);
  }

  /**
   * Tests that a field can be translated using the actions on the management page for multiple locales.
   */
  public function testContentTypeTranslationUsingActionsForMultipleLocales() {
    $assert_session = $this->assertSession();
    $basepath = \Drupal::request()->getBasePath();

    $this->goToConfigBulkManagementForm('node_type');

    // Clicking English must init the upload of content.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we cannot request yet a translation.
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/request/node_type/article/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/request/node_type/article/ca_ES?destination=' . $basepath . '/admin/lingotek/config/manage');

    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node_type'),
    ];
    $this->submitForm($edit, $this->getApplyActionsButtonLabel());
    $this->assertSame('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    $this->assertSession()->pageTextContains('Operations completed.');

    // There is a link for checking status.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/check_upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckUpload('node_type'),
    ];
    $this->submitForm($edit, $this->getApplyActionsButtonLabel());
    $this->assertSame('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    $this->assertSession()->pageTextContains('Operations completed.');

    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/node_type/article/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/request/node_type/article/ca_ES?destination=' . $basepath . '/admin/lingotek/config/manage');

    $this->assertTargetStatus('es', Lingotek::STATUS_REQUEST);
    $this->assertTargetStatus('ca', Lingotek::STATUS_DISABLED);

    // Request the disabled target translation.
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslations('node_type'),
    ];
    $this->submitForm($edit, $this->getApplyActionsButtonLabel());
    $this->assertSame(['dummy-document-hash-id' => ['es_MX']], \Drupal::state()
      ->get('lingotek.requested_locales'));

    $this->assertSession()->pageTextContains('Operations completed.');

    $this->assertTargetStatus('es', Lingotek::STATUS_PENDING);
    $this->assertTargetStatus('ca', Lingotek::STATUS_DISABLED);

    // Check status of the disabled target translation.
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/check_download/node_type/article/ca_ES?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckTranslations('node_type'),
    ];
    $this->submitForm($edit, $this->getApplyActionsButtonLabel());
    $this->assertSame(NULL, \Drupal::state()
      ->get('lingotek.checked_target_locale'));

    $this->assertTargetStatus('es', Lingotek::STATUS_READY);
    $this->assertTargetStatus('ca', Lingotek::STATUS_DISABLED);

    // Download the Catalan translation.
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/download/node_type/article/ca_ES?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForDownloadTranslations('node'),
    ];
    $this->submitForm($edit, $this->getApplyActionsButtonLabel());
    $this->assertSame('es_MX', \Drupal::state()
      ->get('lingotek.downloaded_locale'));

    $this->assertTargetStatus('es', Lingotek::STATUS_CURRENT);
    $this->assertTargetStatus('ca', Lingotek::STATUS_DISABLED);
  }

}
