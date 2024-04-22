# Commerce Adyen Dropin

This module provides a Drupal Commerce payment method to embed the payment
services provided by Adyen. It efficiently integrates payments using Drop-In integration.

## Requirements

This module requires an [Adyen Merchant account](https://www.adyen.com/signup).

This module installs the following dependencies:
* Commerce Core, a submodule of [Drupal Commerce](https://www.drupal.org/project/commerce).
* Commerce Payment, a submodule of [Drupal Commerce](https://www.drupal.org/project/commerce).
* [Rest UI](https://www.drupal.org/project/restui).
* HTTP Basic Authentication, a core module.
* RESTful Web Services, a core module.

Also, it uses the following Javascript libraries, which are loaded as external libraries:
* [Adyen PHP API Library](https://github.com/Adyen/adyen-php-api-library).

## Setup
Upon enabling this module, the following will be installed automatically:

  - A new Adyen payment gateway, that can be managed from *Commerce* > *Configuration* > *Paymen*t > *Payment gateways*, in your site.
  - A new REST POST endpoint (`/adyen/dropin/payment`).
  - Permissions for anonymous and authenticated users will be granted for the forementioned endpoint.

By default, the settings for the payment gateway will be set to disabled, in test mode, and with NO credentials to connect to Adyen. Manage your configuration accordingly, but bear in mind that you will require to fill at least the following fields in Drupal for the gateway to work:

* Merchant Account (your Adyen account name + "ECOM"). You can find it in your Adyen account, under *Settings* > *Merchant Accounts*.
* Api Key. You can find it in your Adyen account, under *Developers* > *API credentials* > *ws@Company.[your_company]* > *Server settings* > *Authentication*. This key is common for the whole webservice you're configuring. It can be viewed only when generating it, and never again. If you need a new one, the old one will stop working within 24h after generating the new one.
* Client Key. You can find it in your Adyen account, under *Developers* > *API credentials* > *ws@Company.[your_company]* > *Client settings*. This key is common to all the allowed origins in this webservice, and can be viewed and copied at any time. You can configure your client key in your Adyen account following the next steps:
  - Go to `Add allowed origins`, below the client key field.
  - Add your site so the Drop-In integration works, like "https://www.mycoolsite.com", and click `Add` next to it.
  - Click `Save changes` at the bottom bar of the page.

Configure the `/adyen/dropin/payment` endpoint by creating a Webhook in the Adyen platform account:

  - Navigate to *Developers* > *Webhooks* in your Adyen account. Select New Webhook and Standard, then fill in these settings:
    - Server configuration: The /adyen/dropin/payment endpoint, like "https://www.mycoolsite.com/adyen/dropin/payment".
    - Events: Clear all and just check the AUTHORISATION event.
    - Basic authentication: Introduce the username and password of a user in your Drupal website that has admin privileges under the Basic Auth section.

Finally, don't forget to set the gateway config to live mode and/or enable/disable it in Drupal, according to your needs!

To test the payment with the card method enabled you can find test cards [here](https://docs.adyen.com/development-resources/testing/test-card-numbers/#visa).
