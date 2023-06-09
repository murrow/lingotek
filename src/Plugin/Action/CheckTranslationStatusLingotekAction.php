<?php

namespace Drupal\lingotek\Plugin\Action;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\Exception\LingotekApiException;
use Drupal\lingotek\Exception\LingotekDocumentNotFoundException;

/**
 * Check Lingotek translation status of a content entity for one language.
 *
 * @Action(
 *   id = "entity:lingotek_check_translation_action",
 *   action_label = @Translation("Check @entity_label translation status to Lingotek for @language"),
 *   category = "Lingotek",
 *   deriver = "Drupal\lingotek\Plugin\Action\Derivative\ContentEntityLingotekActionDeriver",
 * )
 */
class CheckTranslationStatusLingotekAction extends LingotekContentEntityConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $result = FALSE;
    $entityTypeBundleInfo = \Drupal::service('entity_type.bundle.info');
    $bundleInfos = $entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
    if (!$entity->getEntityType()->isTranslatable() || !$bundleInfos[$entity->bundle()]['translatable']) {
      $this->messenger()->addWarning(t('Cannot check translation for @type %label. That @bundle_label is not enabled for translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]));
      return FALSE;
    }
    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration */
    $lingotek_configuration = \Drupal::service('lingotek.configuration');
    if (!$lingotek_configuration->isEnabled($entity->getEntityTypeId(), $entity->bundle())) {
      $this->messenger()->addWarning(t('Cannot check translation for @type %label. That @bundle_label is not enabled for Lingotek translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]));
      return FALSE;
    }
    $configuration = $this->getConfiguration();
    $langcode = $configuration['language'];

    $language = ConfigurableLanguage::load($langcode);
    if (!$lingotek_configuration->isLanguageEnabled($language)) {
      $this->messenger()->addWarning(t('Cannot check status for language @language (%langcode). That language is not enabled for Lingotek translation.',
        ['@language' => $language->getName(), '%langcode' => $langcode]));
      return FALSE;
    }

    try {
      $result = $this->translationService->checkTargetStatus($entity, $langcode);
    }
    catch (LingotekDocumentNotFoundException $exc) {
      $this->messenger()
        ->addError(t('Document @entity_type %title was not found. Please upload again.', [
          '@entity_type' => $entity->getEntityTypeId(),
          '%title' => $entity->label(),
        ]));
    }
    catch (LingotekApiException $exception) {
      $this->messenger()->addError(t('The request for @entity_type %title translation status failed. Please try again.', [
          '@entity_type' => $entity->getEntityTypeId(),
          '@langcode' => $langcode,
          '%title' => $entity->label(),
        ]));
    }
    return $result;
  }

}
