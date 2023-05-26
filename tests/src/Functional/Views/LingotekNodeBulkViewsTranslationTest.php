<?php

namespace Drupal\Tests\lingotek\Functional\Views;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\Lingotek;
use Drupal\Tests\lingotek\Functional\LingotekNodeBulkTranslationTest;

/**
 * Tests translating a node using the bulk management view.
 *
 * @group lingotek
 */
class LingotekNodeBulkViewsTranslationTest extends LingotekNodeBulkTranslationTest {

  use LingotekViewsTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'node', 'views'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    \Drupal::getContainer()
      ->get('module_installer')
      ->install(['lingotek_views_test'], TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function testAddContentLinkPresent() {
    $this->markTestSkipped('This doesn\'t apply if we replace the management pages with views. Or if you do, it is your decision to add the content creation link.');
  }

  /**
   * {@inheritdoc}
   */
  public function testNodeTranslationMessageWhenBundleNotConfiguredWithLinks() {
    $this->markTestSkipped('This doesn\'t apply if we replace the management pages with views.');
  }

  /**
   * {@inheritdoc}
   */
  public function testRequestTranslationWithActionWhenLanguageDisabled() {
    // Add a language.
    $italian = ConfigurableLanguage::createFromLangcode('it');
    $italian->save();

    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_config */
    $lingotek_config = \Drupal::service('lingotek.configuration');
    $lingotek_config->disableLanguage($italian);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->submitForm($edit, $this->getApplyActionsButtonLabel());
    $this->assertSame('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    // I can request a translation
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);

    // There is an option for requesting a disabled language.
    $this->assertSession()->optionExists('edit-action', 'Request content item translation to Lingotek for Italian');
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslation('it', 'node'),
    ];
    $this->submitForm($edit, $this->getApplyActionsButtonLabel());

    // But the disabled language won't be requested.
    $this->assertSession()->pageTextContains('Cannot request language Italian (it). That language is not enabled for Lingotek translation.');
  }

  /**
   * {@inheritdoc}
   */
  protected function assertSelectionIsKept(string $key) {
    // No valid selection, so permission denied message.
    $this->assertSession()->pageTextContains('You are not authorized to access this page.');
  }

  /**
   * Overwritten, so untracked can be as not shown.
   * Assert that a content source has the given status.
   *
   * @param string $language
   *   The target language.
   * @param string $status
   *   The status.
   */
  protected function assertSourceStatus($language, $status) {
    if ($status === Lingotek::STATUS_UNTRACKED) {
      $status_target = $this->xpath("//a[contains(@class,'language-icon') and contains(@class,'source-" . strtolower($status) . "')  and contains(text(), '" . strtoupper($language) . "')]");
      // If not found, maybe it didn't have a link.
      if (count($status_target) === 1) {
        $this->assertEquals(count($status_target), 1, 'The source ' . strtoupper($language) . ' has been marked with status ' . strtolower($status) . '.');
      }
      else {
        $status_target = $this->xpath("//span[contains(@class,'language-icon') and contains(@class,'source-" . strtolower($status) . "')  and contains(text(), '" . strtoupper($language) . "')]");
        if (count($status_target) === 1) {
          $this->assertEquals(count($status_target), 1, 'The source ' . strtoupper($language) . ' has been marked with status ' . strtolower($status) . '.');
        }
        else {
          $status_target = $this->xpath("//span[contains(@class,'language-icon')]");
          $this->assertEquals(count($status_target), 0, 'The source ' . strtoupper($language) . ' has been marked with status ' . strtolower($status) . '.');
        }
      }
    }
    else {
      $status_target = $this->xpath("//a[contains(@class,'language-icon') and contains(@class,'source-" . strtolower($status) . "')  and contains(text(), '" . strtoupper($language) . "')]");
      // If not found, maybe it didn't have a link.
      if (count($status_target) === 1) {
        $this->assertEquals(count($status_target), 1, 'The source ' . strtoupper($language) . ' has been marked with status ' . strtolower($status) . '.');
      }
      else {
        $status_target = $this->xpath("//span[contains(@class,'language-icon') and contains(@class,'source-" . strtolower($status) . "')  and contains(text(), '" . strtoupper($language) . "')]");
        $this->assertEquals(count($status_target), 1, 'The source ' . strtoupper($language) . ' has been marked with status ' . strtolower($status) . '.');
      }
    }
  }

}
