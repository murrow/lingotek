<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\contact\Entity\ContactForm;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\Entity\Node;

/**
 * Tests translating a node with multiple locales embedding another config entity.
 *
 * @group lingotek
 */
class LingotekNodeEmbeddingContactFormTranslationTest extends LingotekTestBase {

  use EntityReferenceTestTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['block', 'node', 'image', 'comment', 'contact'];

  /**
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * @var \Drupal\contact\Entity\ContactForm
   */
  protected $contactForm;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('page_title_block');

    // Create Article node types.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

    $this->contactForm = ContactForm::create([
      'id' => 'contact_form',
      'label' => 'Test contact form',
    ]);
    $this->contactForm->save();

    $this->createEntityReferenceField('node', 'article',
      'field_contact_form', 'Contact Form', 'contact_form');

    EntityFormDisplay::load('node.article.default')
      ->setComponent('field_contact_form', [
        'type' => 'entity_reference_autocomplete',
      ])
      ->save();
    EntityViewDisplay::load('node.article.default')
      ->setComponent('field_contact_form')
      ->save();

    // Add locales.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_AR')->save();
    ConfigurableLanguage::createFromLangcode('es-es')->setThirdPartySetting('lingotek', 'locale', 'es_ES')->save();

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

    $this->saveLingotekContentTranslationSettings([
      'node' => [
        'article' => [
          'profiles' => 'automatic',
          'fields' => [
            'title' => 1,
            'body' => 1,
            'field_contact_form' => 1,
          ],
        ],
      ],
    ]);

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+contact_form');
  }

  /**
   * Tests that a node can be translated.
   */
  public function testNodeTranslation() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_contact_form[0][target_id]'] = $this->contactForm->label() . ' (' . $this->contactForm->id() . ')';

    $this->saveAndPublishNodeForm($edit);

    $this->node = Node::load(1);

    // Check that only the configured fields have been uploaded, including tags.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 3);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEquals(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    $this->assertEquals(1, count($data['field_contact_form']));
    $this->assertEquals('Test contact form', $data['field_contact_form'][0]['label']);
    $this->assertEquals('', $data['field_contact_form'][0]['reply']);

    // Check that the url used was the right one.
    $uploaded_url = \Drupal::state()->get('lingotek.uploaded_url');
    $this->assertSame(\Drupal::request()->getUriForPath('/node/1'), $uploaded_url, 'The node url was used.');

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertSame('automatic', $used_profile, 'The automatic profile was used.');

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Check Upload Status');
    $this->assertSession()->pageTextContains('The import for node Llamas are cool is complete.');

    // Request translation.
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
    $this->assertSession()->pageTextContains('Formulario de Contacto');
  }

  /**
   * Tests that a node can be translated.
   */
  public function testNodeTranslationWithADeletedReference() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_contact_form[0][target_id]'] =
      $this->contactForm->label() . ' (' . $this->contactForm->id() . ')';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';

    $this->saveAndPublishNodeForm($edit);

    $this->node = Node::load(1);

    $this->contactForm->delete();

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextContains('Uploaded 1 document to Lingotek.');

    // Check that only the configured fields have been uploaded, including tags.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 2);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEquals(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    // The contact form is not there.
    $this->assertFalse(isset($data['field_contact_form']));

    // Check that the url used was the right one.
    $uploaded_url = \Drupal::state()->get('lingotek.uploaded_url');
    $this->assertSame(\Drupal::request()->getUriForPath('/node/1'), $uploaded_url, 'The node url was used.');

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertSame('manual', $used_profile, 'The manual profile was used.');

    // The document should have been automatically imported, so let's check
    // the upload status.
    $this->clickLink('Check Upload Status');
    $this->assertSession()->pageTextContains('The import for node Llamas are cool is complete.');

    // Request translation.
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
    $this->assertSession()->pageTextNotContains('Formulario de Contacto');
  }

}
