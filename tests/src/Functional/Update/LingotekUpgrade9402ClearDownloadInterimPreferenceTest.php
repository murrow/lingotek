<?php

namespace Drupal\Tests\lingotek\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests the upgrade path for setting enable_download_interim preference.
 *
 * @group lingotek
 * @group legacy
 */
class LingotekUpgrade9402ClearDownloadInterimPreferenceTest extends UpdatePathTestBase {

  /**
   * The Lingotek configuration service.
   *
   * @var \Drupal\lingotek\LingotekConfigurationServiceInterface
   */
  protected $lingotekConfiguration;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->lingotekConfiguration = $this->container->get('lingotek.configuration');
  }

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/drupal-88x.lingotek-2x20.standard.php.gz',
      __DIR__ . '/../../../fixtures/update/9402-set-preference-enable-download-interim.php',
    ];
  }

  /**
   * Tests that the upgrade sets the value for the enable_download_interim
   * preference.
   */
  public function testUpgrade() {
    $this->assertTrue($this->lingotekConfiguration->getPreference('enable_download_interim'));

    $this->runUpdates();

    $this->assertNull($this->lingotekConfiguration->getPreference('enable_download_interim'));
  }

}
