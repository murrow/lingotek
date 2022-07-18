<?php

namespace Drupal\lingotek_secondary_actions_test\Subscriber;

use Drupal\Core\Url;
use Drupal\lingotek\Event\TargetSecondaryActionsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber to replace lingotek target secondary action links.
 */
class LingotekTargetSecondaryActionsSubscriber implements EventSubscriberInterface  {

  /**
   * Adding new action.
   */
  public function onSecondaryTargets(TargetSecondaryActionsEvent $event) {
    $actions = &$event->getActions();
    $actions['new_action']['title'] = t('Action from external source');
    $actions['new_action']['url'] = Url::fromUri('https://lingotek.com');

    if (isset($actions['workbench'])) {
      $actions['workbench']['title'] = t('Workbench action edited from external source');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      TargetSecondaryActionsEvent::EVENT_NAME => 'onSecondaryTargets',
    ];
  }
}
