<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests debugging a field using the bulk management form.
 *
 * @group lingotek
 */
class LingotekFieldBodyBulkDebugTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'node', 'field_ui'];

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
    node_add_body_field($type);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    $this->saveLingotekConfigTranslationSettings([
      'node_fields' => 'automatic',
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
    $this->goToConfigBulkManagementForm('node_fields');
    // We need to ensure the profile is stored.
    $edit = [
      'table[node.article.body]' => TRUE,
      $this->getBulkOperationFormName() => 'change_profile:manual',
    ];
    $this->submitForm($edit, $this->getApplyActionsButtonLabel());

    $edit = [
      'table[node.article.body]' => TRUE,
      $this->getBulkOperationFormName() => 'debug_export',
    ];
    $this->submitForm($edit, $this->getApplyActionsButtonLabel());

    $this->assertSession()->pageTextContains('Exports available');
    // Download the file.
    $this->clickLink('config.node.article.body.json');

    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertSame('Body', $response['field.field.node.article.body']['label']);
    $this->assertSame('', $response['field.field.node.article.body']['description']);
    $this->assertSame('node.article.body (config): Body', $response['_debug']['title']);
    $this->assertSame('manual', $response['_debug']['profile']);
    $this->assertSame('en_US', $response['_debug']['source_locale']);
  }

}
