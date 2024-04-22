<?php

namespace Drupal\commerce_adyen_dropin\Services;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactory;


/**
 * @file
 * Adyen Logger Service
 */

class AdyenLogger {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The database service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $logger;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  protected $paymentGatewaysConfig = [];

  /**
   * Constructs a new object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The time service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, LoggerChannelFactory $logger, ConfigFactoryInterface $configFactory) {
    $this->entityTypeManager = $entityTypeManager;
    $this->logger = $logger;
    $this->configFactory = $configFactory->get('commerce_adyen_dropin');
  }

  /**
   * Checks if logger is enabled.
   */
  public function isEnabled(string $paymentGatewayId) {
    // $paymentGatewayId = $order->get('payment_gateway')->target_id;
    if (!array_key_exists($paymentGatewayId, $this->paymentGatewaysConfig)) {
      $paymentGatewayStorage =  $this->entityTypeManager->getStorage('commerce_payment_gateway');
      $paymentGateway = $paymentGatewayStorage->load($paymentGatewayId);
      $paymentConfiguration = $paymentGateway->getPluginConfiguration();
      $loggerisActive = empty($paymentConfiguration['logger']) ? false : $paymentConfiguration['logger'];
      $this->paymentGatewaysConfig[$paymentGatewayId] = $loggerisActive;
    }

    return $this->paymentGatewaysConfig[$paymentGatewayId];
  }

  /**
   * Logs the message
   */
  public function log(string $paymentGatewayId, string $level, string $message) {
    if ($this->isEnabled($paymentGatewayId) == false) {
      return;
    }

    $this->logger->get('commerce_adyen_dropin')->log($level, $message);

  }
}