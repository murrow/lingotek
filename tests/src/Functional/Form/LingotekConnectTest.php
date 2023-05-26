<?php

namespace Drupal\Tests\lingotek\Functional\Form;

use Drupal\Tests\BrowserTestBase;

/**
 * Class for testing connecting to Lingotek.
 *
 * @group lingotek
 */
class LingotekConnectTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['lingotek', 'lingotek_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    \Drupal::state()->set('must_remain_disconnected', TRUE);
    // Login as admin.
    $this->drupalLogin($this->rootUser);
  }

  /**
   * Tests connecting to Lingotek.
   */
  public function testConnectToLingotek() {
    $assert_session = $this->assertSession();

    $this->drupalGet('admin/lingotek/setup/account');
    $this->clickLink('Connect Lingotek Account');
    $this->submitForm(['community' => 'test_community'], 'Next');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Assert there are options for workflows.
    $this->assertSession()->fieldExists('workflow');
    $option_field = $assert_session->optionExists('edit-workflow', '- Select -');
    $this->assertTrue($option_field->hasAttribute('selected'));
    $assert_session->optionExists('edit-workflow', 'test_workflow');
    $assert_session->optionExists('edit-workflow', 'test_workflow2');

    // Assert there are options for filters.
    $this->assertSession()->fieldExists('filter');
    $option_field = $assert_session->optionExists('edit-filter', 'drupal_default');
    $this->assertTrue($option_field->hasAttribute('selected'));
    $assert_session->optionExists('edit-filter', 'project_default');
    $assert_session->optionExists('edit-filter', 'test_filter');
    $assert_session->optionExists('edit-filter', 'test_filter2');
    $assert_session->optionExists('edit-filter', 'test_filter3');

    $this->assertSession()->fieldExists('subfilter');
    $option_field = $assert_session->optionExists('edit-subfilter', 'drupal_default');
    $this->assertTrue($option_field->hasAttribute('selected'));
    $assert_session->optionExists('edit-subfilter', 'project_default');
    $assert_session->optionExists('edit-subfilter', 'test_filter');
    $assert_session->optionExists('edit-subfilter', 'test_filter2');
    $assert_session->optionExists('edit-subfilter', 'test_filter3');

    $this->submitForm([
      'project' => 'test_project',
      'vault' => 'test_vault',
      'workflow' => 'test_workflow',
      'filter' => 'drupal_default',
      'subfilter' => 'drupal_default',
    ], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
  }

  /**
   * Tests connecting to Lingotek.
   */
  public function testConnectToLingotekWithoutFilters() {
    $assert_session = $this->assertSession();
    \Drupal::state()->set('lingotek.no_filters', TRUE);

    $this->drupalGet('admin/lingotek/setup/account');
    $this->clickLink('Connect Lingotek Account');
    $this->submitForm(['community' => 'test_community'], 'Next');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Assert there are options for workflows.
    $this->assertSession()->fieldExists('workflow');
    $option_field = $assert_session->optionExists('edit-workflow', '- Select -');
    $this->assertTrue($option_field->hasAttribute('selected'));
    $assert_session->optionExists('edit-workflow', 'test_workflow');
    $assert_session->optionExists('edit-workflow', 'test_workflow2');

    // Assert there are no options for filters and no select.
    $this->assertSession()->fieldValueNotEquals('filter', '');
    $this->assertSession()->fieldValueNotEquals('subfilter', '');

    $this->submitForm([
      'project' => 'test_project',
      'workflow' => 'test_workflow',
      'vault' => 'test_vault',
    ], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
  }

}
