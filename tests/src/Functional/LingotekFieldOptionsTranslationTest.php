<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests translating a options field.
 *
 * @group lingotek
 * @group legacy
 * TODO: Remove legacy group when 8.8.x is not supported.
 * @see https://www.drupal.org/project/lingotek/issues/3153400
 */
class LingotekFieldOptionsTranslationTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['block', 'node', 'options', 'field_ui'];

  /**
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('page_title_block');

    // Create Article node types.
    $type = $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);
    $this->createOptionsField('list_string', 'article', 'field_options', 'Options');

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'options');
  }

  /**
   * Tests that a field can be translated.
   */
  public function testFieldTranslation() {
    $assert_session = $this->assertSession();
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->drupalGet('/admin/config/regional/config-translation');
    $this->drupalGet('/admin/config/regional/config-translation/node_fields');
    $this->clickLink(t('Translate'), 1);

    $this->clickLink(t('Upload'));
    $this->assertSession()->pageTextContains(t('Options uploaded successfully'));

    // Check that only the translatable fields have been uploaded.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    dump(var_export($data, TRUE));
    $this->assertTrue(array_key_exists('label', $data['field.field.node.article.field_options']));
    $this->assertTrue(array_key_exists('description', $data['field.field.node.article.field_options']));
    $this->assertTrue(array_key_exists('settings.allowed_values.0.label', $data['field.storage.node.field_options']));
    $this->assertTrue(array_key_exists('settings.allowed_values.1.label', $data['field.storage.node.field_options']));

    $this->assertEquals('Options', $data['field.field.node.article.field_options']['label']);
    $this->assertEquals('Zero', $data['field.storage.node.field_options']['settings.allowed_values.0.label']);
    $this->assertEquals('One', $data['field.storage.node.field_options']['settings.allowed_values.1.label']);

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertSame('automatic', $used_profile, 'The automatic profile was used.');

    $this->clickLink(t('Check upload status'));
    $this->assertSession()->pageTextContains('Options status checked successfully');

    $this->clickLink(t('Request translation'));
    $this->assertSession()->pageTextContains(t('Translation to es_MX requested successfully'));
    $this->assertSame('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    $this->clickLink(t('Check Download'));
    $this->assertSession()->pageTextContains(t('Translation to es_MX status checked successfully'));

    $this->clickLink('Download');
    $this->assertSession()->pageTextContains(t('Translation to es_MX downloaded successfully'));

    // Check that the edit link is there.
    $basepath = \Drupal::request()->getBasePath();
    $assert_session->linkByHrefExists($basepath . '/admin/structure/types/manage/article/fields/node.article.field_options/translate/es/edit');

    // Check that the values are correct.
    // In Drupal 9.4 blocks have been moved to content from sidebar_left,
    // and this alters the order. See https://www.drupal.org/project/drupal/issues/3257407.
    $index = version_compare(\Drupal::VERSION, '9.4', '>=') ? 2 : 1;
    $this->clickLink('Edit', $index);
    $this->assertSession()->fieldValueEquals('translation[config_names][field.field.node.article.field_options][label]', 'Opciones');
    $this->assertSession()->fieldValueEquals('translation[config_names][field.field.node.article.field_options][description]', 'Descripción del campo');
    $this->assertSession()->fieldValueEquals('translation[config_names][field.storage.node.field_options][settings][allowed_values][0][label]', 'Cero');
    $this->assertSession()->fieldValueEquals('translation[config_names][field.storage.node.field_options][settings][allowed_values][1][label]', 'Uno');
  }

  /**
   * Helper function to create list field of a given type.
   *
   * @param string $type
   *   One of 'list_integer', 'list_float' or 'list_string'.
   */
  protected function createOptionsField($type, $bundle, $field_name, $label) {
    // Create a field.
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'type' => $type,
    ])->save();
    FieldConfig::create([
      'field_name' => $field_name,
      'label' => $label,
      'entity_type' => 'node',
      'bundle' => $bundle,
    ])->save();

    EntityFormDisplay::load('node.' . $bundle . '.default')
      ->setComponent($field_name)
      ->save();

    $adminPath = 'admin/structure/types/manage/' . $bundle . '/fields/node.' . $bundle . '.' . $field_name . '/storage';
    $input_string = "zero|Zero\none|One";

    $edit = ['settings[allowed_values]' => $input_string];
    $this->drupalGet($adminPath);
    $this->submitForm($edit, t('Save field settings'));
  }

}
