<?php

namespace Drupal\lingotek\Tests;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\user\Entity\Role;

/**
 * Tests different permissions of the Lingotek module.
 *
 * @group lingotek
 */
class LingotekManageLingotekTranslationsPermissionTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['lingotek', 'lingotek_test', 'node', 'toolbar', 'block'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $roles = $this->translationManagerUser->getRoles(TRUE);
    /** @var \Drupal\user\RoleInterface $role */
    $role = Role::load($roles[0]);
    $role->grantPermission('access toolbar')->save();

    // Place the actions and title block.
    $this->drupalPlaceBlock('page_title_block', ['region' => 'content', 'weight' => -5]);
    $this->drupalPlaceBlock('local_tasks_block', ['region' => 'content', 'weight' => -10]);

    // Create Article node types.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_ES')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);

    drupal_static_reset();
    \Drupal::entityManager()->clearCachedDefinitions();
    \Drupal::service('entity.definition_update_manager')->applyUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $edit = [
      'node[article][enabled]' => 1,
      'node[article][profiles]' => 'automatic',
      'node[article][fields][title]' => 1,
      'node[article][fields][body]' => 1,
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-content-form');
  }

  /**
   * Test that if user can see settings tab without right permissions
   */
  public function testCannotSeeSettingsTabWithoutRightPermission() {
    $user = $this->drupalCreateUser([
      'administer lingotek',
      'assign lingotek translation profiles',
      'manage lingotek translations'
    ]);
    // Login as user.
    $this->drupalLogin($user);
    // Get the settings form.
    $this->drupalGet('admin/lingotek/settings');
    // Assert translation profile can be assigned.
    $this->assertNoText('You are not authorized to access this page.');

    $user = $this->drupalCreateUser([
      'assign lingotek translation profiles',
      'manage lingotek translations'
    ]);
    // Login as user.
    $this->drupalLogin($user);
    // Get the settings form.
    $this->drupalGet('admin/lingotek/settings');
    // Assert translation profile cannot be assigned.
    $this->assertText('You are not authorized to access this page.');
  }

  /**
   * Tests that a user can navigate to the content bulk translation pages.
   */
  public function testNavigationThroughSiteForBulkContentTranslationAsTranslationsManager() {
    // Login as translations manager.
    $this->drupalLogin($this->translationManagerUser);

    $this->drupalGet('/user');

    // Assert the toolbar has the proper links for configuration and translation.
    $this->assertLink('Configuration');
    $this->assertLink('Translation');

    // Assert in the configuration panes we have access to Lingotek Translation.
    $this->clickLink('Configuration');

    $this->assertText('Regional and language');
    $this->clickLink('Lingotek Translation');

    // Assert we see the dashboard and can navigate to content.
    $this->assertLink('Content');
    $this->clickLink('Content');
    $this->assertText('Manage Translations');
  }

  /**
   * Tests that a user can navigate to the config bulk translation pages.
   */
  public function testNavigationThroughSiteForBulkConfigTranslationAsTranslationsManager() {
    // Login as translations manager.
    $this->drupalLogin($this->translationManagerUser);

    $this->drupalGet('/user');

    // Assert the toolbar has the proper links for configuration and translation.
    $this->assertLink('Configuration');
    $this->assertLink('Translation');

    // Assert in the configuration panes we have access to Lingotek Translation.
    $this->clickLink('Configuration');

    $this->assertText('Regional and language');
    $this->clickLink('Lingotek Translation');

    // Config shouldn't be visible unless we can translate settings too.
    $this->assertNoLink('Config');
  }

  /**
   * Tests that a user can navigate to the config bulk translation pages.
   */
  public function testNavigationThroughSiteForBulkConfigTranslationAsTranslationsManagerWithTranslateConfigPermission() {
    // Login as translations manager, but including the 'translate configuration'
    // permission.
    $roles = $this->translationManagerUser->getRoles(TRUE);
    /** @var \Drupal\user\RoleInterface $role */
    $role = Role::load($roles[0]);
    $role->grantPermission('translate configuration')->save();
    $this->drupalLogin($this->translationManagerUser);

    $this->drupalGet('/user');

    // Assert the toolbar has the proper links for configuration and translation.
    $this->assertLink('Configuration');
    $this->assertLink('Translation');

    // Assert in the configuration panes we have access to Lingotek Translation.
    $this->clickLink('Configuration');

    $this->assertText('Regional and language');
    $this->clickLink('Lingotek Translation');

    // Assert we see the dashboard and can navigate to config.
    $this->assertLink('Config');
    $this->clickLink('Config');
    $this->assertText('Manage Configuration Translation');
  }

  /**
   * Tests dashboard works as a translations manager.
   */
  public function testDashboardAsTranslationsManager() {
    // Login as translations manager.
    $this->drupalLogin($this->translationManagerUser);

    // Check the stats.
    $request = $this->curlExec([
      CURLOPT_URL => \Drupal::url('lingotek.dashboard_endpoint', [], ['absolute' => TRUE]),
      CURLOPT_HTTPGET => TRUE,
      CURLOPT_CUSTOMREQUEST => NULL,
      CURLOPT_NOBODY => FALSE,
      CURLOPT_HTTPHEADER => [
        'Accept: application/json',
      ],
    ]);
    $response = json_decode($request, TRUE);
    $this->verbose(var_export($response, TRUE));
    $this->assertIdentical('GET', $response['method']);
    $this->assertIdentical(2, $response['count']);
    $this->assertIdentical('en', $response['languages']['en_US']['xcode']);
    $this->assertIdentical(1, $response['languages']['en_US']['active']);
    $this->assertIdentical(1, $response['languages']['en_US']['enabled']);
    $this->assertIdentical('es', $response['languages']['es_ES']['xcode']);
    $this->assertIdentical(1, $response['languages']['es_ES']['active']);
    $this->assertIdentical(1, $response['languages']['es_ES']['enabled']);
  }

  /**
   * The node translation form doesn't contain any operations if the current
   * user is not a translation manager.
   */
  public function testNodeTranslateDoesntContainBulkActions() {
    // Create a user that can create content and translate it, but not with the
    // Lingotek module.
    $contentManager = $this->createUser([
      'access toolbar',
      'access content overview',
      'administer nodes',
      'assign lingotek translation profiles',
      'create article content',
      'translate any entity',
    ]);
    $this->drupalLogin($contentManager);

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->clickLink('Translate');

    // We don't have any operations or actions available.
    $this->assertNoLink('Upload');
    $this->assertNoFieldByName('op');
  }

  /**
   * The node translation form doesn't contain any operations if the current
   * user is not a translation manager.
   */
  public function testConfigTranslateDoesntContainBulkActions() {
    // Login as a user that can translate configuration, but cannot manage
    // Lingotek translations.
    $contentManager = $this->createUser([
      'access toolbar',
      'access administration pages',
      'administer site configuration',
      'translate configuration',
    ]);
    $this->drupalLogin($contentManager);

    // Check that the translate tab is in the site information.
    $this->drupalGet('/admin/config/system/site-information');
    $this->clickLink('Translate system information');

    // We don't have any operations available.
    $this->assertNoLink('Upload');
  }

}
