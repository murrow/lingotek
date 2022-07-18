<?php

namespace Drupal\lingotek\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Entity\EntityInterface;

/**
 * Event that is fired when we are rendering target secondary actions.
 */
class TargetSecondaryActionsEvent extends Event {

  const EVENT_NAME = 'lingotek.target_secondary_actions_event';

  /**
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected EntityInterface $entity;

  /**
   * @var string
   */
  protected $targetStatus;

  /**
   * @var string
   */
  protected $langcode;

  /**
   * @var array
   */
  protected $actions;

  /**
   * Constructs the object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $target_status
   *   The target status.
   * @param string $langcode
   *   The language code.
   * @param array $actions
   *   The actions.
   */
  public function __construct(EntityInterface $entity, string $target_status, string $langcode, array &$actions) {
    $this->entity = $entity;
    $this->targetStatus = $target_status;
    $this->langcode = $langcode;
    $this->actions = &$actions;
  }

  /**
   * @return array
   */
  public function &getActions(): array {
    return $this->actions;
  }

  /**
   * @return string
   */
  public function getLangcode(): string {
    return $this->langcode;
  }

  /**
   * @return EntityInterface
   */
  public function getEntity(): EntityInterface {
    return $this->entity;
  }

  /**
   * @return string
   */
  public function getTargetStatus(): string {
    return $this->targetStatus;
  }
}
