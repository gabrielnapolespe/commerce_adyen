<?php

namespace Drupal\commerce_adyen_dropin\Services;

use Adyen\Client;
use Drupal\commerce_order\Entity\Order;
use Adyen\Service\Checkout\PaymentsApi;
use Adyen\Model\Checkout\Amount;
use Adyen\Model\Checkout\CreateCheckoutSessionResponse;
use Adyen\Model\Checkout\CreateCheckoutSessionRequest;
use Drupal\node\Entity\Node;

/**
 * @file
 * Service Example
 */

class AdyenService {

  /**
   * Constructor.
   */
  public function __construct() {}

  /**
   * Creates a payment session.
   */
  public function paymentSession(string $paymentGatewayId, Order $order): CreateCheckoutSessionResponse {
    // Load the pluguin configuration.
    $paymentGatewayId = $order->get('payment_gateway')->target_id;
    $paymentGatewayStorage =  \Drupal::entityTypeManager()->getStorage('commerce_payment_gateway');
    $paymentGateway = $paymentGatewayStorage->load($paymentGatewayId);
    $paymentConfiguration = $paymentGateway->getPluginConfiguration();
    // Set up the client and service
    $client = new Client();
    $client->setXApiKey($paymentConfiguration['api_key']);
    $client->setEnvironment($paymentConfiguration['mode'], $paymentConfiguration['prefix']);
    $client->setTimeout(30);
    $service = new PaymentsApi($client);

    // Create an Amount object.
    $order_price = $order->getTotalPrice();
    $price = $order_price->getNumber();
    $price = round($price, 2);
    $price = number_format($price, 2, '','');
    $amount = new Amount(['currency' => $order_price->getCurrencyCode(), 'value' => $price]);

    // Create the CreateCheckoutSessionRequest object.
    $sessionRequest = new CreateCheckoutSessionRequest();
    $sessionRequest->setMerchantAccount($paymentConfiguration['merchant_account']);
    $sessionRequest->setAmount($amount);

    $sessionRequest->setshopperLocale($this->getLangCode());
    $sessionRequest->setCountryCode('ES');
    $sessionRequest->setReference($order->id());

    // Set the return url for offsite-payments
    $host = \Drupal::request()->getSchemeAndHttpHost();
    $sessionRequest->setReturnUrl($host . '/checkout/'. $order->id() . '/payment');

    // Send the session request
    $result = $service->sessions($sessionRequest);

    return $result;

  }

    /**
   * Gets langcode.
   */
  public function getLangCode(): String {

    //Get current language (ex. /es-es/)
    $langCode = \Drupal::languageManager()->getCurrentLanguage()->getId();

    // Return Adyen format language code
    if ($langCode === 'es-es' || $langCode === 'es') {

      $langCode = 'es-ES';
    }
    else {

      $langCode = 'en-GB';
    }

    return $langCode;
  }
}