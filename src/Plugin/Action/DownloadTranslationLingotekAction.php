<?php

namespace Drupal\lingotek\Plugin\Action;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\Exception\LingotekApiException;
use Drupal\lingotek\Exception\LingotekDocumentNotFoundException;

/**
 * Download Lingotek translation of a content entity for one language.
 *
 * @Action(
 *   id = "entity:lingotek_download_translation_action",
 *   action_label = @Translation("Download @entity_label translation to Lingotek for @language"),
 *   category = "Lingotek",
 *   deriver = "Drupal\lingotek\Plugin\Action\Derivative\ContentEntityLingotekActionDeriver",
 * )
 */
class DownloadTranslationLingotekAction extends LingotekContentEntityConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $result = FALSE;

    $entityTypeBundleInfo = \Drupal::service('entity_type.bundle.info');
    $bundleInfos = $entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
    if (!$entity->getEntityType()->isTranslatable() || !$bundleInfos[$entity->bundle()]['translatable']) {
      $this->messenger()->addWarning(t('Cannot download @type %label. That @bundle_label is not enabled for translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]));
      return FALSE;
    }
    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration */
    $lingotek_configuration = \Drupal::service('lingotek.configuration');
    if (!$lingotek_configuration->isEnabled($entity->getEntityTypeId(), $entity->bundle())) {
      $this->messenger()->addWarning(t('Cannot download @type %label. That @bundle_label is not enabled for Lingotek translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]));
      return FALSE;
    }

    $configuration = $this->getConfiguration();
    $langcode = $configuration['language'];

    $language = ConfigurableLanguage::load($langcode);
    if (!$lingotek_configuration->isLanguageEnabled($language)) {
      $this->messenger()->addWarning(t('Cannot download translation for language @language (%langcode). That language is not enabled for Lingotek translation.',
        ['@language' => $language->getName(), '%langcode' => $langcode]));
      return FALSE;
    }

    try {
      $locale = $this->languageLocaleMapper->getLocaleForLangcode($langcode);
      $result = $this->translationService->downloadDocument($entity, $locale);
    }
    catch (LingotekDocumentNotFoundException $exc) {
      $this->messenger()
        ->addError(t('Document @entity_type %title was not found. Please upload again.', [
          '@entity_type' => $entity->getEntityTypeId(),
          '%title' => $entity->label(),
        ]));
    }
    catch (LingotekApiException $exception) {
      $this->messenger()
        ->addError(t('The download for @entity_type %title translation failed. Please try again.', [
          '@entity_type' => $entity->getEntityTypeId(),
          '@langcode' => $langcode,
          '%title' => $entity->label(),
        ]));
    }
    return $result;
  }

}
