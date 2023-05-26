<?php

namespace Drupal\Tests\lingotek\Functional\Form;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\BrowserTestBase;

/**
 * Test the Drupal language form alters.
 *
 * @group lingotek
 */
class LanguageFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['lingotek', 'lingotek_test'];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // User to add and remove language.
    $admin_user = $this->drupalCreateUser(['administer languages', 'access administration pages']);
    $this->drupalLogin($admin_user);
  }

  /**
   * Tests adding a defined language has the right locale.
   */
  public function testAddingLanguage() {
    // Enable import of translations. By default this is disabled for automated
    // tests.
    $this->config('locale.settings')
      ->set('translation.import_enabled', TRUE)
      ->save();

    $this->drupalGet('admin/config/regional/language/add');

    $edit = [
      'predefined_langcode' => 'de',
    ];
    $this->submitForm($edit, 'Add language');

    // Click on edit for German.
    $this->clickLink('Edit', 1);

    // Assert that the locale is correct.
    $this->assertSession()->fieldValueEquals('lingotek_locale', 'de-DE');
  }

  /**
   * Tests editing a defined language has the right locale.
   */
  public function testEditingLanguage() {
    ConfigurableLanguage::createFromLangcode('de')->save();
    $this->drupalGet('/admin/config/regional/language');
    // Click on edit for German.
    $this->clickLink('Edit', 1);

    // Assert that the locale is correct.
    $this->assertSession()->fieldValueEquals('lingotek_locale', 'de-DE');
    $this->submitForm(['name' => 'German (Germany)'], 'Save language');
    $this->assertSession()->pageTextContains('German (Germany)');
  }

  /**
   * Tests editing a defined language has the right locale.
   */
  public function testEditingLanguageWith401() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.locales_error', TRUE);

    ConfigurableLanguage::createFromLangcode('de')->save();
    $this->drupalGet('/admin/config/regional/language');
    // Click on edit for German.
    $this->clickLink('Edit', 1);

    // Assert that the locale is correct.
    $this->assertSession()->fieldValueEquals('lingotek_locale', 'de-DE');
    $this->submitForm(['name' => 'German (Germany)'], 'Save language');
    $this->assertSession()->pageTextContains('German (Germany)');
    $this->assertSession()->pageTextContains("The Lingotek locale has not been validated.");
  }

  /**
   * Tests adding a custom language with a custom locale.
   */
  public function testAddingCustomLanguage() {
    // Check that there is a select for locales.
    $this->drupalGet('admin/config/regional/language/add');
    $this->assertSession()->fieldExists('lingotek_locale', 'There is a field for adding the Lingotek locale.');

    // Assert that the locale is empty.
    $this->assertSession()->fieldValueEquals('lingotek_locale', '');
    // The Lingotek locale is enabled by default.
    $this->getSession()->getPage()->hasUncheckedField('lingotek_disabled');

    $edit = [
      'predefined_langcode' => 'custom',
      'langcode' => 'es-DE',
      'label' => 'Spanish (Germany)',
      'direction' => 'ltr',
      'lingotek_locale' => 'es-ES',
    ];
    $this->submitForm($edit, 'Add custom language');
    $this->assertSession()->pageTextContains('The language Spanish (Germany) has been created and can now be used.');

    // Ensure the language is created and with the right locale.
    $language = ConfigurableLanguage::load('es-DE');
    $this->assertEquals('es_ES', $language->getThirdPartySetting('lingotek', 'locale'), 'The Lingotek locale has been saved successfully.');

    // Ensure the locale and langcode are correctly mapped.
    /** @var \Drupal\lingotek\LanguageLocaleMapperInterface $locale_mapper */
    $locale_mapper = \Drupal::service('lingotek.language_locale_mapper');
    $this->assertEquals('es_ES', $locale_mapper->getLocaleForLangcode('es-DE'), 'The language locale mapper correctly guesses the locale.');
    $this->assertEquals('es-DE', $locale_mapper->getConfigurableLanguageForLocale('es_ES')->getId(), 'The language locale mapper correctly guesses the langcode.');
  }

  /**
   * Tests disabling for Lingotek a custom language that was enabled.
   */
  public function testDisablingCustomLanguage() {
    $this->testAddingCustomLanguage();

    // Check that there is a select for locales.
    $this->drupalGet('/admin/config/regional/language');

    // Click on edit for Spanish (Germany).
    $this->clickLink('Edit', 1);
    // The Lingotek locale is enabled by default.
    $this->getSession()->getPage()->hasUncheckedField('lingotek_disabled');

    $edit = [
      'lingotek_disabled' => TRUE,
    ];
    $this->submitForm($edit, 'Save language');

    // Ensure the language is disabled.
    $language = ConfigurableLanguage::load('es-DE');
    $this->assertTrue($language->getThirdPartySetting('lingotek', 'disabled'), 'The Lingotek locale has been disabled successfully.');

    // Check that there is a select for locales.
    $this->drupalGet('/admin/config/regional/language');

    // Click on edit for Spanish (Germany).
    $this->clickLink('Edit', 1);
    $this->getSession()->getPage()->hasCheckedField('lingotek_disabled');
  }

  /**
   * Tests enabling for Lingotek a custom language that was disabled.
   */
  public function testEnablingCustomLanguage() {
    ConfigurableLanguage::create(['id' => 'de-at', 'label' => 'German (AT)'])
      ->setThirdPartySetting('lingotek', 'disabled', TRUE)
      ->save();

    // Check that there is a select for locales.
    $this->drupalGet('/admin/config/regional/language');

    // Click on edit for German (AT).
    $this->clickLink('Edit', 1);
    // The Lingotek locale is enabled by default.
    $this->getSession()->getPage()->hasCheckedField('lingotek_disabled');

    $edit = [
      'lingotek_disabled' => FALSE,
    ];
    $this->submitForm($edit, 'Save language');

    // Ensure the language is disabled.
    $language = ConfigurableLanguage::load('de-at');
    $this->assertFalse($language->getThirdPartySetting('lingotek', 'disabled'), 'The Lingotek locale has been enabled successfully.');

    // Check that there is a select for locales.
    $this->drupalGet('/admin/config/regional/language');

    // Click on edit for German (AT).
    $this->clickLink('Edit', 1);
    $this->getSession()->getPage()->hasUncheckedField('lingotek_disabled');
  }

  /**
   * Tests editing a custom language with a custom locale.
   */
  public function testEditingCustomLanguage() {
    ConfigurableLanguage::create(['id' => 'de-at', 'label' => 'German (AT)'])->save();
    $this->drupalGet('/admin/config/regional/language');

    // Click on edit for German (AT).
    $this->clickLink('Edit', 1);

    // Assert that the locale is correct.
    $this->assertSession()->fieldValueEquals('lingotek_locale', 'de-AT');

    // Edit the locale.
    $edit = ['lingotek_locale' => 'de-DE'];
    $this->submitForm($edit, 'Save language');

    // Click again on edit for German (AT).
    $this->clickLink('Edit', 1);
    // Assert that the locale is correct.
    $this->assertSession()->fieldValueEquals('lingotek_locale', 'de-DE');
  }

  /**
   * Tests editing a custom language with a custom locale.
   */
  public function testEditingCustomLanguageWithWrongLocale() {
    ConfigurableLanguage::create(['id' => 'de-at', 'label' => 'German (AT)'])->save();
    $this->drupalGet('/admin/config/regional/language');

    // Click on edit for German (AT).
    $this->clickLink('Edit', 1);

    // Assert that the locale is correct.
    $this->assertSession()->fieldValueEquals('lingotek_locale', 'de-AT');

    // Edit the locale.
    $edit = ['lingotek_locale' => 'de-IN'];
    $this->submitForm($edit, 'Save language');
    $this->assertSession()->pageTextContains('The Lingotek locale de-IN does not exist.');
  }

  /**
   * Tests editing a custom language with a custom locale.
   */
  public function testEditingCustomLanguageWithUnderscoredLocale() {
    ConfigurableLanguage::create(['id' => 'de-at', 'label' => 'German (AT)'])->save();
    $this->drupalGet('/admin/config/regional/language');

    // Click on edit for German (AT).
    $this->clickLink('Edit', 1);

    // Assert that the locale is correct.
    $this->assertSession()->fieldValueEquals('lingotek_locale', 'de-AT');

    // Edit the locale.
    $edit = ['lingotek_locale' => 'de_AT'];
    $this->submitForm($edit, 'Save language');

    // Click on edit for German (AT).
    $this->clickLink('Edit', 1);

    // Assert that the locale is correct.
    $this->assertSession()->fieldValueEquals('lingotek_locale', 'de-AT');
  }

  /**
   * Tests autocomplete on language locales.
   */
  public function testAutocompleteLocale() {
    $page = $this->getSession()->getPage();
    // Check that there is a select for locales.
    ConfigurableLanguage::createFromLangcode('de')->save();
    $this->drupalGet('/admin/config/regional/language');
    // Click on edit for German.
    $this->clickLink('Edit', 1);

    // Make sure that the autocomplete library is added.
    $this->assertSession()->responseContains('core/misc/autocomplete.js');

    // Assert that the locale is correct.
    $this->assertSession()->fieldValueEquals('lingotek_locale', 'de-DE');

    // Check the autocomplete route.
    $result = $this->xpath('//input[@name="lingotek_locale" and contains(@data-autocomplete-path, "admin/lingotek/supported-locales-autocomplete")]');
    $target_url = $this->getAbsoluteUrl($result[0]->getAttribute('data-autocomplete-path'));
    $this->drupalGet($target_url, ['query' => ['q' => 'de']]);
    $result = json_decode($page->getContent(), TRUE);
    $this->assertEquals($result[0]['value'], 'de-AT');
    $this->assertEquals($result[0]['label'], 'German (Austria) (de-AT) [matched: Code: <em class="placeholder">de-AT</em>]');
    $this->assertEquals($result[1]['value'], 'de-DE');
    $this->assertEquals($result[1]['label'], 'German (Germany) (de-DE) [matched: Code: <em class="placeholder">de-DE</em>]');
  }

}
