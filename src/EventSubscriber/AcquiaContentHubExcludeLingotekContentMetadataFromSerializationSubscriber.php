<?php

namespace Drupal\lingotek\EventSubscriber;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\acquia_contenthub\Event\ExcludeEntityFieldEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Exclude Lingotek field from serialization.
 */
class AcquiaContentHubExcludeLingotekContentMetadataFromSerializationSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static $priority = 1001;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events['acquia_contenthub_exclude_entity_field'][] =
      ['excludeContentField', self::$priority];
    return $events;
  }

  /**
   * Sets the "exclude" flag.
   *
   * @param \Drupal\acquia_contenthub\Event\ExcludeEntityFieldEvent $event
   *   The content entity field serialization event.
   */
  public function excludeContentField(ExcludeEntityFieldEvent $event) {
    if ($this->shouldExclude($event)) {
      $event->exclude();
      $event->stopPropagation();
    }
  }

  /**
   * Prevent entity fields from being added to the serialized output.
   *
   * @param \Drupal\acquia_contenthub\Event\ExcludeEntityFieldEvent $event
   *   The content entity field serialization event.
   */
  public function shouldExclude(ExcludeEntityFieldEvent $event): bool {
    $field = $event->getField();
    return !$this->includeField($field);
  }

  /**
   * Whether we should include this field in the serialization.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The entity field.
   *
   * @return bool
   *   TRUE if we should include the field, FALSE otherwise.
   */
  protected function includeField(FieldItemListInterface $field) {
    $definition = $field->getFieldDefinition();
    if ($definition->getType() === 'entity_reference' && $field->getSetting('target_type') === 'lingotek_content_metadata') {
      return FALSE;
    }
    return TRUE;
  }

}
