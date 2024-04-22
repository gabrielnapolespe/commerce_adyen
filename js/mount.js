mount();

(function ($, Drupal, drupalSettings) {
  var adyenRefusedMessage = '';
  var adyenErrorMessage = '';

  Drupal.behaviors.commerceAdyen = {
    attach: function (context, settings) {
      adyenRefusedMessage = Drupal.t("The payment was refused, or cancelled by you. You will be redirected automatically in 10 seconds to try again. If the error persists, contact us.", {}, {context: 'Adyen'});
      adyenErrorMessage = Drupal.t("There was an error during the transaction and it could not be authorised. You will be redirected automatically in 10 seconds to try again.  If the error persists, contact us.", {}, {context: 'Adyen'});
    },
    getRefusedMessage: function () {
      return adyenRefusedMessage;
    },
    getErrorMessage: function () {
      return adyenErrorMessage;
    }
  };

})(jQuery, Drupal, drupalSettings);
