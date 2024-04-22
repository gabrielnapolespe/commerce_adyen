<?php

namespace Drupal\commerce_adyen_dropin\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\Plugin\ResourceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Psr\Log\LoggerInterface;
use Drupal\commerce_payment\Entity\Payment;
use \Datetime;
use Drupal\commerce_adyen_dropin\Services\AdyenLogger;
use Drupal\rest\ModifiedResourceResponse;

/**
 * Webhook used to authorise the Adyen Payment
 *
 * @RestResource(
 *   id = "adyen_webhook",
 *   label = @Translation("Adyen Payment Webhook"),
 *   uri_paths = {
 *     "create" = "/adyen/dropin/payment"
 *   }
 * )
 */
class WebhookAuthorisation extends ResourceBase {

  /**
   *  A curent user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;
  
  /**
   *  Adyen Logger Service.
   *
   * @var \Drupal\commerce_adyen_dropin\Services\AdyenLogger
   */
  protected $adyenLogger;

  /**
   * Actual request. 
   * 
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * Constructs a Drupal\rest\Plugin\ResourceBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user instance.
   * @param \Drupal\commerce_adyen_dropin\Services\AdyenLogger
   *   Adyen Logger Service.
   * @param Request $current_request
   *   The current request
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user,
    AdyenLogger $adyen_logger,
    Request $current_request
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $current_user;
    $this->adyenLogger = $adyen_logger;
    $this->currentRequest = $current_request;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('commerce_adyen_dropin'),
      $container->get('current_user'),
      $container->get('adyen.logger'),
      $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * Responds to entity POST requests.
   * @return \Drupal\rest\ResourceResponse
   */
  public function post(Request $request) {

    // Get the JSON Request Body
    $json = $this->getJsonBody($request);
    // If NotificationRequestItem object is not empty, create the payment
    if(!empty($json->notificationItems[0]->NotificationRequestItem)) {
      // Collect NotificationRequestItem
      $data = $json->notificationItems[0]->NotificationRequestItem;
      // Create payment
      $result = $this->createPayment($data->merchantReference, $data->pspReference, $data->success);
      // Result of payment
      $response = [
        '[accepted]' => 'true',
        'paymentResult' => $result
      ];

    }
    else {
      $response = ['error' => 'Empty notification'];
    }
    return new ModifiedResourceResponse($response);
  }

  /**
   * Loads JSON body from request.
   * @return array | null
   */
  public function getJsonBody(Request $request) {
    // Collect the content
    $content = $this->currentRequest->getContent();
    if (!empty($content)) {
      $json = json_decode($content);
    }

    return $json;
  }

  /**
   * Creates payment.
   * @return string
   */
  public function createPayment(string $orderId, string $remoteId, string $success): String {
    // Get the payment gateway from order
    //TODO: Pasar como servicio
    $order = \Drupal::entityTypeManager()->getStorage('commerce_order')->loadForUpdate($orderId);
    $paymentGatewayId = $order->get('payment_gateway')->target_id;

    $paymentGatewayStorage =  \Drupal::entityTypeManager()->getStorage('commerce_payment_gateway');
    $paymentGateways = $paymentGatewayStorage->loadByProperties(['plugin'=> 'adyen_dropin']);

    $this->adyenLogger->log($paymentGatewayId, 'notice', 'Webhook Authorisation: Entered the createPayment method - Order ID: ' . $orderId);
    
    //TODO: Revisar esto del get one payment. No se debería de pillar el payment gateway del pedido?
    // Get one payment gateway
    foreach ($paymentGateways as $value) {
      $paymentGateway = $value;
    }

    if ($success === 'true') {
      // Change order status
      $this->adyenLogger->log($paymentGatewayId, 'notice', 'Webhook Authorisation: createPayment success TRUE - Order ID: ' . $orderId);
      // \Drupal::logger('commerce_adyen_dropin')->notice('Webhook Authorisation: Success TRUE - Order ID: ' . $orderId);
      if ($order->getState()->isTransitionAllowed('place')) {
        $order->getState()->applyTransitionById('place');
        $order->set('checkout_step', 'complete');
      }
      $order->save();

      // We get the current date for the authorised payment
      $date = new DateTime('now', new \DateTimeZone('UTC'));
      $date->format('Y-m-d H:i:s');
      $currentDate = $date->getTimestamp();

      // Create and save completed payment
      $payment = Payment::create([
        'state' => 'completed',
        'amount' => $order->getTotalPrice(),
        'payment_gateway' => $paymentGateway->id(),
        'order_id' => $order->id(),
        'payment_gateway_mode' => $paymentGateway->getPlugin()->getMode(),
        'remote_id' => $remoteId,
        'authorized' => $currentDate,
        'completed' => $currentDate
      ]);
      $payment->save();

      // We send the payment's state
      $state = 'completed';
    }
    else {
      $this->adyenLogger->log($paymentGatewayId, 'notice', 'Webhook Authorisation: createPayment success FALSE - Order ID: ' . $orderId);
      // Create and save refused payment
      $payment = Payment::create([
        'state' => 'refused',
        'amount' => $order->getTotalPrice(),
        'payment_gateway' => $paymentGateway->id(),
        'order_id' => $order->id(),
        'payment_gateway_mode' => $paymentGateway->getPlugin()->getMode(),
        'remote_id' => $remoteId,
      ]);
      $payment->save();

      $state = 'refused';
    }

    return $state;
  }

}