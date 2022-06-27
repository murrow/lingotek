<?php

namespace Drupal\Tests\lingotek\Functional\Controller;

use Drupal\Tests\lingotek\Functional\LingotekTestBase;

/**
 * Tests the supported locales controller.
 *
 * @group lingotek
 */
class LingotekSupportedLocalesControllerTest extends LingotekTestBase {

  /**
   * Tests that the supported locales are rendered.
   */
  public function testSupportedLocales() {
    $this->drupalGet('/admin/lingotek/supported-locales');
    $this->assertSession()->pageTextContains('German (Austria)');
    $this->assertSession()->pageTextContains('German (Germany)');
    $this->assertSession()->pageTextContains('Spanish (Spain)');
  }

}
