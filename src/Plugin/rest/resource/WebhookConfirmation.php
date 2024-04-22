<?php

namespace Drupal\commerce_adyen_dropin\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Psr\Log\LoggerInterface;
use Drupal\commerce_adyen_dropin\Services\AdyenLogger;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\rest\ModifiedResourceResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Webhook to provide confirmation of Adyen Payment on the front-end
 *
 * @RestResource(
 *   id = "adyen_confirmation_webhook",
 *   label = @Translation("Adyen Confirmation Webhook"),
 *   uri_paths = {
 *     "canonical" = "/adyen/dropin/confirm/{order_id}"
 *   }
 * )
 */
class WebhookConfirmation extends ResourceBase {
  /**
   *  The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   *  Adyen Logger Service.
   *
   * @var \Drupal\commerce_adyen_dropin\Services\AdyenLogger
   */
  protected $adyenLogger;

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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The centity type manager.
   * @param \Drupal\commerce_adyen_dropin\Services\AdyenLogger
   *   Adyen Logger Service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    EntityTypeManagerInterface $entityTypeManager,
    AdyenLogger $adyenLogger
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->entityTypeManager = $entityTypeManager;
    $this->adyenLogger = $adyenLogger;
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
      $container->get('entity_type.manager'),
      $container->get('adyen.logger')
    );
  }

  /**
   * Responds to entity GET requests.
   * 
   * @param int $order_id
   *   The ID of the order.
   * 
   * @return \Drupal\rest\ResourceResponse
   */
  public function get($order_id) {
    if ($order_id) {
      $order = $this->entityTypeManager->getStorage('commerce_order')->load($order_id);
      $paymentGatewayId = $order->get('payment_gateway')->target_id;
      $this->adyenLogger->log($paymentGatewayId, 'notice', 'Webhook Confirmation: Entered Webhook Confirmation - Order ID: ' . $order_id);
      $query = $this->entityTypeManager->getStorage('commerce_payment')->getQuery();
      $query->condition('order_id', $order_id);
      $payment = $query->execute();
      if (!empty($payment)) {
        $this->adyenLogger->log($paymentGatewayId, 'notice', 'Webhook Confirmation: Payment TRUE - Order ID: ' . $order_id);
        return new ModifiedResourceResponse(['paymentStatus' => true]);
      } else {
        $this->adyenLogger->log($paymentGatewayId, 'notice', 'Webhook Confirmation: Payment TRUE - Order ID: ' . $order_id);
        return new ModifiedResourceResponse(['paymentStatus' => false]);
      }
    }
    $this->logger->notice('Webhook Confirmation: Not entered Webhook Confirmation');
    throw new BadRequestHttpException('No order ID was provided');
  }

}