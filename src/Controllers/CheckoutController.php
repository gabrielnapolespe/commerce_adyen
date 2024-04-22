<?php

namespace Drupal\commerce_adyen_dropin\Controllers;

use Drupal\commerce_adyen_dropin\Services\AdyenLogger;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CheckoutController implements ContainerInjectionInterface {

  /**
   * The adyen logger service.
   */
  protected $adyenLogger;

  /**
   * Constructs a Controller.
   *
   * @param \Drupal\commerce_adyen_dropin\Services\AdyenLogger
   *   Adyen Logger Service.
   */
  public function __construct(AdyenLogger $adyenLogger) {
    $this->adyenLogger = $adyenLogger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('adyen.logger')
    );
  }


  /**
   *  Creates a redirect
   */
  public function redirect(RouteMatchInterface $route_match) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $route_match->getParameter('commerce_order');
    $stepId = $route_match->getParameter('step');
    $paymentGatewayId = $order->get('payment_gateway')->target_id;
    $this->adyenLogger->log($paymentGatewayId, 'notice', 'Controller: Enter redirect - Order ID: ' . $order->id());

    // Create url for the redirect
    $checkoutFlow = $order->get('checkout_flow')->first()->get('entity')->getTarget()->getValue();
    $checkoutFlowPlugin = $checkoutFlow->getPlugin();
    $redirectStepId = $checkoutFlowPlugin->getNextStepId($stepId);

    $url = Url::fromRoute('commerce_checkout.form', [
      'commerce_order' => $order->id(),
      'step' => $redirectStepId,
    ])->toString();

    $response = new RedirectResponse($url);

    return $response;
  }

  /**
   *  Creates a message
   */
  public function message($commerce_order) {
    // Get the payment gateway from order
    $storage = \Drupal::entityTypeManager()->getStorage('commerce_order');
    $order = $storage->load($commerce_order);

    $paymentGatewayId = $order->get('payment_gateway')->target_id;
    $this->adyenLogger->log($paymentGatewayId, 'notice', 'Controller: Enter message  - Order ID: ' . $commerce_order);

    // Load the pluguin configuration.
    $paymentGatewayId = $order->get('payment_gateway')->target_id;
    $paymentGateway =  \Drupal::entityTypeManager()->getStorage('commerce_payment_gateway')->load($paymentGatewayId);
    $paymentConfiguration = $paymentGateway->getPluginConfiguration();

    return [
      '#theme' => 'message_error',
      '#message' => $paymentConfiguration['error_message']['value'],
    ];
  }
}