<?php

namespace Drupal\commerce_adyen_dropin\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a resource to log Adyen front information.
 *
 * @RestResource(
 *   id = "adyen_webhook_information",
 *   label = @Translation("Adyen Information Logger"),
 *   uri_paths = {
 *     "create" = "/adyen/dropin/dblog/logger/{order_id}"
 *   }
 * )
 */
class WebhookInformation extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a new DefaultRestResource object.
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
   *   A current user instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, AccountProxyInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $current_user;
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
      $container->get('current_user')
    );
  }

  /**
   * Responds to a POST request and logs the message.
   *
   * @param mixed $data
   *   The data to be logged.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   */
  public function post(Request $request, $order_id): ModifiedResourceResponse {
    $message = json_decode($request->getContent());
    $message = get_object_vars($message);
    // Log the message using the logger context.
    $this->logger->log('notice', 'Order ID @orderId: @message', [
      '@orderId' => $order_id,
      '@message' => print_r($message, TRUE),
    ]);

    // Build our response.
    $response = [
      'message' => 'successfully logged',
      'dblog_message' => $this->t('@message', [
        '@message' => print_r($message, TRUE),
      ]),
      'dblog_channel' => 'commerce_adyen_dropin',
    ];

    return new ModifiedResourceResponse($response, 200);
  }

}
