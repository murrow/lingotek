<?php

namespace Drupal\Tests\lingotek\Functional\Form;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\Tests\lingotek\Functional\LingotekTestBase;

/**
 * Tests the Lingotek content settings form.
 *
 * @group lingotek
 * @group legacy
 * TODO: Remove legacy group when 8.8.x is not supported.
 * @see https://www.drupal.org/project/lingotek/issues/3153400
 */
class LingotekSettingsTabContentFormTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['block', 'node', 'field_ui', 'image'];

  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('page_title_block', [
      'region' => 'content',
      'weight' => -5,
    ]);
    $this->drupalPlaceBlock('local_actions_block', [
      'region' => 'content',
      'weight' => -10,
    ]);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')
      ->setThirdPartySetting('lingotek', 'locale', 'es_MX')
      ->save();

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create Article node types.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

    $this->createImageField('field_image', 'article');
    $this->createImageField('user_picture', 'user', 'user');
  }

  /**
   * Test that if there are no entities, there is a proper feedback to the user.
   */
  public function testNoUntranslatableEntitiesAreShown() {
    $this->drupalGet('admin/lingotek/settings');
    $this->assertSession()->pageTextContains('There are no translatable content entities specified');
  }

  /**
   * Test that we can configure entities at the subfield level.
   */
  public function testConfigureTranslatableEntityWithFieldsAndSubfields() {
    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);
    ContentLanguageSettings::loadByEntityTypeBundle('user', 'user')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('user', 'user', TRUE);

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->applyEntityUpdates();

    // Check the form contains the article type and only its text-based fields.
    $this->drupalGet('admin/lingotek/settings');
    $this->assertSession()->pageTextNotContains('There are no translatable content types specified');
    $this->assertSession()->fieldNotExists('node[article][fields][langcode]');
    $this->assertSession()->fieldExists('node[article][enabled]');
    $this->assertSession()->fieldExists('node[article][profiles]');
    $this->assertSession()->fieldExists('node[article][fields][title]');
    $this->assertSession()->fieldExists('node[article][fields][body]');

    // Check the title and body fields.
    $edit = [
      'node[article][enabled]' => 1,
      'node[article][profiles]' => 'automatic',
      'node[article][fields][title]' => 1,
      'node[article][fields][body]' => 1,
      'node[article][fields][field_image]' => 1,
      'node[article][fields][field_image:properties][alt]' => 'alt',
      'user[user][enabled]' => 1,
      'user[user][fields][user_picture]' => 1,
      'user[user][fields][user_picture:properties][alt]' => 'alt',
    ];
    $this->submitForm($edit, 'Save', 'lingoteksettings-tab-content-form');

    // Check that values are kept in the form.
    $this->assertSession()->checkboxChecked('edit-node-article-enabled');
    $this->assertSession()->fieldValueEquals('node[article][profiles]', 'automatic');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-title');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-body');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-field-image');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-field-imageproperties-alt');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-title');

    // Check that the config is correctly saved.
    $config_data = $this->config('lingotek.settings')->getRawData();
    $this->assertTrue($config_data['translate']['entity']['node']['article']['enabled']);
    $this->assertTrue($config_data['translate']['entity']['node']['article']['field']['title']);
    $this->assertTrue($config_data['translate']['entity']['node']['article']['field']['body']);
    $this->assertTrue($config_data['translate']['entity']['node']['article']['field']['field_image']);
    // As the schema here is sequence:ignore, there is no boolean casting.
    $this->assertEquals($config_data['translate']['entity']['node']['article']['field']['field_image:properties']['alt'], '1');
    $this->assertEquals($config_data['translate']['entity']['node']['article']['field']['field_image:properties']['title'], '0');
    $this->assertFalse(array_key_exists('revision_log', $config_data['translate']['entity']['node']['article']['field']));
    $this->assertEquals('automatic', $config_data['translate']['entity']['node']['article']['profile']);
  }

  /**
   * Test that we can configure entities at the subfield level.
   */
  public function testDisableTranslatableEntity() {
    $this->testConfigureTranslatableEntityWithFieldsAndSubfields();

    // Uncheck the image alt property and enable title.
    $edit = [
      'node[article][enabled]' => 1,
      'node[article][profiles]' => 'automatic',
      'node[article][fields][title]' => 1,
      'node[article][fields][body]' => 1,
      'node[article][fields][field_image]' => 1,
      'node[article][fields][field_image:properties][alt]' => FALSE,
      'node[article][fields][field_image:properties][title]' => 'title',
      'user[user][enabled]' => 1,
      'user[user][fields][user_picture]' => 1,
      'user[user][fields][user_picture:properties][alt]' => FALSE,
      'user[user][fields][user_picture:properties][title]' => 'title',
    ];
    $this->submitForm($edit, 'Save', 'lingoteksettings-tab-content-form');

    // Check that values are kept in the form.
    $this->assertSession()->checkboxChecked('edit-node-article-enabled');
    $this->assertSession()->fieldValueEquals('node[article][profiles]', 'automatic');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-title');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-body');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-field-image');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-alt');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-field-imageproperties-title');

    // Check that the config is correctly saved.
    $config_data = $this->config('lingotek.settings')->getRawData();
    $this->assertTrue($config_data['translate']['entity']['node']['article']['enabled']);
    $this->assertTrue($config_data['translate']['entity']['node']['article']['field']['title']);
    $this->assertTrue($config_data['translate']['entity']['node']['article']['field']['body']);
    $this->assertTrue($config_data['translate']['entity']['node']['article']['field']['field_image']);
    // As the schema here is sequence:ignore, there is no boolean casting.
    $this->assertEquals($config_data['translate']['entity']['node']['article']['field']['field_image:properties']['alt'], '0');
    $this->assertEquals($config_data['translate']['entity']['node']['article']['field']['field_image:properties']['title'], '1');
    $this->assertFalse(array_key_exists('revision_log', $config_data['translate']['entity']['node']['article']['field']));
    $this->assertEquals('automatic', $config_data['translate']['entity']['node']['article']['profile']);

    // Uncheck a couple of fields: body and image from node.
    $edit = [
      'node[article][enabled]' => 1,
      'node[article][profiles]' => 'automatic',
      'node[article][fields][title]' => 1,
      'node[article][fields][body]' => FALSE,
      'node[article][fields][field_image]' => FALSE,
      'node[article][fields][field_image:properties][alt]' => FALSE,
      'node[article][fields][field_image:properties][title]' => 'title',
      'user[user][enabled]' => 1,
      'user[user][fields][user_picture]' => 1,
      'user[user][fields][user_picture:properties][alt]' => FALSE,
      'user[user][fields][user_picture:properties][title]' => 'title',
    ];
    $this->submitForm($edit, 'Save', 'lingoteksettings-tab-content-form');

    // Check that values are kept in the form.
    $this->assertSession()->checkboxChecked('edit-node-article-enabled');
    $this->assertSession()->fieldValueEquals('node[article][profiles]', 'automatic');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-title');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-body');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-image');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-alt');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-title');

    // Check that the config is correctly saved.
    $config_data = $this->config('lingotek.settings')->getRawData();
    $this->assertTrue($config_data['translate']['entity']['node']['article']['enabled']);
    $this->assertTrue($config_data['translate']['entity']['node']['article']['field']['title']);
    $this->assertFalse(array_key_exists('body', $config_data['translate']['entity']['node']['article']['field']));
    $this->assertFalse(array_key_exists('field_image', $config_data['translate']['entity']['node']['article']['field']));
    // As the schema here is sequence:ignore, there is no boolean casting.
    // This should probably just be deleted.
    $this->assertFalse(array_key_exists('alt', $config_data['translate']['entity']['node']['article']['field']['field_image:properties']));
    $this->assertEquals($config_data['translate']['entity']['node']['article']['field']['field_image:properties']['title'], '0');
    $this->assertFalse(array_key_exists('revision_log', $config_data['translate']['entity']['node']['article']['field']));
    $this->assertEquals('automatic', $config_data['translate']['entity']['node']['article']['profile']);

    // Uncheck user for translation.
    $edit = [
      'node[article][enabled]' => 1,
      'node[article][profiles]' => 'automatic',
      'node[article][fields][title]' => 1,
      'node[article][fields][body]' => FALSE,
      'node[article][fields][field_image]' => FALSE,
      'node[article][fields][field_image:properties][alt]' => FALSE,
      'node[article][fields][field_image:properties][title]' => 'title',
      'user[user][enabled]' => FALSE,
    ];
    $this->submitForm($edit, 'Save', 'lingoteksettings-tab-content-form');

    // Check that values are kept in the form.
    $this->assertSession()->checkboxChecked('edit-node-article-enabled');
    $this->assertSession()->fieldValueEquals('node[article][profiles]', 'automatic');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-title');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-body');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-image');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-alt');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-title');

    $this->assertSession()->checkboxNotChecked('edit-user-user-enabled');
    $this->assertSession()->fieldValueEquals('user[user][profiles]', 'automatic');
    $this->assertSession()->checkboxChecked('edit-user-user-fields-user-picture');
    $this->assertSession()->checkboxChecked('edit-user-user-fields-user-pictureproperties-title');

    // Check that the config is correctly saved.
    $config_data = $this->config('lingotek.settings')->getRawData();
    $this->assertTrue($config_data['translate']['entity']['node']['article']['enabled']);
    $this->assertTrue($config_data['translate']['entity']['node']['article']['field']['title']);
    $this->assertFalse(array_key_exists('body', $config_data['translate']['entity']['node']['article']['field']));
    $this->assertFalse(array_key_exists('field_image', $config_data['translate']['entity']['node']['article']['field']));
    // As the schema here is sequence:ignore, there is no boolean casting.
    // This should probably just be deleted.
    $this->assertFalse(array_key_exists('alt', $config_data['translate']['entity']['node']['article']['field']['field_image:properties']));
    $this->assertEquals($config_data['translate']['entity']['node']['article']['field']['field_image:properties']['title'], '0');
    $this->assertFalse(array_key_exists('revision_log', $config_data['translate']['entity']['node']['article']['field']));
    $this->assertEquals('automatic', $config_data['translate']['entity']['node']['article']['profile']);

    $this->assertFalse($config_data['translate']['entity']['user']['user']['enabled']);
    $this->assertTrue($config_data['translate']['entity']['user']['user']['field']['user_picture']);
    $this->assertEquals($config_data['translate']['entity']['user']['user']['field']['user_picture:properties']['title'], '1');
  }

  public function testICanDisableFields() {
    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->applyEntityUpdates();

    // Check the form contains the article type and only its text-based fields.
    $this->drupalGet('admin/lingotek/settings');
    // Check the title and body fields.
    $edit = [
      'node[article][enabled]' => 1,
      'node[article][profiles]' => 'automatic',
      'node[article][fields][title]' => 1,
      'node[article][fields][body]' => 1,
      'node[article][fields][field_image]' => 1,
      'node[article][fields][field_image:properties][alt]' => 'alt',
    ];
    $this->submitForm($edit, 'Save', 'lingoteksettings-tab-content-form');
    // Assert that body translation is enabled.
    $this->assertSession()->checkboxChecked('edit-node-article-fields-body');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-field-imageproperties-alt');

    // Submit again unchecking body and image including subfields.
    $edit = [
      'node[article][enabled]' => 1,
      'node[article][profiles]' => 'automatic',
      'node[article][fields][title]' => 1,
      'node[article][fields][body]' => FALSE,
      'node[article][fields][field_image]' => FALSE,
      'node[article][fields][field_image:properties][alt]' => FALSE,
    ];
    $this->submitForm($edit, 'Save', 'lingoteksettings-tab-content-form');

    // Those checkboxes should not be checked anymore.
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-body');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-alt');

  }

  /**
   * Test that if we disable content translation for an entity or an entity
   * field, they are disabled for Lingotek.
   *
   * @throws \Exception
   */
  public function testFieldsAreDisabledInLingotekIfFieldsAreMarkedAsNotTranslatable() {
    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->applyEntityUpdates();

    // Check the form contains the article type and only its text-based fields.
    $this->drupalGet('admin/lingotek/settings');
    // Check the title and body fields.
    $edit = [
      'node[article][enabled]' => 1,
      'node[article][profiles]' => 'automatic',
      'node[article][fields][title]' => 1,
      'node[article][fields][body]' => 1,
      'node[article][fields][field_image]' => 1,
      'node[article][fields][field_image:properties][alt]' => 'alt',
    ];
    $this->submitForm($edit, 'Save', 'lingoteksettings-tab-content-form');
    // Assert that body translation is enabled.
    $this->assertSession()->checkboxChecked('edit-node-article-fields-title', 'The title field is enabled after enabled for Lingotek translation');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-body', 'The body field is enabled after enabled for Lingotek translation');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-field-image', 'The image field is enabled after enabled for Lingotek translation');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-field-imageproperties-alt', 'The image alt property is enabled after enabled for Lingotek translation');

    // Go to the content language settings, and disable the body field.
    // It should result that the field is disabled in Lingotek too.
    $edit = [
      'entity_types[node]' => TRUE,
      'settings[node][article][settings][language][language_alterable]' => TRUE,
      'settings[node][article][translatable]' => TRUE,
      'settings[node][article][fields][title]' => TRUE,
      'settings[node][article][fields][body]' => FALSE,
      'settings[node][article][fields][field_image]' => FALSE,
    ];
    $this->drupalGet('admin/config/regional/content-language');
    $this->submitForm($edit, t('Save configuration'));

    // Get the form and check the fields are not available, because they cannot be translated.
    $this->drupalGet('admin/lingotek/settings');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-title', 'The title field is enabled after other fields were disabled for content translation');
    $this->assertSession()->fieldValueNotEquals('edit-node-article-fields-body', 'The body field is not present after disabled for content translation');
    $this->assertSession()->fieldValueNotEquals('edit-node-article-fields-field-image', 'The image field is not present after disabled for content translation');
    $this->assertSession()->fieldValueNotEquals('edit-node-article-fields-field-imageproperties-alt', 'The image alt property is not present after image was disabled for content translation');

    // But also check that the fields are not enabled.
    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_config */
    $lingotek_config = \Drupal::service('lingotek.configuration');
    $this->assertFalse($lingotek_config->isFieldLingotekEnabled('node', 'article', 'body'), 'The body field is disabled after being disabled for content translation');
    $this->assertFalse($lingotek_config->isFieldLingotekEnabled('node', 'article', 'image'), 'The image field is disabled after being disabled for content translation');

    // And if we disable the entity itself, it should not be enabled anymore.
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', FALSE);
    $this->assertFalse($lingotek_config->isEnabled('node', 'article'), 'The article entity is disabled after being disabled for content translation');
    $this->assertFalse($lingotek_config->isFieldLingotekEnabled('node', 'article', 'title'), 'The title field is disabled after the entity being disabled for content translation');
  }

  public function testFieldsAreNotAvailableIfTranslatableEvenIfStorageIsTranslatable() {
    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->applyEntityUpdates();

    // Ensure field storage is translatable.
    $field_storage = FieldStorageConfig::loadByName('node', 'field_image');
    $field_storage->setTranslatable(TRUE)->save();

    // Ensure field instance is not translatable.
    $field = FieldConfig::loadByName('node', 'article', 'field_image');
    $field->setTranslatable(FALSE)->save();

    // Ensure changes were saved correctly.
    $field_storage = FieldStorageConfig::loadByName('node', 'field_image');
    $field = FieldConfig::loadByName('node', 'article', 'field_image');
    $this->assertTrue($field_storage->isTranslatable(), 'Field storage is translatable.');
    $this->assertFalse($field->isTranslatable(), 'Field instance is not translatable.');

    // Get the form and check the field is not available, even if the storage
    // is translatable.
    $this->drupalGet('admin/lingotek/settings');
    $this->assertSession()->fieldValueNotEquals('edit-node-article-fields-field-image', '');

    // Make the field translatable again.
    $field->setTranslatable(TRUE)->save();

    // If the field is translatable, the field is available again.
    $this->drupalGet('admin/lingotek/settings');
    $this->assertSession()->fieldValueEquals('edit-node-article-fields-field-image', '');
  }

  public function testAddContentTypeAndConfigureLingotekToTranslate() {
    // Check the form contains the article type and only its text-based fields.
    $this->drupalGet('admin/structure/types');
    $this->clickLink('Add content type');
    $this->assertSession()->checkboxNotChecked('language_configuration[language_alterable]');
    $this->assertSession()->checkboxNotChecked('language_configuration[content_translation]');
    $this->assertSession()->checkboxNotChecked('language_configuration[content_translation_for_lingotek]');

    $edit = [
      'name' => 'Test',
      'type' => 'test',
      'language_configuration[language_alterable]' => TRUE,
      'language_configuration[content_translation]' => TRUE,
      'language_configuration[content_translation_for_lingotek]' => TRUE,
    ];
    $this->submitForm($edit, 'Save and manage fields');
    $this->assertSession()->pageTextContains('The content type Test has been added.');

    // It should result that the field is enabled in Lingotek settings.
    $this->drupalGet('admin/lingotek/settings');
    $this->assertSession()->checkboxChecked('edit-node-test-enabled');

    // We automatically enabled sensible defaults fields according to their type.
    $this->assertSession()->checkboxChecked('edit-node-test-fields-title');
    $this->assertSession()->checkboxChecked('edit-node-test-fields-body');
    $this->assertSession()->checkboxNotChecked('edit-node-test-fields-uid');
  }

  /**
   * Tests the "Use Lingotek To Translate" option when editing a content type.
   */
  public function testEditContentTypeAndUseLingotekToTranslate() {
    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->applyEntityUpdates();

    // Check the form contains the article type and only its text-based fields.
    $this->drupalGet('admin/structure/types/manage/article');

    // It should result that the field is disabled in Lingotek.
    $edit = [
      'language_configuration[language_alterable]' => FALSE,
      'language_configuration[content_translation]' => FALSE,
      'language_configuration[content_translation_for_lingotek]' => FALSE,
    ];
    $this->drupalGet('admin/structure/types/manage/article');
    $this->submitForm($edit, t('Save content type'));

    // Check the form contains the article type and only its text-based fields.
    $this->drupalGet('admin/structure/types/manage/article');

    $this->drupalGet('admin/lingotek/settings');
    $this->assertSession()->pageTextContains('There are no translatable content entities specified');

    // It should result that the field is enabled in Lingotek.
    $edit = [
      'language_configuration[language_alterable]' => TRUE,
      'language_configuration[content_translation]' => TRUE,
      'language_configuration[content_translation_for_lingotek]' => TRUE,
    ];
    $this->drupalGet('admin/structure/types/manage/article');
    $this->submitForm($edit, t('Save content type'));
    $this->applyEntityUpdates();

    // Check the form contains the article type and only its text-based fields.
    $this->drupalGet('admin/structure/types/manage/article');

    $this->drupalGet('admin/lingotek/settings');
    $this->assertSession()->checkboxChecked('edit-node-article-enabled');

    // We automatically enabled sensible defaults fields according to their type.
    $this->assertSession()->checkboxChecked('edit-node-article-fields-title');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-body');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-uid');
  }

  /**
   * Tests the "Use Lingotek To Translate" option when creating a new body field.
   */
  public function testCreateFieldAndUseLingotekToTranslateWithBodyField() {
    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')
      ->setLanguageAlterable(TRUE)
      ->save();
    \Drupal::service('content_translation.manager')
      ->setEnabled('node', 'article', TRUE);

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->applyEntityUpdates();

    $this->drupalGet('admin/lingotek/settings');

    // Check the form contains the fields but they are disabled.
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-body');

    // Check body field.
    $edit = [
      'node[article][enabled]' => 1,
      'node[article][profiles]' => 'automatic',
      'node[article][fields][body]' => 1,
    ];
    $this->submitForm($edit, 'Save', 'lingoteksettings-tab-content-form');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-body');

    $this->drupalGet('/admin/config/regional/config-translation/node_fields');
    $this->clickLink(t('Translate'), 0);
    $this->clickLink('Edit');

    $this->assertSession()->checkboxChecked('edit-translatable-for-lingotek');
    // There are no properties to show.
    $this->assertSession()->responseNotContains('Lingotek translation');

    $edit = [
      'translatable_for_lingotek' => FALSE,
    ];
    $this->submitForm($edit, 'Save settings');
    $this->clickLink('Edit');

    $this->assertSession()->checkboxNotChecked('edit-translatable-for-lingotek');

    $this->drupalGet('/admin/lingotek/settings');

    $this->assertSession()->checkboxNotChecked("edit-node-article-fields-body");

    $this->drupalGet('/admin/config/regional/config-translation/node_fields');
    $this->clickLink(t('Translate'), 0);
    $this->clickLink('Edit');

    $edit = [
      'translatable_for_lingotek' => 1,
    ];
    $this->submitForm($edit, 'Save settings');
    $this->clickLink('Edit');

    $this->assertSession()->checkboxChecked('edit-translatable-for-lingotek');

    $this->drupalGet('/admin/lingotek/settings');
    $this->assertSession()->checkboxChecked("edit-node-article-fields-body");
  }

  /**
   * Tests the "Use Lingotek To Translate" option when creating a new image field with properties
   */
  public function testCreateFieldAndUseLingotekToTranslateWithImageProperties() {
    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')
      ->setLanguageAlterable(TRUE)
      ->save();
    \Drupal::service('content_translation.manager')
      ->setEnabled('node', 'article', TRUE);

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->applyEntityUpdates();

    $this->drupalGet('admin/lingotek/settings');

    // Check the form contains the fields but they are disabled.
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-body');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-image');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-alt');

    // Check image and alt subfield.
    $edit = [
      'node[article][enabled]' => 1,
      'node[article][profiles]' => 'automatic',
      'node[article][fields][title]' => 1,
      'node[article][fields][body]' => 1,
      'node[article][fields][field_image]' => 1,
      'node[article][fields][field_image:properties][alt]' => 'alt',
    ];
    $this->submitForm($edit, 'Save', 'lingoteksettings-tab-content-form');

    $this->assertSession()->checkboxChecked('edit-node-article-fields-body');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-field-image');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-file');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-field-imageproperties-alt');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-title');

    // Submit again unchecking image including subfields.
    $edit = [
      'node[article][fields][field_image]' => FALSE,
      'node[article][fields][field_image:properties][alt]' => FALSE,
    ];
    $this->submitForm($edit, 'Save', 'lingoteksettings-tab-content-form');

    // Those checkboxes should not be checked anymore.
    $this->assertSession()->checkboxChecked('edit-node-article-fields-body');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-image');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-file');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-alt');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-title');

    $this->drupalGet('/admin/config/regional/config-translation/node_fields');
    $this->clickLink(t('Translate'), 1);
    $this->clickLink('Edit');

    $this->assertSession()->checkboxNotChecked('edit-translatable-for-lingotek');
    $this->assertSession()->checkboxNotChecked('edit-third-party-settings-content-translation-translation-sync-file');
    $this->assertSession()->checkboxChecked('edit-third-party-settings-content-translation-translation-sync-alt');
    $this->assertSession()->checkboxChecked('edit-third-party-settings-content-translation-translation-sync-title');
    $this->assertSession()->checkboxNotChecked('edit-translatable-for-lingotek-properties-file');
    $this->assertSession()->checkboxNotChecked('edit-translatable-for-lingotek-properties-alt');
    $this->assertSession()->checkboxNotChecked('edit-translatable-for-lingotek-properties-title');

    $edit = [
      'translatable_for_lingotek' => 1,
      'third_party_settings[content_translation][translation_sync][alt]' => 'alt',
      'translatable_for_lingotek_properties_alt' => 1,
    ];
    $this->submitForm($edit, 'Save settings');
    $this->clickLink('Edit');

    $this->assertSession()->checkboxChecked('edit-translatable-for-lingotek');
    $this->assertSession()->checkboxNotChecked('edit-third-party-settings-content-translation-translation-sync-file');
    $this->assertSession()->checkboxChecked('edit-third-party-settings-content-translation-translation-sync-alt');
    $this->assertSession()->checkboxChecked('edit-third-party-settings-content-translation-translation-sync-title');
    $this->assertSession()->checkboxNotChecked('edit-translatable-for-lingotek-properties-file');
    $this->assertSession()->checkboxChecked('edit-translatable-for-lingotek-properties-alt');
    $this->assertSession()->checkboxNotChecked('edit-translatable-for-lingotek-properties-title');

    $this->drupalGet('/admin/lingotek/settings');

    $this->assertSession()->checkboxChecked("edit-node-article-fields-field-image");
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-file');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-field-imageproperties-alt');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-title');

    $edit = [
      'node[article][fields][field_image]' => 1,
      'node[article][fields][field_image:properties][file]' => FALSE,
      'node[article][fields][field_image:properties][alt]' => FALSE,
      'node[article][fields][field_image:properties][title]' => FALSE,
    ];
    $this->submitForm($edit, 'Save', 'lingoteksettings-tab-content-form');

    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-file');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-alt');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-title');

    $this->drupalGet('/admin/config/regional/config-translation/node_fields');
    $this->clickLink(t('Translate'), 1);
    $this->clickLink('Edit');

    $edit = [
      'translatable_for_lingotek' => 1,
      'third_party_settings[content_translation][translation_sync][file]' => 'file',
      'third_party_settings[content_translation][translation_sync][alt]' => 'alt',
      'third_party_settings[content_translation][translation_sync][title]' => 'title',
      'translatable_for_lingotek_properties_alt' => 1,
      'translatable_for_lingotek_properties_title' => 1,
      'translatable_for_lingotek_properties_file' => 1,
    ];
    $this->submitForm($edit, 'Save settings');
    $this->clickLink('Edit');

    $this->assertSession()->checkboxChecked('edit-translatable-for-lingotek');
    $this->assertSession()->checkboxChecked('edit-third-party-settings-content-translation-translation-sync-file');
    $this->assertSession()->checkboxChecked('edit-third-party-settings-content-translation-translation-sync-alt');
    $this->assertSession()->checkboxChecked('edit-third-party-settings-content-translation-translation-sync-title');
    $this->assertSession()->checkboxChecked('edit-translatable-for-lingotek-properties-file');
    $this->assertSession()->checkboxChecked('edit-translatable-for-lingotek-properties-alt');
    $this->assertSession()->checkboxChecked('edit-translatable-for-lingotek-properties-title');

    $this->drupalGet('/admin/lingotek/settings');

    $this->assertSession()->checkboxChecked("edit-node-article-fields-field-image");
    $this->assertSession()->checkboxChecked('edit-node-article-fields-field-imageproperties-file');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-field-imageproperties-alt');
    $this->assertSession()->checkboxChecked('edit-node-article-fields-field-imageproperties-title');

    $edit = [
      'node[article][fields][field_image]' => 1,
      'node[article][fields][field_image:properties][file]' => FALSE,
      'node[article][fields][field_image:properties][alt]' => FALSE,
      'node[article][fields][field_image:properties][title]' => FALSE,
    ];
    $this->submitForm($edit, 'Save', 'lingoteksettings-tab-content-form');

    $this->assertSession()->checkboxChecked("edit-node-article-fields-field-image");
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-file');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-alt');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-title');

    $this->drupalGet('/admin/config/regional/config-translation/node_fields');
    $this->clickLink(t('Translate'), 1);
    $this->clickLink('Edit');

    $edit = [
      'translatable_for_lingotek' => 1,
      'third_party_settings[content_translation][translation_sync][alt]' => FALSE,
      'third_party_settings[content_translation][translation_sync][title]' => FALSE,
      'third_party_settings[content_translation][translation_sync][file]' => FALSE,
      'translatable_for_lingotek_properties_alt' => 1,
      'translatable_for_lingotek_properties_title' => 1,
      'translatable_for_lingotek_properties_file' => 1,
    ];
    $this->submitForm($edit, 'Save settings');
    $this->clickLink('Edit');

    $this->assertSession()->checkboxChecked('edit-translatable-for-lingotek');
    $this->assertSession()->checkboxNotChecked('edit-third-party-settings-content-translation-translation-sync-file');
    $this->assertSession()->checkboxNotChecked('edit-third-party-settings-content-translation-translation-sync-alt');
    $this->assertSession()->checkboxNotChecked('edit-third-party-settings-content-translation-translation-sync-title');
    $this->assertSession()->checkboxNotChecked('edit-translatable-for-lingotek-properties-file');
    $this->assertSession()->checkboxNotChecked('edit-translatable-for-lingotek-properties-alt');
    $this->assertSession()->checkboxNotChecked('edit-translatable-for-lingotek-properties-title');

    $this->drupalGet('/admin/lingotek/settings');

    $this->assertSession()->checkboxChecked("edit-node-article-fields-field-image");
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-alt');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-title');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-file');

    $edit = [
      'node[article][fields][field_image]' => 1,
      'node[article][fields][field_image:properties][alt]' => FALSE,
      'node[article][fields][field_image:properties][title]' => FALSE,
      'node[article][fields][field_image:properties][file]' => FALSE,
    ];
    $this->submitForm($edit, 'Save', 'lingoteksettings-tab-content-form');

    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-alt');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-title');
    $this->assertSession()->checkboxNotChecked('edit-node-article-fields-field-imageproperties-file');
  }

  public function testContentTypesAreNotDisabledIfThereAreLotsOfContentTypes() {
    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')
      ->setLanguageAlterable(TRUE)
      ->save();
    \Drupal::service('content_translation.manager')
      ->setEnabled('node', 'article', TRUE);

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->saveLingotekContentTranslationSettingsForNodeTypes();

    foreach (range(1, 150) as $i) {
      $this->drupalCreateContentType([
        'type' => 'content_type_' . $i,
        'name' => 'Content Type ' . $i,
      ]);
      ContentLanguageSettings::loadByEntityTypeBundle('node', 'content_type_' . $i)
        ->setLanguageAlterable(TRUE)
        ->save();
      \Drupal::service('content_translation.manager')
        ->setEnabled('node', 'content_type_' . $i, TRUE);
    }

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')
      ->setLanguageAlterable(TRUE)
      ->save();
    \Drupal::service('content_translation.manager')
      ->setEnabled('node', 'article', TRUE);

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->drupalGet('admin/lingotek/settings');

    // Check the form contains the fields, and have the proper values,
    // but they are disabled.
    $this->assertSession()->checkboxChecked('edit-node-article-readonly-enabled');
    $this->assertSession()->fieldValueEquals('node[article][profiles]', 'automatic');

    $edit = ['node[article][profiles]' => 'manual'];
    $this->submitForm($edit, 'Save', 'lingoteksettings-tab-content-form');

    // Check the form contains the fields, and have the proper values,
    // but they are disabled.
    $this->assertSession()->checkboxChecked('edit-node-article-readonly-enabled');
    $this->assertSession()->fieldValueEquals('node[article][profiles]', 'manual');
  }

}
