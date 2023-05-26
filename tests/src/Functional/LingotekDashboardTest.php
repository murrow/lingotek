<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests the Lingotek dashboard.
 *
 * @group lingotek
 */
class LingotekDashboardTest extends LingotekTestBase {

  /**
   * {@inheritDoc}
   */
  protected static $modules = ['block', 'node', 'comment'];

  /**
   * Test that a language can be added.
   */
  public function testDashboardCanAddLanguage() {
    $url = Url::fromRoute('lingotek.dashboard_endpoint')->setAbsolute()->toString();

    $post = [
      'code' => 'it_IT',
      'language' => 'Italian',
      'native' => 'Italiano',
      'direction' => '',
    ];
    $request = $this->client->post($url, [
      'body' => http_build_query($post),
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/x-www-form-urlencoded',
      ],
      'http_errors' => FALSE,
    ]);
    $response = json_decode($request->getBody(), TRUE);
    $this->assertSame('it', $response['xcode']);
    $this->assertSame('it_IT', $response['locale']);
    $this->assertSame(1, $response['active']);
    $this->assertSame(1, $response['enabled']);
    $this->assertSame(0, $response['source']['total']);
    $this->assertSame(0, $response['target']['total']);

    $italian_language = ConfigurableLanguage::load('it');
    /** @var \Drupal\language\ConfigurableLanguageInterface $italian_language */
    $this->assertNotNull($italian_language, 'Italian language has been added.');
    $this->assertSame('Italian', $italian_language->getName());
    $this->assertSame(ConfigurableLanguage::DIRECTION_LTR, $italian_language->getDirection());

    // @ToDo: The native language is not saved.
    // $config_translation = \Drupal::languageManager()->getLanguageConfigOverride('it', $italian_language->id());
  }

  /**
   * Test that a language can't be added.
   */
  public function testDashboardCanNotAddLanguageWithoutPermission() {
    $url = Url::fromRoute('lingotek.dashboard_endpoint')->setAbsolute()->toString();

    $no_administer_languages = $this->createUser([
      'administer lingotek',
    ]);
    $this->drupalLogin($no_administer_languages);

    $post = [
      'code' => 'it_IT',
      'language' => 'Italian',
      'native' => 'Italiano',
      'direction' => '',
    ];
    $request = $this->client->post($url, [
      'body' => http_build_query($post),
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/x-www-form-urlencoded',
      ],
      'http_errors' => FALSE,
    ]);
    $response = json_decode($request->getBody(), TRUE);
    $this->assertEquals($request->getStatusCode(), Response::HTTP_FORBIDDEN);
  }

  /**
   * Test that a language can be added.
   */
  public function testDashboardCanAddRTLLanguage() {
    $url = Url::fromRoute('lingotek.dashboard_endpoint')->setAbsolute()->toString();

    $post = [
      'code' => 'ar_AE',
      'language' => 'Arabic',
      'native' => 'العربية',
      'direction' => 'RTL',
    ];
    $request = $this->client->post($url, [
      'body' => http_build_query($post),
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/x-www-form-urlencoded',
      ],
      'http_errors' => FALSE,
    ]);
    $response = json_decode($request->getBody(), TRUE);
    $this->assertSame('ar', $response['xcode']);
    $this->assertSame('ar_AE', $response['locale']);
    $this->assertSame(1, $response['active']);
    $this->assertSame(1, $response['enabled']);
    $this->assertSame(0, $response['source']['total']);
    $this->assertSame(0, $response['target']['total']);

    /** @var \Drupal\language\ConfigurableLanguageInterface $arabic_language */
    $arabic_language = ConfigurableLanguage::load('ar');
    $this->assertNotNull($arabic_language, 'Arabic language has been added.');
    $this->assertSame('Arabic', $arabic_language->getName());
    $this->assertSame(ConfigurableLanguage::DIRECTION_RTL, $arabic_language->getDirection());

    // @ToDo: The native language is not saved.
  }

  /**
   * Test that arabic (somehow a special language) can be added.
   */
  public function testDashboardCanAddArabicLanguage() {
    $url = Url::fromRoute('lingotek.dashboard_endpoint')->setAbsolute()->toString();

    $post = [
      'code' => 'ar',
      'language' => 'Arabic',
      'native' => 'العربية',
      'direction' => 'RTL',
    ];
    $request = $this->client->post($url, [
      'body' => http_build_query($post),
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/x-www-form-urlencoded',
      ],
      'http_errors' => FALSE,
    ]);
    $response = json_decode($request->getBody(), TRUE);
    $this->assertSame('ar', $response['xcode']);
    $this->assertSame('ar', $response['locale']);
    $this->assertSame(1, $response['active']);
    $this->assertSame(1, $response['enabled']);
    $this->assertSame(0, $response['source']['total']);
    $this->assertSame(0, $response['target']['total']);

    /** @var \Drupal\language\ConfigurableLanguageInterface $arabic_language */
    $arabic_language = ConfigurableLanguage::load('ar');
    $this->assertNotNull($arabic_language, 'Arabic language has been added.');
    $this->assertSame('Arabic', $arabic_language->getName());
    $this->assertSame(ConfigurableLanguage::DIRECTION_RTL, $arabic_language->getDirection());

    // @ToDo: The native language is not saved.
  }

  /**
   * Test that different locales from same language can be added.
   */
  public function testDashboardAddLanguageAndThenLocale() {
    $url = Url::fromRoute('lingotek.dashboard_endpoint')->setAbsolute()->toString();

    $post = [
      'code' => 'es_ES',
      'language' => 'Spanish (Spain)',
      'native' => 'Español',
      'direction' => '',
    ];
    $request = $this->client->post($url, [
      'body' => http_build_query($post),
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/x-www-form-urlencoded',
      ],
      'http_errors' => FALSE,
    ]);
    $response = json_decode($request->getBody(), TRUE);
    $this->assertSame('es', $response['xcode']);
    $this->assertSame('es_ES', $response['locale']);
    $this->assertSame(1, $response['active']);
    $this->assertSame(1, $response['enabled']);
    $this->assertSame(0, $response['source']['total']);
    $this->assertSame(0, $response['target']['total']);

    $esEsLanguage = ConfigurableLanguage::load('es');
    /** @var \Drupal\language\ConfigurableLanguageInterface $esEsLanguage */
    $this->assertNotNull($esEsLanguage, 'Spanish (Spain) language has been added.');
    $this->assertSame('Spanish (Spain)', $esEsLanguage->getName());
    $this->assertSame(ConfigurableLanguage::DIRECTION_LTR, $esEsLanguage->getDirection());

    // The language must be returned in the dashboard.
    $request = $this->client->get($url, [
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
      ],
      'http_errors' => FALSE,
    ]);
    $response = json_decode($request->getBody(), TRUE);
    $returned_languages = array_keys($response['languages']);
    $this->assertSame(['en_US', 'es_ES'], $returned_languages);

    $post = [
      'code' => 'es_AR',
      'language' => 'Spanish (Argentina)',
      'native' => 'Español',
      'direction' => '',
    ];
    $request = $this->client->post($url, [
      'body' => http_build_query($post),
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/x-www-form-urlencoded',
      ],
      'http_errors' => FALSE,
    ]);
    $response = json_decode($request->getBody(), TRUE);
    $this->assertSame('es-ar', $response['xcode']);
    $this->assertSame('es_AR', $response['locale']);
    $this->assertSame(1, $response['active']);
    $this->assertSame(1, $response['enabled']);
    $this->assertSame(0, $response['source']['total']);
    $this->assertSame(0, $response['target']['total']);

    $esArLanguage = ConfigurableLanguage::load('es-ar');
    /** @var \Drupal\language\ConfigurableLanguageInterface $esArLanguage */
    $this->assertNotNull($esArLanguage, 'Spanish (Argentina) language has been added.');
    $this->assertSame('Spanish (Argentina)', $esArLanguage->getName());
    $this->assertSame(ConfigurableLanguage::DIRECTION_LTR, $esArLanguage->getDirection());

    // The language must be returned in the dashboard.
    $request = $this->client->get($url, [
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
      ],
      'http_errors' => FALSE,
    ]);
    $response = json_decode($request->getBody(), TRUE);
    $returned_languages = array_keys($response['languages']);
    $this->assertSame(['en_US', 'es_AR', 'es_ES'], $returned_languages);
  }

  /**
   * Test that different locales from same language can be added.
   */
  public function testDashboardAddLocaleAndThenLanguage() {
    $url = Url::fromRoute('lingotek.dashboard_endpoint')->setAbsolute()->toString();

    $post = [
      'code' => 'es_AR',
      'language' => 'Spanish (Argentina)',
      'native' => 'Español',
      'direction' => '',
    ];
    $request = $this->client->post($url, [
      'body' => http_build_query($post),
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/x-www-form-urlencoded',
      ],
      'http_errors' => FALSE,
    ]);
    $response = json_decode($request->getBody(), TRUE);
    $this->assertSame('es', $response['xcode']);
    $this->assertSame('es_AR', $response['locale']);
    $this->assertSame(1, $response['active']);
    $this->assertSame(1, $response['enabled']);
    $this->assertSame(0, $response['source']['total']);
    $this->assertSame(0, $response['target']['total']);

    $esArLanguage = ConfigurableLanguage::load('es');
    /** @var \Drupal\language\ConfigurableLanguageInterface $esArLanguage */
    $this->assertNotNull($esArLanguage, 'Spanish (Argentina) language has been added.');
    $this->assertSame('Spanish (Argentina)', $esArLanguage->getName());
    $this->assertSame(ConfigurableLanguage::DIRECTION_LTR, $esArLanguage->getDirection());

    // The language must be returned in the dashboard.
    $request = $this->client->get($url, [
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
      ],
      'http_errors' => FALSE,
    ]);
    $response = json_decode($request->getBody(), TRUE);
    $returned_languages = array_keys($response['languages']);
    $this->assertSame(['en_US', 'es_AR'], $returned_languages);

    $post = [
      'code' => 'es_ES',
      'language' => 'Spanish (Spain)',
      'native' => 'Español',
      'direction' => '',
    ];
    $request = $this->client->post($url, [
      'body' => http_build_query($post),
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/x-www-form-urlencoded',
      ],
      'http_errors' => FALSE,
    ]);
    $response = json_decode($request->getBody(), TRUE);
    $this->assertSame('es-es', $response['xcode']);
    $this->assertSame('es_ES', $response['locale']);
    $this->assertSame(1, $response['active']);
    $this->assertSame(1, $response['enabled']);
    $this->assertSame(0, $response['source']['total']);
    $this->assertSame(0, $response['target']['total']);

    $esEsLanguage = ConfigurableLanguage::load('es-es');
    /** @var \Drupal\language\ConfigurableLanguageInterface $esEsLanguage */
    $this->assertNotNull($esEsLanguage, 'Spanish (Spain) language has been added.');
    $this->assertSame('Spanish (Spain)', $esEsLanguage->getName());
    $this->assertSame(ConfigurableLanguage::DIRECTION_LTR, $esEsLanguage->getDirection());

    // The language must be returned in the dashboard.
    $request = $this->client->get($url, [
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
      ],
      'http_errors' => FALSE,
    ]);
    $response = json_decode($request->getBody(), TRUE);
    $returned_languages = array_keys($response['languages']);
    $this->assertSame(['en_US', 'es_AR', 'es_ES'], $returned_languages);
  }

  /**
   * Tests that we can disable languages in the dashboard.
   */
  public function testDisableLanguage() {
    $url = Url::fromRoute('lingotek.dashboard_endpoint')->setAbsolute()->toString();
    // Add a language.
    $post = [
      'code' => 'es_ES',
      'language' => 'Spanish (Spain)',
      'native' => 'Español (España)',
      'direction' => '',
    ];
    $request = $this->client->post($url, [
      'body' => http_build_query($post),
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/x-www-form-urlencoded',
      ],
      'http_errors' => FALSE,
    ]);
    $response = json_decode($request->getBody(), TRUE);
    dump(var_export($response, TRUE));

    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    /** @var \Drupal\Core\Language\LanguageManagerInterface $language_manager */
    $language_manager = \Drupal::service('language_manager');
    $languages = $language_manager->getLanguages();
    $this->assertSame(2, count($languages));

    // Check the properties of the language.
    $request = $this->client->get(Url::fromRoute('lingotek.dashboard_endpoint', ['code' => 'es_ES'])->setAbsolute()->toString(), [
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
      ],
      'http_errors' => FALSE,
    ]);
    $response = json_decode($request->getBody(), TRUE);
    $this->assertSame('GET', $response['method']);
    $this->assertSame('es', $response['xcode']);
    $this->assertSame('es_ES', $response['locale']);
    $this->assertSame(1, $response['active']);
    $this->assertSame(1, $response['enabled']);

    $language = ConfigurableLanguage::load('es');
    $this->assertSame($language->getThirdPartySetting('lingotek', 'disabled', NULL), FALSE, 'The Spanish language is enabled');

    $request = $this->client->delete($url, [
      'body' => http_build_query(['code' => 'es_ES']),
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/x-www-form-urlencoded',
      ],
      'http_errors' => FALSE,
    ]);
    $response = json_decode($request->getBody(), TRUE);
    $this->assertSame('DELETE', $response['method']);
    $this->assertSame('es', $response['language']);
    $this->assertSame('Language disabled: es_ES', $response['message']);

    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $languages = $language_manager->getLanguages();
    $this->assertSame(2, count($languages), 'Spanish language is disabled, but not deleted.');

    $language = ConfigurableLanguage::load('es');
    $this->assertSame($language->getThirdPartySetting('lingotek', 'disabled', NULL), TRUE, 'The Spanish language is disabled');

    // Check the properties of the language.
    $request = $this->client->get(Url::fromRoute('lingotek.dashboard_endpoint', ['code' => 'es_ES'])->setAbsolute()->toString(), [
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
      ],
      'http_errors' => FALSE,
    ]);
    $response = json_decode($request->getBody(), TRUE);
    $this->assertSame('GET', $response['method']);
    $this->assertSame('es', $response['xcode']);
    $this->assertSame('es_ES', $response['locale']);
    $this->assertSame(0, $response['active']);
    $this->assertSame(1, $response['enabled']);

    $post = [
      'code' => 'es_ES',
      'language' => 'Spanish (Spain)',
      'native' => 'Español',
      'direction' => '',
    ];
    $request = $this->client->post($url, [
      'body' => http_build_query($post),
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/x-www-form-urlencoded',
      ],
      'http_errors' => FALSE,
    ]);
    $response = json_decode($request->getBody(), TRUE);
    $this->assertSame('POST', $response['method']);
    $this->assertSame('es', $response['xcode']);
    $this->assertSame('es_ES', $response['locale']);
    $this->assertSame(1, $response['active']);
    $this->assertSame(1, $response['enabled']);

    // Check the properties of the language.
    $request = $this->client->get(Url::fromRoute('lingotek.dashboard_endpoint', ['code' => 'es_ES'])->setAbsolute()->toString(), [
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
      ],
      'http_errors' => FALSE,
    ]);
    $response = json_decode($request->getBody(), TRUE);
    $this->assertSame('GET', $response['method']);
    $this->assertSame('es', $response['xcode']);
    $this->assertSame('es_ES', $response['locale']);
    $this->assertSame(1, $response['active']);
    $this->assertSame(1, $response['enabled']);

    $languages = $language_manager->getLanguages();
    $this->assertSame(2, count($languages), 'Spanish language is enabled again, no new languages added.');

    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $language = ConfigurableLanguage::load('es');
    $this->assertSame($language->getThirdPartySetting('lingotek', 'disabled', NULL), FALSE, 'The Spanish language is enabled');
  }

  /**
   * Tests that disabled language appear as disabled in stats.
   */
  public function testDisabledLanguageInStats() {
    $url = Url::fromRoute('lingotek.dashboard_endpoint')->setAbsolute()->toString();
    // Add a language.
    $post = [
      'code' => 'es_ES',
      'language' => 'Spanish (Spain)',
      'native' => 'Español (España)',
      'direction' => '',
    ];
    $request = $this->client->post($url, [
      'body' => http_build_query($post),
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/x-www-form-urlencoded',
      ],
      'http_errors' => FALSE,
    ]);
    $response = json_decode($request->getBody(), TRUE);
    $this->assertSame('POST', $response['method']);
    $this->assertSame('es', $response['xcode']);
    $this->assertSame('es_ES', $response['locale']);
    $this->assertSame(1, $response['active']);
    $this->assertSame(1, $response['enabled']);

    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    /** @var \Drupal\Core\Language\LanguageManagerInterface $language_manager */
    $language_manager = \Drupal::service('language_manager');
    $languages = $language_manager->getLanguages();
    $this->assertSame(2, count($languages));

    // Check the stats.
    $request = $this->client->get($url, [
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/x-www-form-urlencoded',
      ],
      'http_errors' => FALSE,
    ]);
    $response = json_decode($request->getBody(), TRUE);
    $this->assertSame('GET', $response['method']);
    $this->assertSame(2, $response['count']);
    $this->assertSame('en', $response['languages']['en_US']['xcode']);
    $this->assertSame(1, $response['languages']['en_US']['active']);
    $this->assertSame(1, $response['languages']['en_US']['enabled']);
    $this->assertSame('es', $response['languages']['es_ES']['xcode']);
    $this->assertSame(1, $response['languages']['es_ES']['active']);
    $this->assertSame(1, $response['languages']['es_ES']['enabled']);

    // Disable Spanish.
    $request = $this->client->delete($url, [
      'body' => http_build_query(['code' => 'es_ES']),
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/x-www-form-urlencoded',
      ],
      'http_errors' => FALSE,
    ]);
    $response = json_decode($request->getBody(), TRUE);
    $this->assertSame('DELETE', $response['method']);
    $this->assertSame('es', $response['language']);
    $this->assertSame('Language disabled: es_ES', $response['message']);

    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    // Check the stats.
    $request = $this->client->get($url, [
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/x-www-form-urlencoded',
      ],
      'http_errors' => FALSE,
    ]);
    $response = json_decode($request->getBody(), TRUE);
    $this->assertSame('GET', $response['method']);
    $this->assertSame(2, $response['count']);
    $this->assertSame('en', $response['languages']['en_US']['xcode']);
    $this->assertSame(1, $response['languages']['en_US']['active']);
    $this->assertSame(1, $response['languages']['en_US']['enabled']);
    $this->assertSame('es', $response['languages']['es_ES']['xcode']);
    $this->assertSame(0, $response['languages']['es_ES']['active']);
    $this->assertSame(1, $response['languages']['es_ES']['enabled']);
  }

  /**
   * Tests that there is a message when there are UI translations available.
   */
  public function testTranslationsAvailable() {
    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    // One language added, there are missing translations.
    $this->drupalGet('admin/lingotek');
    $this->assertSession()->responseContains(t('Missing translations for: @languages. See the <a href=":updates">Available translation updates</a> page for more information.', ['@languages' => t('Spanish'), ':updates' => Url::fromRoute('locale.translate_status')->toString()]));

    // Override Drupal core translation status as 'up-to-date'.
    $status = locale_translation_get_status();
    $status['drupal']['es'] = new \stdClass();
    $status['drupal']['es']->type = 'current';
    \Drupal::keyValue('locale.translation_status')->set('drupal', $status['drupal']);

    // There are no missing translations, translations are current.
    $this->drupalGet('admin/lingotek');
    $this->assertSession()->responseNotContains(t('Missing translations for: @languages. See the <a href=":updates">Available translation updates</a> page for more information.', ['@languages' => t('Spanish'), ':updates' => Url::fromRoute('locale.translate_status')->toString()]));

    // Set lingotek module to have a local translation available.
    $status = locale_translation_get_status();
    $status['lingotek']['es'] = new \stdClass();
    $status['lingotek']['es']->type = 'local';
    \Drupal::keyValue('locale.translation_status')->set('lingotek', $status['lingotek']);

    // There are no missing translations, translations are local.
    $this->drupalGet('admin/lingotek');
    $this->assertSession()->responseNotContains(t('Missing translations for: @languages. See the <a href=":updates">Available translation updates</a> page for more information.', ['@languages' => t('Spanish'), ':updates' => Url::fromRoute('locale.translate_status')->toString()]));
  }

  /**
   * Ensure endpoint url is relative.
   */
  public function testDashboardEndpointUrlIsRelative() {
    $basepath = \Drupal::request()->getBasePath();
    $this->drupalGet('/admin/lingotek');
    $drupalSettings = $this->getDrupalSettings();
    // Using an absolute url can be problematic in https environments, ensure we
    // use a relative one.
    $this->assertEquals($basepath . '/admin/lingotek/dashboard_endpoint', $drupalSettings['lingotek']['cms_data']['endpoint_url']);
  }

  /**
   * Ensure language without locale doesn't mess with the response.
   */
  public function testEndpointResponseWithEmptyLocale() {
    $basepath = \Drupal::request()->getBasePath();

    $language = ConfigurableLanguage::create([
      'id' => 'en-hk',
      'name' => 'English (Hong-Kong)',
    ]);
    $language->setThirdPartySetting('lingotek', 'locale', '');
    $language->save();

    $url = Url::fromRoute('lingotek.dashboard_endpoint')->setAbsolute()->toString();

    // Check the stats.
    $request = $this->client->get($url, [
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/x-www-form-urlencoded',
      ],
      'http_errors' => FALSE,
    ]);
    $responseData = Json::decode($request->getBody());
    $this->assertEquals($responseData['count'], 2);
    $this->assertCount(2, $responseData['languages']);
    // We default to the known code, which is only the langcode.
    $this->assertEquals($responseData['languages']['en-hk']['active'], 1);
    $this->assertEquals($responseData['languages']['en_US']['active'], 1);
  }

}
