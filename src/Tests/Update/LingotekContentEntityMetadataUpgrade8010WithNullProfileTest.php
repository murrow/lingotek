<?php

namespace Drupal\lingotek\Tests\Update;

use Drupal\lingotek\Entity\LingotekContentMetadata;
use Drupal\lingotek\Lingotek;
use Drupal\node\Entity\Node;
use Drupal\system\Tests\Update\UpdatePathTestBase;

/**
 * Tests the upgrade path after migrating metadata to its own entity.
 *
 * @group lingotek
 */
class LingotekContentEntityMetadataUpgrade8010WithNullProfileTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../tests/fixtures/update/drupal-8.lingotek.standard.pre8010.php.gz',
      __DIR__ . '/../../../tests/fixtures/update/null-profile-8010.php',
    ];
  }

  /**
   * Tests that content entity metadata is migrated correctly.
   */
  public function testContentEntityMetadataUpgrade() {
    $this->runUpdates();

    /** @var LingotekContentMetadata[] $metadatas */
    $metadatas = LingotekContentMetadata::loadMultiple();
    $this->assertEqual(count($metadatas), 15, 'All metadatas were migrated.');

    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $content_translation */
    $content_translation = \Drupal::service('lingotek.content_translation');

    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_config */
    $lingotek_config = \Drupal::service('lingotek.configuration');

    $results = \Drupal::database()->select('lingotek_metadata')
      ->fields('lingotek_metadata')
      ->execute()->fetchAll();
    foreach ($results as $result) {
      $this->assertNotEqual(NULL, var_export($result, TRUE));
    }

    $node = Node::load(1);
    $this->assertEqual('document_id_0', $content_translation->getDocumentId($node));
    $this->assertEqual(Lingotek::STATUS_CURRENT, $content_translation->getSourceStatus($node));
    $this->assertEqual(Lingotek::STATUS_CURRENT, $content_translation->getTargetStatus($node, 'es'));
    $this->assertEqual(Lingotek::STATUS_CURRENT, $content_translation->getTargetStatus($node, 'de'));
    $this->assertEqual('automatic', $lingotek_config->getEntityProfile($node, FALSE)->id());

    $node = Node::load(2);
    $this->assertEqual('document_id_1', $content_translation->getDocumentId($node));
    $this->assertEqual(Lingotek::STATUS_CURRENT, $content_translation->getSourceStatus($node));
    $this->assertEqual(Lingotek::STATUS_CURRENT, $content_translation->getTargetStatus($node, 'es'));
    $this->assertEqual(Lingotek::STATUS_REQUEST, $content_translation->getTargetStatus($node, 'de'));
    $this->assertEqual('manual', $lingotek_config->getEntityProfile($node, FALSE)->id());

    $node = Node::load(3);
    $this->assertEqual('document_id_2', $content_translation->getDocumentId($node));
    $this->assertEqual(Lingotek::STATUS_CURRENT, $content_translation->getSourceStatus($node));
    $this->assertEqual(Lingotek::STATUS_INTERMEDIATE, $content_translation->getTargetStatus($node, 'es'));
    $this->assertEqual(Lingotek::STATUS_CURRENT, $content_translation->getTargetStatus($node, 'de'));
    $this->assertEqual(NULL, $lingotek_config->getEntityProfile($node, FALSE));

    $node = Node::load(4);
    $this->assertEqual('document_id_3', $content_translation->getDocumentId($node));
    $this->assertEqual(Lingotek::STATUS_CURRENT, $content_translation->getSourceStatus($node));
    $this->assertEqual(Lingotek::STATUS_PENDING, $content_translation->getTargetStatus($node, 'es'));
    $this->assertEqual(Lingotek::STATUS_UNTRACKED, $content_translation->getTargetStatus($node, 'de'));
    $this->assertEqual('customized', $lingotek_config->getEntityProfile($node, FALSE)->id());

    $node = Node::load(5);
    $this->assertEqual('document_id_4', $content_translation->getDocumentId($node));
    $this->assertEqual(Lingotek::STATUS_IMPORTING, $content_translation->getSourceStatus($node));
    $this->assertEqual(Lingotek::STATUS_READY, $content_translation->getTargetStatus($node, 'es'));
    $this->assertEqual(Lingotek::STATUS_UNTRACKED, $content_translation->getTargetStatus($node, 'de'));
    $this->assertEqual('customized', $lingotek_config->getEntityProfile($node, FALSE)->id());


    $node = Node::load(6);
    $this->assertEqual('document_id_5', $content_translation->getDocumentId($node));
    $this->assertEqual(Lingotek::STATUS_EDITED, $content_translation->getSourceStatus($node));
    $this->assertEqual(Lingotek::STATUS_UNTRACKED, $content_translation->getTargetStatus($node, 'es'));
    $this->assertEqual(Lingotek::STATUS_EDITED, $content_translation->getTargetStatus($node, 'de'));
    $this->assertEqual('automatic', $lingotek_config->getEntityProfile($node, FALSE)->id());

    for ($i = 6; $i < 11; $i++) {
      $node = Node::load($i + 1);
      $this->assertEqual('document_id_' . $i, $content_translation->getDocumentId($node));
      $this->assertEqual(Lingotek::STATUS_CURRENT, $content_translation->getSourceStatus($node));
      $this->assertEqual(Lingotek::STATUS_CURRENT, $content_translation->getTargetStatus($node, 'es'));
      $this->assertEqual(Lingotek::STATUS_CURRENT, $content_translation->getTargetStatus($node, 'de'));
      $this->assertEqual('automatic', $lingotek_config->getEntityProfile($node, FALSE)->id());
    }

    $node = Node::load(13);
    $this->assertEqual('document_id_12', $content_translation->getDocumentId($node));
    $this->assertEqual(Lingotek::STATUS_CURRENT, $content_translation->getSourceStatus($node));
    $this->assertEqual(Lingotek::STATUS_NONE, $content_translation->getTargetStatus($node, 'es'));
    $this->assertEqual(Lingotek::STATUS_NONE, $content_translation->getTargetStatus($node, 'de'));
    $this->assertEqual('automatic', $lingotek_config->getEntityProfile($node, FALSE)->id());

    $node = Node::load(14);
    $this->assertEqual('document_id_13', $content_translation->getDocumentId($node));
    $this->assertEqual(Lingotek::STATUS_CURRENT, $content_translation->getSourceStatus($node));
    $this->assertEqual(Lingotek::STATUS_NONE, $content_translation->getTargetStatus($node, 'es'));
    $this->assertEqual(Lingotek::STATUS_CURRENT, $content_translation->getTargetStatus($node, 'de'));
    $this->assertEqual('manual', $lingotek_config->getEntityProfile($node, FALSE)->id());

    $node = Node::load(15);
    $this->assertEqual('document_id_14', $content_translation->getDocumentId($node));
    $this->assertEqual(Lingotek::STATUS_CURRENT, $content_translation->getSourceStatus($node));
    $this->assertEqual(Lingotek::STATUS_CURRENT, $content_translation->getTargetStatus($node, 'es'));
    $this->assertEqual(Lingotek::STATUS_NONE, $content_translation->getTargetStatus($node, 'de'));
    $this->assertEqual('manual', $lingotek_config->getEntityProfile($node, FALSE)->id());

    $this->drupalLogin($this->rootUser);
    /** @var \Drupal\Core\Extension\ModuleInstallerInterface $module_installer */
    $module_installer = \Drupal::service('module_installer');
    $module_installer->install(['lingotek_test']);
    $this->connectToLingotek();
    $this->goToContentBulkManagementForm();
  }

  /**
   * Go to the content bulk management form.
   *
   * @param string $entity_type_id
   *   Entity type ID we want to manage in bulk. By default is node.
   */
  protected function goToContentBulkManagementForm($entity_type_id = 'node') {
    $this->drupalGet('admin/lingotek/manage/' . $entity_type_id);
  }

  protected function connectToLingotek() {
    $this->drupalGet('admin/lingotek/setup/account');
    $this->clickLink('Connect Lingotek Account');
    $this->drupalPostForm(NULL, ['community' => 'test_community'], 'Next');
    $this->drupalPostForm(NULL, [
      'project' => 'test_project',
      'vault' => 'test_vault'
    ], 'Save configuration');
  }

}