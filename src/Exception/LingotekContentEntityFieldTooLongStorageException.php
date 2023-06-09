<?php

namespace Drupal\lingotek\Exception;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * An exception for issues when storing content entity translations.
 *
 * @package Drupal\lingotek\Exception
 */
class LingotekContentEntityFieldTooLongStorageException extends LingotekContentEntityStorageException {

  public function __construct(ContentEntityInterface $entity, string $field_name, $message = NULL, $code = 0) {
    parent::__construct($entity, NULL, $message, $code);
    $this->table = $field_name;
  }

}
