<?php

namespace Drupal\commerce_adyen_dropin\PluginForm;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;

/**
 * Adyen Form.
 */
class AdyenPaymentForm extends BasePaymentOffsiteForm {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;
    /** @var \Drupal\commerce_order\Entity\Order $order */
    $order = $payment->getOrder();
    $orderId = $order->id();

    // Load the pluguin configuration.
    $paymentGatewayId = $order->get('payment_gateway')->target_id;
    $paymentGatewayStorage =  \Drupal::entityTypeManager()->getStorage('commerce_payment_gateway');
    $paymentGateway = $paymentGatewayStorage->load($paymentGatewayId);
    $paymentConfiguration = $paymentGateway->getPluginConfiguration();
    
    // Attach Adyen script and stylesheet
    $form['#attached']['library'][] = 'commerce_adyen_dropin/adyen_dropin_js';

    // If get variable 'sessionId' is null, means that the page has been loaded for the first time
    if (empty($_GET['sessionId'])) {
      // Call Adyen service and create payment session
      $service = \Drupal::service('adyen.service');
      $resultSession = $service->paymentSession($paymentGatewayId, $order);

      // Save returned data
      $sessionData = $resultSession->getSessionData();
      $sessionId = $resultSession->getId();
      $step = \Drupal::routeMatch()->getParameter('step');

      // Change %% references for values in session_config.js
      $content = $this->loadDropIn($paymentConfiguration, $sessionId, $sessionData, $orderId, $step);
    } else {
      // Save get variables given by offsite-payments and order id
      $sessionId = $_GET['sessionId'];
      $redirectResult = $_GET['redirectResult'];
      $step = \Drupal::routeMatch()->getParameter('step');

      // Change %% references for values in result_config.js
      $content = $this->loadResultDropIn($paymentConfiguration, $sessionId, $redirectResult, $orderId, $step);
    }

    // Load 'mount' function ($content) and #dropin-container div
    $form['drop-in-js'] = [
      '#markup' => Markup::create('<script>' . $content . '</script><div id="dropin-container"></div'),
    ];

    // Execute 'mount' function
    $form['#attached']['library'][] = 'commerce_adyen_dropin/mount';

    return $form;
  }

  /**
   *  Load session_config.js file and changes %% references to values.
   */
  public function loadDropIn(array $paymentConfiguration, string $sessionId, string $sessionData, string $orderId, string $step) {
    $modulePath = \Drupal::service('extension.list.module')->getPath('commerce_adyen_dropin');
    $file = $modulePath . '/js/session_config.js';
    $content = file_get_contents($file);
    $content = str_replace("%enviroment%", $paymentConfiguration['mode'], $content);
    $content = str_replace("%clientKey%", $paymentConfiguration['client_key'], $content);
    $content = str_replace("%sessionId%", $sessionId, $content);
    $content = str_replace("%sessionData%", $sessionData, $content);
    $content = str_replace("%orderId%", $orderId, $content);
    $content = str_replace("%step%", $step, $content);

    $urlOptions = [
      'absolute' => TRUE,
      'language' => \Drupal::languageManager()->getCurrentLanguage(),
    ];
    $siteUrl = Url::fromRoute('<front>', [], $urlOptions)->toString();

    $content = str_replace("%host%", $siteUrl, $content);

    return $content;
  }

  /**
   *  Load result_config.js file and changes %% references to values.
   */
    public function loadResultDropIn(array $paymentConfiguration, string $sessionId, string $redirectResult, string $orderId, string $step) {
    $modulePath = \Drupal::service('extension.list.module')->getPath('commerce_adyen_dropin');
    $file = $modulePath . '/js/result_config.js';
    $content = file_get_contents($file);
    $content = str_replace("%enviroment%", $paymentConfiguration['mode'], $content);
    $content = str_replace("%clientKey%", $paymentConfiguration['client_key'], $content);
    $content = str_replace("%sessionId%", $sessionId, $content);
    $content = str_replace("%redirectResult%", $redirectResult, $content);
    $content = str_replace("%orderId%", $orderId, $content);
    $content = str_replace("%step%", $step, $content);

    $urlOptions = [
      'absolute' => TRUE,
      'language' => \Drupal::languageManager()->getCurrentLanguage(),
    ];
    $siteUrl = Url::fromRoute('<front>', [], $urlOptions)->toString();

    $content = str_replace("%host%", $siteUrl, $content);

    return $content;
  }
}
