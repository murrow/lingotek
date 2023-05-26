<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Entity\LingotekConfigMetadata;
use Drupal\node\Entity\Node;

/**
 * Tests translating a node that contains a block field.
 *
 * @group lingotek
 */
class LingotekNodeWithBlockfieldTranslationTest extends LingotekTestBase {

  use EntityReferenceTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'node', 'dblog', 'block_content', 'block_field', 'frozenintime'];

  /**
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('page_title_block', ['region' => 'content', 'weight' => -5]);
    $this->drupalPlaceBlock('local_tasks_block', ['region' => 'content', 'weight' => -10]);

    // Create Article node types.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

    $bundle = BlockContentType::create([
      'id' => 'custom_content_block',
      'label' => 'Custom content block',
      'revision' => FALSE,
    ]);
    $bundle->save();

    block_content_add_body_field('custom_content_block');

    $fieldStorage = \Drupal::entityTypeManager()
      ->getStorage('field_storage_config')
      ->create([
        'field_name' => 'field_block',
        'entity_type' => 'node',
        'type' => 'block_field',
        'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      ]);
    $fieldStorage->save();
    $field = \Drupal::entityTypeManager()->getStorage('field_config')->create([
      'field_storage' => $fieldStorage,
      'bundle' => 'article',
    ]);
    $field->save();

    EntityFormDisplay::load('node.article.default')
      ->setComponent('field_block', [
        'type' => 'block_field_default',
        'settings' => [
          'configuration_form' => 'full',
        ],
      ])
      ->save();
    EntityViewDisplay::load('node.article.default')
      ->setComponent('field_block', [
        'type' => 'block_field',
      ])
      ->save();

    // Add locales.
    ConfigurableLanguage::createFromLangcode('es')
      ->setThirdPartySetting('lingotek', 'locale', 'es_ES')
      ->save();
    ConfigurableLanguage::createFromLangcode('es-ar')
      ->setThirdPartySetting('lingotek', 'locale', 'es_AR')
      ->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')
      ->setLanguageAlterable(TRUE)
      ->save();
    \Drupal::service('content_translation.manager')
      ->setEnabled('node', 'article', TRUE);

    ContentLanguageSettings::loadByEntityTypeBundle('block_content', 'custom_content_block')
      ->setLanguageAlterable(TRUE)
      ->save();
    \Drupal::service('content_translation.manager')
      ->setEnabled('block_content', 'custom_content_block', TRUE);

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
            'field_block' => 1,
          ],
        ],
      ],
      'block_content' => [
        'custom_content_block' => [
          'profiles' => 'manual',
          'fields' => [
            'body' => 1,
          ],
        ],
      ],

    ]);
  }

  /**
   * Tests that a node can be translated referencing a standard block.
   */
  public function testNodeWithConfigBlockTranslation() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+blockfield');

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['field_block[0][plugin_id]'] = 'current_theme_block';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $edit['langcode[0][value]'] = 'en';

    // Because we cannot do ajax requests in this test, we submit and edit later.
    $this->saveAndPublishNodeForm($edit);

    // Ensure it has the expected timestamp for updated and upload
    foreach (LingotekConfigMetadata::loadMultiple() as $metadata) {
      $this->assertEmpty($metadata->getLastUpdated());
      $this->assertEmpty($metadata->getLastUploaded());
    }

    $edit['field_block[0][settings][label_display]'] = TRUE;
    $edit['field_block[0][settings][label]'] = 'Current theme overridden title block';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndKeepPublishedNodeForm($edit, 1);

    $this->assertSession()->pageTextContains('Current theme overridden title block');
    $this->assertSession()->pageTextContains('Current theme: stark');

    // Ensure it has the expected timestamp for updated and upload
    $timestamp = \Drupal::time()->getRequestTime();
    foreach (LingotekConfigMetadata::loadMultiple() as $metadata) {
      $this->assertEmpty($metadata->getLastUpdated());
      $this->assertEquals($timestamp, $metadata->getLastUploaded());
    }

    $this->node = Node::load(1);

    // Check that only the configured fields have been uploaded, including field
    // block settings stored in the field.
    $data = json_decode(\Drupal::state()
      ->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 3);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEquals(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    $this->assertEquals(1, count($data['field_block'][0]));
    $this->assertEquals($data['field_block'][0]['label'], 'Current theme overridden title block');

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Check Upload Status');
    $this->assertSession()->pageTextContains('The import for node Llamas are cool is complete.');

    // Request translation.
    $link = $this->xpath('//a[normalize-space()="Request translation" and contains(@href,"es_AR")]');
    $link[0]->click();
    $this->assertSession()->pageTextContains("Locale 'es_AR' was added as a translation target for node Llamas are cool.");

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertSession()->pageTextContains('The es_AR translation for node Llamas are cool is ready for download.');

    // Check that the Edit link points to the workbench and it is opened in a new tab.
    $this->assertLingotekWorkbenchLink('es_AR', 'dummy-document-hash-id', 'Edit in Ray Enterprise Workbench');

    // Download translation.
    $this->clickLink('Download completed translation');
    $this->assertSession()->pageTextContains('The translation of node Llamas are cool into es_AR has been downloaded.');

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas');
    $this->assertSession()->pageTextContains('Las llamas son chulas');
    $this->assertSession()->pageTextContains('Las llamas son muy chulas');
    $this->assertSession()->pageTextContains('Tema actual titulo sobreescrito del bloque');
    $this->assertSession()->pageTextContains('Current theme: stark');
  }

  /**
   * Tests that a node can be translated referencing a standard block.
   */
  public function testNodeWithCustomConfigBlockTranslation() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+blockfieldcustom');

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['field_block[0][plugin_id]'] = 'lingotek_test_rich_text_block';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $edit['langcode[0][value]'] = 'en';

    // Because we cannot do ajax requests in this test, we submit and edit later.
    $this->saveAndPublishNodeForm($edit);

    $edit['field_block[0][settings][label_display]'] = TRUE;
    $edit['field_block[0][settings][label]'] = 'Custom block title';
    $edit['field_block[0][settings][rich_text][value]'] = 'Custom block body';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndKeepPublishedNodeForm($edit, 1);

    $this->assertSession()->pageTextContains('Custom block title');
    $this->assertSession()->pageTextContains('Custom block body');

    $this->node = Node::load(1);

    // Check that only the configured fields have been uploaded, including field
    // block settings stored in the field.
    $data = json_decode(\Drupal::state()
      ->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 3);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEquals(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    $this->assertEquals(2, count($data['field_block'][0]));
    $this->assertEquals($data['field_block'][0]['label'], 'Custom block title');
    $this->assertEquals($data['field_block'][0]['rich_text.value'], 'Custom block body');

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Check Upload Status');
    $this->assertSession()->pageTextContains('The import for node Llamas are cool is complete.');

    // Request translation.
    $link = $this->xpath('//a[normalize-space()="Request translation" and contains(@href,"es_AR")]');
    $link[0]->click();
    $this->assertSession()->pageTextContains("Locale 'es_AR' was added as a translation target for node Llamas are cool.");

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertSession()->pageTextContains('The es_AR translation for node Llamas are cool is ready for download.');

    // Check that the Edit link points to the workbench and it is opened in a new tab.
    $this->assertLingotekWorkbenchLink('es_AR', 'dummy-document-hash-id', 'Edit in Ray Enterprise Workbench');

    // Download translation.
    $this->clickLink('Download completed translation');
    $this->assertSession()->pageTextContains('The translation of node Llamas are cool into es_AR has been downloaded.');

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas');
    $this->assertSession()->pageTextContains('Las llamas son chulas');
    $this->assertSession()->pageTextContains('Las llamas son muy chulas');
    $this->assertSession()->pageTextContains('Título de bloque personalizado');
    $this->assertSession()->pageTextContains('Cuerpo de bloque personalizado');

    // The original content didn't change.
    $this->drupalGet('node/1');
    $this->assertSession()->pageTextContains('Llamas are cool');
    $this->assertSession()->pageTextContains('Llamas are very cool');
    $this->assertSession()->pageTextContains('Custom block title');
    $this->assertSession()->pageTextContains('Custom block body');
  }

  /**
   * Tests that a node can be translated referencing a content block.
   */
  public function testNodeWithContentBlockTranslation() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+contentblockfield');

    // Create a block.
    $edit = [];
    $edit['info[0][value]'] = 'Dogs block';
    $edit['body[0][value]'] = 'Dogs are very cool block';
    $this->drupalGet('block/add/custom_content_block');
    $this->submitForm($edit, t('Save'));

    $dogsBlock = BlockContent::load(1);

    $edit = [];
    $edit['info[0][value]'] = 'Cats block';
    $edit['body[0][value]'] = 'Cats are very cool block';
    $this->drupalGet('block/add/custom_content_block');
    $this->submitForm($edit, t('Save'));

    $catsBlock = BlockContent::load(2);

    // Create a node.
    $this->drupalGet('node/add/article');
    $this->submitForm([], 'Add another item');
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['field_block[0][plugin_id]'] = 'block_content:' . $dogsBlock->uuid();
    $edit['field_block[1][plugin_id]'] = 'block_content:' . $catsBlock->uuid();
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $edit['langcode[0][value]'] = 'en';

    // Because we cannot do ajax requests in this test, we submit and edit later.
    $this->saveAndPublishNodeForm($edit, NULL);

    $edit['field_block[0][settings][label_display]'] = TRUE;
    $edit['field_block[0][settings][label]'] = 'Dogs overridden title block';
    $edit['field_block[1][settings][label_display]'] = TRUE;
    $edit['field_block[1][settings][label]'] = 'Cats overridden title block';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndKeepPublishedNodeForm($edit, 1);

    $this->assertSession()->pageTextContains('Dogs overridden title block');
    $this->assertSession()->pageTextContains('Dogs are very cool block');
    $this->assertSession()->pageTextContains('Cats overridden title block');
    $this->assertSession()->pageTextContains('Cats are very cool block');

    $this->node = Node::load(1);

    // Check that only the configured fields have been uploaded, including field
    // block settings stored in the field.
    $data = json_decode(\Drupal::state()
      ->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 3);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEquals(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    $this->assertEquals(2, count($data['field_block']));
    $this->assertEquals(3, count($data['field_block'][0]));
    $this->assertEquals(3, count($data['field_block'][1]));
    $this->assertEquals($data['field_block'][0]['label'], 'Dogs overridden title block');
    $this->assertEquals($data['field_block'][0]['info'], '');
    $this->assertTrue(isset($data['field_block'][0]['entity']));
    $this->assertEquals($data['field_block'][0]['entity']['body'][0]['value'], 'Dogs are very cool block');
    $this->assertEquals($data['field_block'][0]['entity']['_lingotek_metadata']['_entity_type_id'], 'block_content');
    $this->assertEquals($data['field_block'][0]['entity']['_lingotek_metadata']['_entity_id'], '1');
    $this->assertEquals($data['field_block'][1]['label'], 'Cats overridden title block');
    $this->assertEquals($data['field_block'][1]['info'], '');
    $this->assertTrue(isset($data['field_block'][1]['entity']));
    $this->assertEquals($data['field_block'][1]['entity']['body'][0]['value'], 'Cats are very cool block');
    $this->assertEquals($data['field_block'][1]['entity']['_lingotek_metadata']['_entity_type_id'], 'block_content');
    $this->assertEquals($data['field_block'][1]['entity']['_lingotek_metadata']['_entity_id'], '2');

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Check Upload Status');
    $this->assertSession()->pageTextContains('The import for node Llamas are cool is complete.');

    // Request translation.
    $link = $this->xpath('//a[normalize-space()="Request translation" and contains(@href,"es_AR")]');
    $link[0]->click();
    $this->assertSession()->pageTextContains("Locale 'es_AR' was added as a translation target for node Llamas are cool.");

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertSession()->pageTextContains('The es_AR translation for node Llamas are cool is ready for download.');

    // Check that the Edit link points to the workbench and it is opened in a new tab.
    $this->assertLingotekWorkbenchLink('es_AR', 'dummy-document-hash-id', 'Edit in Ray Enterprise Workbench');

    // Download translation.
    $this->clickLink('Download completed translation');
    $this->assertSession()->pageTextContains('The translation of node Llamas are cool into es_AR has been downloaded.');

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas');
    $this->assertSession()->pageTextContains('Las llamas son chulas');
    $this->assertSession()->pageTextContains('Las llamas son muy chulas');
    $this->assertSession()->pageTextContains('Bloque sobreescrito con título Perros');
    $this->assertSession()->pageTextContains('Bloque Los perros son muy chulos');
    $this->assertSession()->pageTextContains('Bloque sobreescrito con título Gatos');
    $this->assertSession()->pageTextContains('Bloque Los gatos son muy chulos');
  }

}
