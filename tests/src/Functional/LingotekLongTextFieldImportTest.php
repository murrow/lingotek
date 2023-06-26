<?php

namespace Drupal\Tests\lingotek\Functional;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
/**
 * Tests whether long text field format is correct for an imported translation
 *
 * @group lingotek
 */
class LingotekLongTextFieldImportTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['block', 'node', 'field_ui'];

  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('page_title_block', ['region' => 'content', 'weight' => -5]);
    $this->drupalPlaceBlock('local_tasks_block', ['region' => 'content', 'weight' => -10]);

    // Create Article node types.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    // Add a new field for Articles.
    $this->addNewField('node', 'article', 'long_text', 'Long_Text');

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

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();

    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->saveLingotekContentTranslationSettings([
      'node' => [
        'article' => [
          'profiles' => 'automatic',
          'fields' => [
            'title' => 1,
            'body' => 1,
            'long_text' => 1,
          ],
        ],
      ],
    ]);
  }

  /**
   * Tests that a translated node's long text field has a proper non-null format after import 
   */
  public function testTranslatedLongTextFieldFormat() {

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

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 3);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEquals(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));

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

    // Open the node and it works.
    $this->clickLink('Llamas are cool');
    $this->clickLink('Translate');
    $this->clickLink('Las llamas son chulas');
    $this->assertSession()->pageTextContains('Las llamas son muy chulas');

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+long_text');

    // Insert html text in the long text field
    $this->goToContentBulkManagementForm();
    $edit =[];
    $edit['long_text[0][value]'] = '<p class="text-align-center"><span class="thsplay-1"><span class="text-white">Hello test text!</span> </span></p>';
    $this->saveAndKeepPublishedNodeForm($edit,1);

    //Reupload and import translation
    $this->clickLink('Manage Translations');
    $this->clickLink('Re-upload (content has changed since last upload)');
    $this->clickLink('Source importing');
    $this->clickLink('Spanish - In-progress');
    $this->clickLink('Spanish - Ready for Download');


    $this->drupalGet('es/node/1');
    $result = \Drupal::database()->query('SELECT * FROM {node__long_text}')->fetchAll();
    $this->assertEquals(count($result),2);
    $sourceResult = json_decode(json_encode($result[0]), true);
    $targetResult = json_decode(json_encode($result[1]), true);
    $this->assertEquals($sourceResult['long_text_format'],'plain_text');
    $this->assertEquals($targetResult['long_text_format'],'plain_text');

  }


  /**
   * Adds a new text field.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The bundle.
   * @param string $field_name
   *   The field name.
   * @param string $label
   *   The label.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The field config.
   */
  protected function addNewField($entity_type_id, $bundle, $field_name, $label) {
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type_id,
      'type' => 'text_long',
      'translatable' => TRUE,
      'format' => 'basic_html',
      'description' => [
        'format' => 'basic_html',
      ]
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $bundle,
      'label' => $label,
      'format' => 'basic_html',
      'settings' => [
        'display_summary' => TRUE,
        'format' => 'basic_html',
      ],
    ]);
    $field->save();

    // Assign widget settings for the 'default' form mode.
    EntityFormDisplay::load($entity_type_id . '.' . $bundle . '.' . 'default')
      ->setComponent($field_name, [
        'type' => 'text_textarea_with_summary',
      ])
      ->save();
    EntityViewDisplay::load($entity_type_id . '.' . $bundle . '.' . 'default')
      ->setComponent($field_name, [
        'label' => 'hidden',
        'type' => 'text_default',
      ])
      ->save();

    // The teaser view mode is created by the Standard profile and therefore
    // might not exist.
    $view_modes = \Drupal::service('entity_display.repository')->getViewModes($entity_type_id);
    if (isset($view_modes['teaser'])) {
      EntityViewDisplay::load($entity_type_id . '.' . $bundle . '.' . 'default')
        ->setComponent($field_name, [
          'label' => 'hidden',
          'type' => 'text_summary_or_trimmed',
        ])
        ->save();
    }
    return $field;
  }

}
