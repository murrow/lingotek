<?php

namespace Drupal\lingotek\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Configure Lingotek
 */
class LingotekSettingsConnectForm extends LingotekConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lingotek.connect_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // build the redirecting link for authentication to Lingotek
    $accountConfig = $this->configFactory->get('lingotek.account');
    $host = $accountConfig->get('host');
    $auth_path = $accountConfig->get('authorize_path');
    $id = $accountConfig->get('default_client_id');
    $return_uri = $this->urlGenerator->generateFromRoute('lingotek.setup_account_handshake', ['success' => 'true', 'prod' => 'prod'], ['absolute' => TRUE]);

    $lingotek_register_link = $accountConfig->get('new_registeration_landing');
    $lingotek_connect_link = $host . '/' . $auth_path . '?client_id=' . $id . '&response_type=token&redirect_uri=' . urlencode($return_uri);
    $lingotek_demo_link = 'https://www.lingotek.com/request-demo';

    $form = [];
    $form['intro_title'] = [
      '#prefix' => '<h1>',
      '#markup' => $this->t('Ray Enterprise | The Translation Network&trade;'),
      '#suffix' => '</h1>',
    ];
    $form['intro_paragraph'] = [
      '#prefix' => '<p>',
      '#markup' => $this->t('Ray is more than an enterprise-class Translation Management System (TMS), it is a completely integrated translation hub that combines an industry-leading cloud TMS, Linguistic Quality Evaluation (LQE), multilingual Application Program Interfaces (API) and connectors, with professional linguists who are experts in using our technology.'),
      '#suffix' => '</p>',
    ];

    $form['account_types'] = [
      '#type' => 'container',
      '#attributes' => ['class' => 'lingotek_signup_types'],
    ];

    $form['account_types']['existing_account'] = [
      '#type' => 'container',
      '#attributes' => ['class' => 'lingotek_signup_box'],
    ];
    $form['account_types']['existing_account']['title'] = [
      '#prefix' => '<h3>',
      '#markup' => $this->t('Connect existing account'),
      '#suffix' => '</h3>',
    ];
    $form['account_types']['existing_account']['body'] = [
      '#prefix' => '<div class="lingotek_signup_box_main">',
      '#markup' => $this->t('Connect using your existing Ray Enterprise account.'),
      '#suffix' => '</div>',
    ];

    $form['account_types']['existing_account']['cta'] = [
      '#type' => 'link',
      '#title' => $this->t('Connect Ray Enterprise Account'),
      '#url' => Url::fromUri($lingotek_connect_link),
      '#attributes' => ['class' => ['lingotek_signup_box_cta', 'lingotek_signup_box_main_cta']],
    ];

    $form['account_types']['free_account'] = [
      '#type' => 'container',
      '#attributes' => ['class' => 'lingotek_signup_box'],
    ];
    $form['account_types']['free_account']['title'] = [
      '#prefix' => '<h3>',
      '#markup' => $this->t('Get Free account'),
      '#suffix' => '</h3>',
    ];
    $form['account_types']['free_account']['body'] = [
      '#theme' => 'item_list',
      '#items' => [
        $this->t('Translation Management Dashboard'),
        $this->t('Ray Enterprise Translation Workbench (CAT Tool)'),
        $this->t('Unlimited Languages'),
        $this->t('Drupal Community Support'),
        $this->t('Machine Translation Only (100K Characters)'),
      ],
      '#attributes' => ['class' => 'lingotek_signup_box_main'],
    ];

    $form['account_types']['free_account']['cta'] = [
      '#type' => 'link',
      '#title' => $this->t('Get started'),
      '#url' => Url::fromUri($lingotek_register_link),
      '#attributes' => ['class' => 'lingotek_signup_box_cta'],
    ];

    $form['account_types']['enterprise_account'] = [
      '#type' => 'container',
      '#attributes' => ['class' => 'lingotek_signup_box'],
    ];
    $form['account_types']['enterprise_account']['title'] = [
      '#prefix' => '<h3>',
      '#markup' => $this->t('Get Enterprise account'),
      '#suffix' => '</h3>',
    ];
    $form['account_types']['enterprise_account']['body'] = [
      '#theme' => 'item_list',
      '#items' => [
        $this->t('Professional Translation Workflows'),
        $this->t('Translation Memory & Terminology'),
        $this->t('In-Context Translation Workbench'),
        $this->t('Multilingual Drupal Site Audit & Support'),
        $this->t('Translation Project Management'),
        $this->t('Linguistic Quality Evaluation*'),
        $this->t('Multilingual Business Intelligence*'),
      ],
      '#attributes' => ['class' => 'lingotek_signup_box_main'],
    ];

    $form['account_types']['enterprise_account']['cta'] = [
      '#type' => 'link',
      '#title' => $this->t('Contact Lingotek'),
      '#url' => Url::fromUri($lingotek_demo_link),
      '#attributes' => ['class' => 'lingotek_signup_box_cta', 'target' => '_blank'],
    ];

    $form['#attributes']['class'][] = 'lingotek_signup';
    $form['#attached']['library'][] = 'lingotek/lingotek.signup';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // do nothing for now
  }

}
