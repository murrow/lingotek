<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests debugging a config entity using the bulk management form.
 *
 * @group lingotek
 */
class LingotekContentTypeBulkDebugTest extends LingotekTestBase {

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
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    $this->saveLingotekConfigTranslationSettings([
      'node_type' => 'automatic',
    ]);
  }

  /**
   * Tests that a config can be exported using the debug options on the management page.
   */
  public function testDebugOptionsDisplay() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    // There is no 'debug' option group.
    $this->assertEmpty($this->xpath('//select[@id=:id]//optgroup[@label=:label]', [':id' => 'edit-operation', ':label' => 'Debug']), 'There is no debug group.');

    // Enable the debug operations.
    $this->drupalGet('admin/lingotek/settings');
    $this->submitForm([], t('Enable debug operations'));

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();
    // There should be a 'debug' option group with the right operation.
    $this->assertNotEmpty($this->xpath('//select[@id=:id]//optgroup[@label=:label]', [':id' => 'edit-operation', ':label' => 'Debug']), 'There is a debug group.');
    $this->assertNotEmpty($this->xpath('//select[@id=:id]//option[@value=:value]', [':id' => 'edit-operation', ':value' => 'debug_export']), 'There is a debug export option.');
  }

  public function testDebugExport() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Enable the debug operations.
    $this->drupalGet('admin/lingotek/settings');
    $this->submitForm([], t('Enable debug operations'));

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_type');

    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => 'debug_export',
    ];
    $this->submitForm($edit, $this->getApplyActionsButtonLabel());

    $this->assertSession()->pageTextContains('Exports available');
    // Download the file.
    $this->clickLink('config.node_type.json');

    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertSame('Article', $response['node.type.article']['name']);
    $this->assertSame(NULL, $response['node.type.article']['description']);
    $this->assertSame(NULL, $response['node.type.article']['help']);
    $this->assertSame('node_type (config): Article content type', $response['_debug']['title']);
    $this->assertSame('automatic', $response['_debug']['profile']);
    $this->assertSame('en_US', $response['_debug']['source_locale']);
  }

}
