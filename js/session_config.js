// Put it into a function so we can call it from another js (%% values are just reference. This values will be modified before the execution of the script)
const mount = async function () {

  // Credit Card configuration
  const cardConfiguration = {
    billingAddressRequired: false
  };

  const delay = ms => new Promise(res => setTimeout(res, ms));

  const fetchRetry = async (url, options = {}, n) => {
    let error;
    for (let i = 0; i < n; i++) {
      try {
        await delay(1000);
        const response = await fetch(url, options);
        const paymentStatus = await response.json();
        if (paymentStatus.paymentStatus == true) {
          return paymentStatus;
        }
      } catch (err) {
        error = err;
      }
    }
    throw error;
  };

  const getCsrfToken = async () => {
    const response = await fetch(Drupal.url('session/token'));
    const token = await response.text();
    return token;
  }

  const sendInformation = async (data, orderId, token) => {
    var host = window.location.protocol + '//' + window.location.host;
    data = JSON.stringify(data);
    const response = await fetch(host + '/adyen/dropin/dblog/logger/' + orderId + '?_format=json', {
      method: "POST",
      body: data,
      headers: {
        "Content-Type": "application/json",
        'X-CSRF-Token': token
      }
    });
    // const info = await response.json();
  }

  const configuration = {
    environment: '%enviroment%', // Enviroment type (live or test).
    clientKey: '%clientKey%', // Public key used for client-side authentication: https://docs.adyen.com/development-resources/client-side-authentication
    analytics: {
      enabled: true // Set to false to not send analytics data to Adyen.
    },
    session: {
      id: '%sessionId%', // Unique identifier for the payment session.
      sessionData: '%sessionData%' // The payment session data.
    },
    paymentMethodsConfiguration: {
      card: cardConfiguration
    },
    onPaymentCompleted: async (result, component) => {
      var token = await getCsrfToken();
      var orderId = '%orderId%';
      var host = '%host%';
      await sendInformation(result, orderId, token);
      // Handle the result.resultCode
      if (result.resultCode == 'Authorised' || result.resultCode == 'Received') {
        const options = {
          headers: {
            "Content-Type": "application/json",
            'X-CSRF-Token': token
          },
        }

        try {
          // Check if response from backend is positive or not
          var paymentStatus = await fetchRetry(host + '/adyen/dropin/confirm/' + orderId + '?_format=json', options, 5);

          if (paymentStatus.paymentStatus) {
            // Redirect to the url to create the order's payment
            var step = '%step%';
            window.location.replace(host + '/commerce_dropin_adyen/order/complete/' + orderId + '/' + step);
          }
        } catch (error) {
          window.location.replace(host +  '/commerce_dropin_adyen/order/message/' + orderId);
        }

        return;
      }
      else if (result.resultCode == 'Refused' || result.resultCode == 'Cancelled') {
        // Alert the customer about the error and rebuild the drop-in-container
        const messages = new Drupal.Message();
        messages.add(Drupal.behaviors.commerceAdyen.getRefusedMessage(), { type: 'error' });
        setTimeout(function () {
          location.reload();
        }, 10000);
      }
      else if (result.resultCode == 'Error') {
        // Alert the customer about the error and rebuild the drop-in-container
        const messages = new Drupal.Message();
        messages.add(Drupal.behaviors.commerceAdyen.getErrorMessage(), { type: 'error' });
        setTimeout(function () {
          location.reload();
        }, 10000);
      }
    },
    onError: (error, component) => {
      console.error(error.name, error.message, error.stack, component);
    },
    // Any payment method specific configuration. Find the configuration specific to each payment method:  https://docs.adyen.com/payment-methods
    // For example, this is 3D Secure configuration for cards:
    paymentMethodsConfiguration: {
      card: {
        hasHolderName: true,
        holderNameRequired: true,
        billingAddressRequired: false
      }
    }
  };
  // Create an instance of AdyenCheckout using the configuration object.
  const checkout = await AdyenCheckout(configuration);

  // Create an instance of Drop-in and mount it to the container you created.
  const dropinComponent = checkout.create('dropin').mount('#dropin-container');

}