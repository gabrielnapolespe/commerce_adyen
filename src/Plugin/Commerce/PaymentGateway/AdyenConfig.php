<?php

namespace Drupal\commerce_adyen_dropin\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;


/**
 * Provides the Off-site Redirect payment Boleto gateway.
 *
 * @CommercePaymentGateway(
 *   id = "adyen_dropin",
 *   label = "Adyen Drop-In",
 *   display_label = "Adyen Drop-In",
 *     forms = {
 *       "offsite-payment" = "Drupal\commerce_adyen_dropin\PluginForm\AdyenPaymentForm",
 *     },
 *   payment_method_types = {"credit_card"},
 *   credit_card_types = {
 *     "amex", "dinersclub", "discover", "jcb", "maestro", "mastercard", "visa", "unionpay"
 *   }
 *
 * )
 */
class AdyenConfig extends OffsitePaymentGatewayBase {

  private $values;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'merchant_account' => '',
      'api_key' => '',
      'client_key' => '',
    ] + parent::defaultConfiguration();
  }

  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['merchant_account'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Merchant Account'),
      '#default_value' => $this->configuration['merchant_account'],
      '#description' => $this->t('Your account name + ECOM. You can find this value in your Adyen account, under <em>Settings</em> > <em>Merchant Accounts</em>.'),
      '#required' => TRUE,
    ];

    $form['api_key'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Api Key'),
      '#default_value' => $this->configuration['api_key'],
      '#description' => $this->t('<p>You can find this value in your Adyen account, under <em>Developers</em> > <em>API credentials</em> > <em>your API key credential</em> > <em>Server settings</em> > <em>Authentication</em>.</p>'
        . '<p>This key is common for the whole webservice you\'re configuring and it can be viewed only when generating it, and never again.</p>'
        . '<p>If you need a new one, the old one will stop working within 24h after generating the new one.</p>'),
      '#required' => TRUE,
    ];

    $form['client_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Key'),
      '#default_value' => $this->configuration['client_key'],
      '#description' => $this->t('<p>You can find this value in your Adyen account, under <em>Developers</em> > <em>API credentials</em> > <em>your API key credential</em> > <em>Client settings</em>.</p>'
        . '<p>This key is common to all the allowed origins in this webservice, and can be viewed and copied at any time.</p>'
        . '<p>You can configure your client key in your Adyen account following the next steps:<p>'
        . '<ul>'
        . '<li>Go to <em>Add allowed origins</em>, below the client key field.'
        . '<li>Add your site so the Drop-In integration works, like "https://www.mycoolsite.com", and click <em>Add</em> next to it.'
        . '<li>Click <em>Save changes</em> at the bottom bar of the page.'
        . '</ul>'
      ),
      '#required' => TRUE,
    ];

    $form['prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL Prefix'),
      '#default_value' => !empty($this->configuration['prefix']) ? $this->configuration['prefix'] : '',
      '#description' => $this->t('Only required for LIVE transactions. In TEST, you can add anything beacuse the value is skipped. You can find this value inside your Adyen account, Developers > API URLs > Prefix.'),
      '#required' => TRUE,
    ];

    $form['webhook'] = [
      '#type' => 'markup',
      '#title' => $this->t('URL Prefix'),
      '#markup' => $this->t(
          '<div class="form-item__label">Additional settings in Adyen</div>'
          .'<div class="form-item__description">'
            .'<p>'
              .'You have to configure the `/adyen/dropin/payment` endpoint by creating a Webhook in the Adyen platform account.'
              .'To do that, navigate to <em>Developers</em> > <em>Webhooks</em> in your Adyen account.'
              .'Select <em>New Webhook</em> and then <em>Standard</em>. Next fill in the following settings:'
            .'</p>'
            .'<ul>'
              .'<li>Server configuration: The /adyen/dropin/payment endpoint, like "https://www.mycoolsite.com/adyen/dropin/payment".</li>'
              .'<li>Events: Clear all and check the AUTHORISATION event.</li>'
              .'<li>Basic authentication: Introduce the username and password of a user in your Drupal website that has admin privileges under the Basic Auth section.</li>'
            .'</ul>'
            .'<p>To test the payment with the card method enabled you can find test cards <a target="_blank" href="https://docs.adyen.com/development-resources/testing/test-card-numbers/#visa">here</a>.</p>'
          .'</div>'
        ),
    ];

    $form['collect_billing_information'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Collect billing information'),
      '#description' => $this->t('Before disabling, make sure you are not legally required to collect billing information.'),
      '#default_value' => FALSE,
      // Merchants can disable collecting billing information only if the
      // payment gateway indicates that it doesn't require it.
      '#access' => FALSE,
    ];

    $form['error_message'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Error message'),
      '#description' => $this->t('This message is shown when the web doesn\'t have enough information to know if the payment has been successful or not, and after waiting for 5 seconds the answer from Adyen.'),
      '#default_value' => !empty($this->configuration['error_message']['value']) ? $this->configuration['error_message']['value'] : '',
    ];

    $form['logger'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Activate logger'),
      '#description' => $this->t('Activate the logger to get more information from the module.'),
      '#default_value' => !empty($this->configuration['logger']) ? $this->configuration['logger'] : FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['merchant_account'] = $values['merchant_account'];
      $this->configuration['api_key'] = $values['api_key'];
      $this->configuration['client_key'] = $values['client_key'];
      $this->configuration['prefix'] = $values['prefix'];
      $this->configuration['collect_billing_information'] = $values['collect_billing_information'];
      $this->configuration['error_message'] = $values['error_message'];
      $this->configuration['logger'] = $values['logger'];
    }
  }
}