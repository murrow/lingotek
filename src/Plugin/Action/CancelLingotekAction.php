<?php

namespace Drupal\lingotek\Plugin\Action;

use Drupal\lingotek\Exception\LingotekApiException;

/**
 * Assigns ownership of a node to a user.
 *
 * @Action(
 *   id = "entity:lingotek_cancel_action",
 *   action_label = @Translation("Cancel @entity_label from Lingotek"),
 *   category = "Lingotek",
 *   deriver = "Drupal\lingotek\Plugin\Action\Derivative\ContentEntityLingotekActionDeriver",
 * )
 */
class CancelLingotekAction extends LingotekContentEntityActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $result = FALSE;
    try {
      /** @var \Drupal\node\NodeInterface $entity */
      $result = $this->translationService->cancelDocument($entity);
    }
    catch (LingotekApiException $exception) {
      $this->messenger()->addError(t('The cancellation of @entity_type %title failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
    }
    return $result;
  }

}
