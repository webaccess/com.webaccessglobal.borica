<?php

class com_webaccessglobal_borica extends CRM_Core_Payment {

  static protected $_mode = null;
  static protected $_params = array();

  /**
   * We only need one instance of this object. So we use the singleton
   * pattern and cache the instance in this variable
   *
   * @var object
   * @static
   */
  static private $_singleton = null;

  /**
   * Constructor
   *
   * @param string $mode the mode of operation: live or test
   *
   * @return void
   */
  function __construct($mode, &$paymentProcessor) {

    $this->_mode = $mode;
    $this->_paymentProcessor = $paymentProcessor;
    $this->_processorName = ts('Borica');
  }

  /**
   * This function checks to see if we have the right config values
   *
   * @return string the error message if any
   * @public
   */
  function checkConfig() {
    $config = CRM_Core_Config::singleton();
    $error = array();
    if (empty($this->_paymentProcessor['user_name'])) {
      $error[] = ts('Terminal ID is not set for this payment processor');
    }

    if (empty($this->_paymentProcessor['password'])) {
      $error[] = ts('Private Key is not set for this payment processor');
    }

    if (empty($this->_paymentProcessor['url_site'])) {
      $error[] = ts('Application URL is not set for this payment processor');
    }

    if (!empty($error)) {
      return implode('<p>', $error);
    }
    else {
      return null;
    }
  }

  function doDirectPayment(&$params) {
    CRM_Core_Error::fatal(ts('This function is not implemented'));
  }

  /**
   * Submit an Automated Recurring Billing subscription
   *
   * @param  array $params assoc array of input parameters for this transaction
   * @return array the result in a nice formatted array (or an error object)
   * @public
   */
  function doTransferCheckout(&$params, $component) {
    $component = strtolower($component);

    foreach ($params as $field => $value) {
      $this->_setParam($field, $value);
    }

    $terminalID = $this->_paymentProcessor['user_name'];
    $privateKeyPass = $this->_paymentProcessor['password'];
    $gateBoricaURL = $this->_paymentProcessor['url_site'];

    /*
     * Bank private key file .key extension with its location
     */
    if ($this->_mode == 'live') {
      //production mode private key
      $privateKeyFileName = '/home/prathamesh/public_html/borica/certificate-new/liveborica.clataccess.in.key';
    }
    else {
      //Test mode private key 
      $privateKeyFileName = '/home/prathamesh/public_html/borica/certificate-new/testborica.clataccess.in.key';
    }

    if ($component != 'contribute' && $component != 'event') {
      CRM_Core_Error::fatal(ts('Component is invalid'));
    }

    $boricaValues = array();
    $boricaValues['qfKey'] = $params['qfKey'];
    $boricaValues['contactID'] = $params['contactID'];
    $boricaValues['contributionID'] = $params['contributionID'];
    $boricaValues['component'] = $component;

    if ($component == 'event') {
      $boricaValues['eventID'] = $params['eventID'];
      $boricaValues['participantID'] =$params['participantID'];
    }
    else {
      $membershipID = CRM_Utils_Array::value('membershipID', $params);
      if ($membershipID) {
        $boricaValues['membershipID'] = $membershipID;
      }
      $relatedContactID = CRM_Utils_Array::value('related_contact', $params);
      if ($relatedContactID) {
        $boricaValues['relatedContactID'] = $relatedContactID;
        $onBehalfDupeAlert = CRM_Utils_Array::value('onbehalf_dupe_alert', $params);
        if ($onBehalfDupeAlert) {
          $boricaValues['onbehalf_dupe_alert'] = $onBehalfDupeAlert;
        }
      }
    }
    if (!empty($params['invoiceID']))  {
      $boricaValues['invoiceID'] = $params['invoiceID'];
    }
    $Borica_orderID = substr($params['invoiceID'],-15);
    // Insert current user's last selected form preferences into cache table
    CRM_Core_BAO_Cache::setItem($boricaValues, "borica_orderID_{$Borica_orderID}", 'com_webaccessglobal_borica', null);

    $amount = $params['amount'];
    $amount *= 100;
    $message = 10;
    $message .= $params['receive_date'];
    $message .= str_pad($amount, 12, "0", STR_PAD_LEFT);
    $message .= (string) $terminalID;
    $message .= str_pad((string) $Borica_orderID, 15); 
    $message .= str_pad($params['description'], 125);
    $message .= 'EN';
    $message .= '1.1';
    /*
     * Currently Borica payment processor only accept payments which are in BGN currency 
     */
    $message .= 'BGN';

    $fp = fopen($privateKeyFileName, "r");
    $priv_key = fread($fp, 8192);
    fclose($fp);
    //Encrypt the sending message using private key
    $pkeyid = openssl_get_privatekey($priv_key, $privateKeyPass);
    openssl_sign($message, $signature, $pkeyid);
    openssl_free_key($pkeyid);
    $message .= $signature;
    $action = "/registerTransaction?eBorica=";
    $url = $gateBoricaURL . $action . urlencode(base64_encode($message));
    CRM_Utils_System::redirect($url);
  }

  /**
   * Get the value of a field if set
   *
   * @param string $field the field
   * @return mixed value of the field, or empty string if the field is
   * not set
   */
  function _getParam($field) {
    return CRM_Utils_Array::value($field, $this->_params, '');
  }

  /**
   * singleton function used to manage this object
   *
   * @param string $mode the mode of operation: live or test
   *
   * @return object
   * @static
   *
   */
  static function &singleton($mode, &$paymentProcessor) {
    $processorName = $paymentProcessor['name'];
    if (self::$_singleton[$processorName] === null) {
      self::$_singleton[$processorName] = new com_webaccessglobal_borica($mode, $paymentProcessor);
    }
    return self::$_singleton[$processorName];
  }

  function &error($errorCode = null, $errorMessage = null) {
    $e = & CRM_Core_Error::singleton();
    if ($errorCode) {
      $e->push($errorCode, 0, null, $errorMessage);
    }
    else {
      $e->push(9001, 0, null, 'Unknowns System Error.');
    }
    return $e;
  }

  /**
   * Set a field to the specified value.  Value must be a scalar (int,
   * float, string, or boolean)
   *
   * @param string $field
   * @param mixed $value
   * @return bool false if value is not a scalar, true if successful
   */
  function _setParam($field, $value) {
    if (!is_scalar($value)) {
      return false;
    }
    else {
      $this->_params[$field] = $value;
    }
  }

  /**
   * Handle return response from payment processor
   */
  function handlePaymentNotification() {
    require_once 'boricaipn.php';
    $boricaIPN = new boricaipn($this->_mode, $this->_paymentProcessor);
    $boricaIPN->main($_GET);
  }

}
