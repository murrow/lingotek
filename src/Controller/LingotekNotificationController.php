<?php

namespace Drupal\lingotek\Controller;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Render\HtmlResponse;
use Drupal\lingotek\LanguageLocaleMapperInterface;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekConfigTranslationServiceInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Drupal\lingotek\LingotekInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns responses for lingotek module setup routes.
 */
class LingotekNotificationController extends LingotekControllerBase {

  /**
   * The Lingotek configuration service.
   *
   * @var \Drupal\lingotek\LingotekConfigurationServiceInterface
   */
  protected $lingotekConfiguration;

  /**
   * The Lingotek content translation service.
   *
   * @var \Drupal\lingotek\LingotekContentTranslationServiceInterface
   */
  protected $lingotekContentTranslation;

  /**
   * The Lingotek configuration translation service.
   *
   * @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface
   */
  protected $lingotekConfigTranslation;

  /**
   * Constructs a LingotekControllerBase object.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The Request instance.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\lingotek\LingotekInterface $lingotek
   *   The lingotek service.
   * @param \Drupal\lingotek\LanguageLocaleMapperInterface $language_locale_mapper
   *   The language-locale mapper.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration
   *   The Lingotek configuration service.
   * @param \Drupal\lingotek\LingotekContentTranslationServiceInterface $content_translation_service
   *   The Lingotek content translation service.
   * @param \Drupal\lingotek\LingotekConfigTranslationServiceInterface $config_translation_service
   *   The Lingotek config translation service.
   */
  public function __construct(Request $request, ConfigFactoryInterface $config_factory, LingotekInterface $lingotek, LanguageLocaleMapperInterface $language_locale_mapper, FormBuilderInterface $form_builder, LoggerInterface $logger, LingotekConfigurationServiceInterface $lingotek_configuration, LingotekContentTranslationServiceInterface $content_translation_service, LingotekConfigTranslationServiceInterface $config_translation_service) {
    parent::__construct($request, $config_factory, $lingotek, $language_locale_mapper, $form_builder, $logger);
    $this->lingotekConfiguration = $lingotek_configuration;
    $this->lingotekContentTranslation = $content_translation_service;
    $this->lingotekConfigTranslation = $config_translation_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('config.factory'),
      $container->get('lingotek'),
      $container->get('lingotek.language_locale_mapper'),
      $container->get('form_builder'),
      $container->get('logger.channel.lingotek'),
      $container->get('lingotek.configuration'),
      $container->get('lingotek.content_translation'),
      $container->get('lingotek.config_translation')
    );
  }

  public function endpoint(Request $request) {
    $translation_service = $this->lingotekContentTranslation;

    $request_method = $request->getMethod();
    $http_status_code = Response::HTTP_ACCEPTED;
    $type = $request->query->get('type');
    $result = [];
    $messages = [];
    $security_token = $request->query->get('security_token');
    if ($security_token == 1) {
      $http_status_code = Response::HTTP_ACCEPTED;
    }
    switch ($type) {

      // all translations for all documents have been completed for the project
      case 'project':
        // ex. ?project_id=103956f4-17cf-4d79-9d15-5f7b7a88dee2&progress=100&type=project
        break;

      case 'document':

        break;
      case 'document_archived':
        $entity = $this->getEntity($request->query->get('document_id'));
        if ($entity) {
          if ($entity instanceof ConfigEntityInterface) {
            $translation_service = $this->lingotekConfigTranslation;
          }
          // We need to unset the document id first, so there's no cancelling
          // call to the TMS.
          $translation_service->setDocumentId($entity, NULL);
          $translation_service->deleteMetadata($entity);

          $http_status_code = Response::HTTP_OK;
          $messages[] = new FormattableMarkup('Document @label was archived in Lingotek.', [
            '@label' => $entity->label(),
          ]);
        }
        break;
      case 'document_cancelled':
        $documentId = $request->query->get('document_id');
        $entity = $this->getEntity($documentId);
        if ($entity) {
          if ($entity instanceof ConfigEntityInterface) {
            $translation_service = $this->lingotekConfigTranslation;
            $this->lingotekConfiguration->setConfigEntityProfile($entity, NULL);
          }
          elseif ($entity instanceof ContentEntityInterface) {
            $this->lingotekConfiguration->setProfile($entity, NULL);
          }
          $http_status_code = Response::HTTP_OK;

          $translation_service->setDocumentId($entity, NULL);
          $translation_service->setSourceStatus($entity, Lingotek::STATUS_CANCELLED);
          $translation_service->setTargetStatuses($entity, Lingotek::STATUS_CANCELLED);

          $this->logger->log(LogLevel::DEBUG, 'Document @label cancelled in TMS.', [
            '@label' => $entity->label(),
          ]);
          $messages[] = new FormattableMarkup('Document @label cancelled in TMS.', [
            '@label' => $entity->label(),
          ]);
        }
        else {
          $http_status_code = Response::HTTP_NO_CONTENT;
          $messages[] = "Document not found.";
        }
        break;
      // a document has uploaded and imported successfully for document_id
      case 'document_uploaded':
        $entity = $this->getEntity($request->query->get('document_id'));
        /** @var \Drupal\lingotek\Entity\LingotekProfile $profile */
        $profile = $this->getProfile($entity);
        if ($entity) {
          if ($entity instanceof ConfigEntityInterface) {
            $translation_service = $this->lingotekConfigTranslation;
          }
          $http_status_code = Response::HTTP_OK;
          $translation_service->setSourceStatus($entity, Lingotek::STATUS_CURRENT);
          $result['request_translations'] = ($profile->hasAutomaticUpload()) ?
             $translation_service->requestTranslations($entity) : [];
        }
        else {
          $http_status_code = Response::HTTP_NO_CONTENT;
          $messages[] = "Document not found.";
        }
        break;
      case 'document_updated':
        $entity = $this->getEntity($request->query->get('document_id'));
        if ($entity) {
          if ($entity instanceof ConfigEntityInterface) {
            $translation_service = $this->lingotekConfigTranslation;
          }
          $http_status_code = Response::HTTP_OK;
          $translation_service->setSourceStatus($entity, Lingotek::STATUS_CURRENT);
        }
        else {
          $http_status_code = Response::HTTP_NO_CONTENT;
          $messages[] = "Document not found.";
        }
        break;
      case 'import_failure':
        $prevDocumentId = $request->query->get('prev_document_id');
        $documentId = $request->query->get('document_id');
        $entity = $this->getEntity($documentId);
        if ($entity) {
          if ($entity instanceof ConfigEntityInterface) {
            $translation_service = $this->lingotekConfigTranslation;
          }
          $http_status_code = Response::HTTP_OK;
          // We update the document id to the previous one no matter what. If it
          // was a new document, we want to set the document id to NULL anyway.
          $translation_service->setDocumentId($entity, $prevDocumentId);
          $translation_service->setSourceStatus($entity, Lingotek::STATUS_ERROR);
          $this->logger->log(LogLevel::DEBUG, 'Document import for entity @label failed. Reverting @documentId to previous id @prevDocumentId', [
            '@label' => $entity->label(),
            '@documentId' => $documentId,
            '@prevDocumentId' => $prevDocumentId ?: '(NULL)',
          ]);
          $messages[] = new FormattableMarkup('Document import for entity @label failed. Reverting @documentId to previous id @prevDocumentId', [
            '@label' => $entity->label(),
            '@documentId' => $documentId,
            '@prevDocumentId' => $prevDocumentId ?: '(NULL)',
          ]);
        }
        else {
          $http_status_code = Response::HTTP_NO_CONTENT;
          $messages[] = "Document not found.";
        }
        break;
      case 'target_cancelled':
        $documentId = $request->query->get('document_id');
        $locale = $request->query->get('locale');
        $entity = $this->getEntity($documentId);
        if ($entity) {
          if ($entity instanceof ConfigEntityInterface) {
            $translation_service = $this->lingotekConfigTranslation;
          }
          $http_status_code = Response::HTTP_OK;

          $langcode = $this->languageLocaleMapper->getConfigurableLanguageForLocale($locale)
            ->id();
          $translation_service->setTargetStatus($entity, $langcode, Lingotek::STATUS_CANCELLED);

          $this->logger->log(LogLevel::DEBUG, 'Document @label target @locale cancelled in TMS.', [
            '@label' => $entity->label(),
            '@locale' => $locale,
          ]);
          $messages[] = new FormattableMarkup('Document @label target @locale cancelled in TMS.', [
            '@label' => $entity->label(),
            '@locale' => $locale,
          ]);
        }
        else {
          $http_status_code = Response::HTTP_NO_CONTENT;
          $messages[] = "Document not found.";
        }
        break;
      case 'target_deleted':
        /**
         * array(
         * 'community_id' => 'my_community_id',
         * 'complete' => 'true',
         * 'deleted_at' => '1536115104487',
         * 'deleted_by_user_id' => 'user_hash',
         * 'deleted_by_user_login' => 'user@example.com',
         * 'deleted_by_user_name' => 'Name Surname',
         * 'doc_cts' => '1536115021300',
         * 'doc_domain_type' => 'http://example.com',
         * 'doc_region' => '',
         * 'doc_status' => 'COMPLETE',
         * 'documentId' => 'document_tms_id',
         * 'document_id' => 'document_id',
         * 'locale' => 'ca_ES',
         * 'locale_code' => 'ca-ES',
         * 'original_project_id' => '0',
         * 'progress' => '100',
         * 'projectId' => 'project_tms_id',
         * 'project_id' => 'project_id_hash',
         * 'status' => 'COMPLETE',
         * 'targetId' => 'target_tms_id',
         * 'target_id' => 'target_hash',
         * 'type' => 'target_deleted',
         * )
         */
        $entity = $this->getEntity($request->query->get('document_id'));
        if ($entity !== NULL) {
          if ($entity instanceof ConfigEntityInterface) {
            $translation_service = $this->lingotekConfigTranslation;
          }
          $locale = $request->query->get('locale');
          $langcode = $this->languageLocaleMapper->getConfigurableLanguageForLocale($locale)
            ->id();
          $user_login = $request->query->get('deleted_by_user_login');
          $translation_service->setTargetStatus($entity, $langcode, Lingotek::STATUS_UNTRACKED);
          $this->logger->log(LogLevel::DEBUG, 'Target @locale for entity @label deleted by @user_login', [
            '@locale' => $locale,
            '@user_login' => $user_login,
            '@label' => $entity->label(),
          ]);
          $http_status_code = Response::HTTP_OK;
          $messages[] = new FormattableMarkup('Target @locale for entity @label deleted by @user_login', [
            '@locale' => $locale,
            '@user_login' => $user_login,
            '@label' => $entity->label(),
          ]);

        }
        else {
          $http_status_code = Response::HTTP_NO_CONTENT;
          $document_id = $request->query->get('document_id');
          $user_login = $request->query->get('deleted_by_user_login');
          $locale = $request->query->get('locale');
          $this->logger->log(LogLevel::WARNING, 'Target @locale for document @document_id deleted by @user_login in the TMS, but document not found on the system.', [
            '@locale' => $locale,
            '@user_login' => $user_login,
            '@document_id' => $document_id,
          ]);
        }
        break;
      case 'document_deleted':
        /**
         * array(
         * 'community_id' => 'my_community_id',
         * 'complete' => 'true',
         * 'deleted_at' => '1536171165274',
         * 'deleted_by_user_id' => 'user_hash',
         * 'deleted_by_user_login' => 'user@example.com',
         * 'deleted_by_user_name' => 'Name Surname',
         * 'documentId' => 'document_tms_id',
         * 'document_id' => 'document_id',
         * 'original_project_id' => '0',
         * 'progress' => '100',
         * 'projectId' => 'project_tms_id',
         * 'project_id' => 'project_id_hash',
         * 'type' => 'document_deleted',
         * )
         */
        $entity = $this->getEntity($request->query->get('document_id'));
        if ($entity !== NULL) {
          if ($entity instanceof ConfigEntityInterface) {
            $translation_service = $this->lingotekConfigTranslation;
          }
          $user_login = $request->query->get('deleted_by_user_login');
          $translation_service->deleteMetadata($entity);
          $this->logger->log(LogLevel::DEBUG, 'Document for entity @label deleted by @user_login in the TMS.', [
            '@user_login' => $user_login,
            '@label' => $entity->label(),
          ]);
          $http_status_code = Response::HTTP_OK;
          $messages[] = new FormattableMarkup('Document for entity @label deleted by @user_login in the TMS.', [
            '@user_login' => $user_login,
            '@label' => $entity->label(),
          ]);
        }
        else {
          $http_status_code = Response::HTTP_NO_CONTENT;
          $document_id = $request->query->get('document_id');
          $user_login = $request->query->get('deleted_by_user_login');
          $this->logger->log(LogLevel::WARNING, 'Document @document_id deleted by @user_login in the TMS, but not found on the system.', [
            '@user_login' => $user_login,
            '@document_id' => $document_id,
          ]);
        }
        break;
      case 'phase':
        // translation (i.e., chinese) has been completed for a document
      case 'target':
        // TO-DO: download target for locale_code and document_id (also, progress and complete params can be used as needed)
        // ex. ?project_id=103956f4-17cf-4d79-9d15-5f7b7a88dee2&locale_code=de-DE&document_id=bbf48a7b-b201-47a0-bc0e-0446f9e33a2f&complete=true&locale=de_DE&progress=100&type=target
        $document_id = $request->query->get('document_id');
        $locale = $request->query->get('locale');
        $langcode = $this->languageLocaleMapper->getConfigurableLanguageForLocale($locale)
          ->id();

        $lock = \Drupal::lock();
        $lock_name = __FUNCTION__ . ':' . $document_id;

        do {
          if ($lock->lockMayBeAvailable($lock_name)) {
            if ($held = $lock->acquire($lock_name)) {
              break;
            }
          }
          $lock->wait($lock_name, rand(1, 12));
        } while (TRUE);

        try {
          $entity = $this->getEntity($document_id);
          /** @var \Drupal\lingotek\Entity\LingotekProfile $profile */
          $profile = $this->getProfile($entity);
          if ($entity) {
            if ($entity instanceof ConfigEntityInterface) {
              $translation_service = $this->lingotekConfigTranslation;
            }
            $translation_service->setTargetStatus($entity, $langcode, Lingotek::STATUS_READY);

            if ($profile->hasAutomaticDownloadForTarget($langcode) && $profile->hasAutomaticDownloadWorker()) {
              $queue = \Drupal::queue('lingotek_downloader_queue_worker');
              $item = [
                'entity_type_id' => $entity->getEntityTypeId(),
                'entity_id' => $entity->id(),
                'locale' => $locale,
                'document_id' => $document_id,
              ];
              $result['download_queued'] = $queue->createItem($item);
            }
            elseif ($profile->hasAutomaticDownloadForTarget($langcode) && !$profile->hasAutomaticDownloadWorker()) {
              $result['download'] = $translation_service->downloadDocument($entity, $locale);
            }
            else {
              $result['download'] = FALSE;
            }
            if (isset($result['download']) && $result['download']) {
              $messages[] = "Document downloaded.";
              $http_status_code = Response::HTTP_OK;
            }
            elseif (isset($result['download_queued']) && $result['download_queued']) {
              $messages[] = new FormattableMarkup('Download for target @locale in document @document has been queued.', [
                '@locale' => $locale,
                '@document' => $document_id,
              ]);
              $result['download_queued'] = TRUE;
              $http_status_code = Response::HTTP_OK;
            }
            else {
              $messages[] = new FormattableMarkup('No download for target @locale happened in document @document.', [
                '@locale' => $locale,
                '@document' => $document_id,
              ]);
              if (!$profile->hasAutomaticDownloadForTarget($langcode)) {
                $http_status_code = Response::HTTP_OK;
              }
              else {
                $http_status_code = Response::HTTP_SERVICE_UNAVAILABLE;
              }
            }
          }
          else {
            $http_status_code = Response::HTTP_NO_CONTENT;
            $messages[] = "Document not found.";
          }
        }
        catch (\Exception $exception) {
          $http_status_code = Response::HTTP_SERVICE_UNAVAILABLE;
          $messages[] = new FormattableMarkup('Download of target @locale for document @document failed', [
            '@locale' => $locale,
            '@document' => $document_id,
          ]);
        }
        finally {
          $lock->release($lock_name);
        }
        break;
      // ignore
      default:
        $http_status_code = Response::HTTP_ACCEPTED;
        return new HtmlResponse('It works, but nothing to look here.', $http_status_code);
      break;
    }

    $response = [
      'service' => 'notify',
      'method' => $request_method,
      'params' => $request->query->all(),
      'result' => $result,
      'messages' => $messages,
    ];

    return JsonResponse::create($response, $http_status_code)
      ->setMaxAge(0)
      ->setSharedMaxAge(0);
  }

  protected function getProfile($entity) {
    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $configuration_service */
    $configuration_service = \Drupal::service('lingotek.configuration');
    $profile = NULL;
    if ($entity instanceof ContentEntityInterface) {
      $profile = $configuration_service->getEntityProfile($entity, FALSE);
    }
    elseif ($entity instanceof ConfigEntityInterface) {
      $profile = $configuration_service->getConfigEntityProfile($entity, FALSE);
    }
    return $profile;
  }

  protected function getEntity($document_id) {
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $entity = $translation_service->loadByDocumentId($document_id);
    if ($entity === NULL) {
      /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
      $translation_service = \Drupal::service('lingotek.config_translation');
      $entity = $translation_service->loadByDocumentId($document_id);
    }
    return $entity;
  }

}
