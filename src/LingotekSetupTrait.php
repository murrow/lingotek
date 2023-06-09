<?php

namespace Drupal\lingotek;

/**
 * Useful methods for checking if Lingotek is already setup.
 */
trait LingotekSetupTrait {

  /**
   * A lingotek connector object
   *
   * @var \Drupal\lingotek\LingotekInterface
   */
  protected $lingotek;

  /**
   * Verify the Lingotek Translation module has been properly initialized.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|false
   *   A redirect response object, or FALSE if setup is complete.
   */
  protected function checkSetup() {
    if (!$this->setupCompleted()) {
      return $this->redirect('lingotek.setup_account');
    }
    return FALSE;
  }

  /**
   * Checks if Lingotek module is completely set up.
   *
   * @return boolean TRUE if connected, FALSE otherwise.
   */
  public function setupCompleted() {
    $accountConfig = $this->config('lingotek.account');
    $accessToken = $accountConfig->get('access_token');
    $loginId = $accountConfig->get('login_id');
    if (!empty($accessToken) && !empty($loginId)) {
      return TRUE;
    }
    return FALSE;
  }

}
