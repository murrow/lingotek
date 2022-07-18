<?php

namespace Drupal\Tests\lingotek\Functional\Render\Element;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Lingotek;
use Drupal\node\Entity\Node;
use Drupal\Tests\lingotek\Functional\LingotekTestBase;

/**
 * Tests the secondary actions can be extended.
 *
 * @group lingotek
 */
class TargetSecondaryActionsEventTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['system', 'block', 'node', 'lingotek_form_test', 'lingotek_secondary_actions_test'];

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
    // Add locales.
    ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_DE')->save();

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

    $this->saveLingotekContentTranslationSettingsForNodeTypes(['article'], 'manual');
  }

  /**
   * Tests #type 'lingotek_source_statuses'.
   */
  public function testLingotekTargetStatuses() {
    $basepath = \Drupal::request()->getBasePath();

    /** @var \Drupal\node\NodeInterface $entity */
    $entity = Node::create(['id' => 1, 'title' => 'Llamas are cool', 'type' => 'article']);
    $entity->save();

    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $translation_service->setDocumentId($entity, 'test-document-id');
    $translation_service->setTargetStatus($entity, 'de', Lingotek::STATUS_PENDING);
    $this->drupalGet('/lingotek_form_test/lingotek_translation_statuses/node/1');

    $this->assertTargetAction("Check translation status",
      "$basepath/admin/lingotek/entity/check_target/test-document-id/de_DE?destination=$basepath/lingotek_form_test/lingotek_translation_statuses/node/1"
    );
    $this->assertTargetAction("Workbench action edited from external source",
      "$basepath/admin/lingotek/workbench/test-document-id/de_DE"
    );
    // Test we can add actions from external sources.
    $this->assertTargetAction("Action from external source",
      "https://lingotek.com"
    );
  }

  /**
   * Tests #type 'lingotek_source_status'.
   */
  public function testLingotekTargetStatus() {
    $basepath = \Drupal::request()->getBasePath();

    /** @var \Drupal\node\NodeInterface $entity */
    $entity = Node::create(['id' => 1, 'title' => 'Llamas are cool', 'type' => 'article']);
    $entity->save();

    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $translation_service->setDocumentId($entity, 'test-document-id');
    $translation_service->setTargetStatus($entity, 'de', Lingotek::STATUS_PENDING);

    $this->drupalGet('/lingotek_form_test/lingotek_translation_status/node/1');

    $this->assertTargetAction("Check translation status",
      "$basepath/admin/lingotek/entity/check_target/test-document-id/de_DE?destination=$basepath/lingotek_form_test/lingotek_translation_status/node/1"
    );
    $this->assertTargetAction("Workbench action edited from external source",
      "$basepath/admin/lingotek/workbench/test-document-id/de_DE"
    );
    // Test we can add actions from external sources.
    $this->assertTargetAction("Action from external source",
      "https://lingotek.com"
    );
  }

  protected function assertTargetAction($text, $url) {
    $link = $this->xpath('//ul[contains(@class,lingotek-target-actions)]//li//a[@href="' . $url . '" and text()="' . $text . '"]');
    $this->assertCount(1, $link, 'Action exists.');
  }

  protected function assertNoTargetAction($text, $url) {
    $link = $this->xpath('//ul[contains(@class,lingotek-target-actions)]//li//a[@href="' . $url . '" and text()="' . $text . '"]');
    $this->assertCount(0, $link, 'Action exists.');
  }

}
