<?php

class boricaipn extends CRM_Core_Payment_BaseIPN {

  /**
   * We only need one instance of this object. So we use the singleton
   * pattern and cache the instance in this variable
   *
   * @var object
   * @static
   */
  private static $_singleton = null;

  /**
   * mode of operation: live or test
   *
   * @var object
   * @static
   */
  protected static $_mode = null;

  static function retrieve($name, $type, $object, $abort = true) {
    $value = CRM_Utils_Array::value($name, $object);
    if ($abort && $value === null) {
      CRM_Core_Error::debug_log_message("Could not find an entry for $name");
      echo "Failure: Missing Parameter - " . $name . "<p>";
      exit();
    }

    if ($value) {
      if (!CRM_Utils_Type::validate($value, $type)) {
        CRM_Core_Error::debug_log_message("Could not find a valid entry for $name");
        echo "Failure: Invalid Parameter<p>";
        exit();
      }
    }

    return $value;
  }

  /**
   * Constructor
   *
   * @param string $mode the mode of operation: live or test
   * @return void
   */
  function __construct($mode, &$paymentProcessor) {
    parent::__construct();

    $this->_mode = $mode;
    $this->_paymentProcessor = $paymentProcessor;
  }

  /**
   * singleton function used to manage this object
   *
   * @param string $mode the mode of operation: live or test
   *
   * @return object
   * @static
   */
  static function &singleton($mode, $component, &$paymentProcessor) {
    if (self::$_singleton === null) {
      self::$_singleton = new boricaipn($mode, $paymentProcessor);
    }
    return self::$_singleton;
  }

  /**
   * This method is handles the response that will be invoked by the
   * notification or request sent by the payment processor.
   * hex string from paymentexpress is passed to this function as hex string.
   */
  function main($boricaPostData) {
    $success = false;

    /*
     * Bank public certificate file .cer extension with its location
     */
    $boricaPostData = $this->_getBOResp($boricaPostData);

    // Getting transaction related data from cache table
    $data = CRM_Core_BAO_Cache::getItem("borica_orderID_{$boricaPostData['ORDER_ID']}", 'com_webaccessglobal_borica', null);

    // delete current user's last form preferences from cache table
    CRM_Core_BAO_Cache::deleteGroup("borica_orderID_{$boricaPostData['ORDER_ID']}");

    $component = $data['component'];
    $qfKey = $data['qfKey'];

    /*
     * @param array  $privateData  contains the CiviCRM related data
     */
    $privateData = $ids = $objects = array();
    $privateData['transactionID'] = $boricaPostData['ORDER_ID'];//Transaction ID for Contribution
    $privateData['contributionID'] = $data['contributionID'];
    $privateData['contactID'] = $data['contactID'];
    $privateData['invoiceID'] = $data['invoiceID'];

    if ($component == "event") {
      $privateData['participantID'] = $data['participantID'];
      $privateData['eventID'] = $data['eventID'];
    }
    else if ($component == "contribute") {
      $privateData["membershipID"] = array_key_exists('membershipID', $data) ? $data['membershipID'] : '';
      $privateData["relatedContactID"] = array_key_exists('relatedContactID', $data) ? $data['relatedContactID'] : '';
      $privateData["onbehalf_dupe_alert"] = array_key_exists('onbehalf_dupe_alert', $data) ? $data['onbehalf_dupe_alert'] : '';
    }

    list ($mode, $duplicateTransaction) = self::getContext($privateData);
    $privateData['is_test'] = $mode;
    $mode = $mode ? 'test' : 'live';

    /**
     * Fix me as per civicrm versions
     * In below 4.2 version 'CRM_Core_BAO_PaymentProcessor'
     * */
    $paymentProcessor = CRM_Financial_BAO_PaymentProcessor::getPayment($this->_paymentProcessor['id'], $mode);
    $ipn = self::singleton($mode, $component, $paymentProcessor);

    /*
     * @param array  $boricaData contains the Trnsaction related data
     */
    $boricaData = array();
    $boricaData['trxn_id'] = $boricaPostData['ORDER_ID'];// Bank Transaction ID
    $boricaData['PurchaseAmount'] = $boricaPostData['AMOUNT']/100;
    $boricaData['TRANSACTION_CODE'] = $boricaPostData['TRANSACTION_CODE'];
    $boricaData['status'] = $boricaPostData['RESPONSE_CODE'];
    $boricaData['SIGNOK'] = $boricaPostData['SIGNATURE_OK'];

    // Borica's RESPONSE_CODE == 00 means transaction completed successfully
    if ($boricaPostData['RESPONSE_CODE'] == 00)
      $success = TRUE;

    if ($duplicateTransaction == 0) {
      $ipn->newOrderNotify($success, $privateData, $component, $boricaData);
    }

    //Check $component and take appropriate action
    if ($component == "event") {
      $baseURL = 'civicrm/event/register';
      $query = $success ? "_qf_ThankYou_display=true&qfKey={$qfKey}" : "_qf_Register_display=1&cancel=1&qfKey={$qfKey}";
    }
    else if ($component == "contribute") {
      $baseURL = 'civicrm/contribute/transact';
      $query = $success ? "_qf_ThankYou_display=true&qfKey={$qfKey}" : "_qf_Main_display=1&cancel=1&qfKey={$qfKey}";
    }
    else {
      // Invalid component
      CRM_Core_Error::fatal(ts('Invalid component "' . $component . '" selected.'));
      exit();
    }

    $finalURL = CRM_Utils_System::url($baseURL, $query, false, null, false);
    CRM_Utils_System::redirect($finalURL);
  }

  function _getBOResp($boricaData) {
    $publicKey = '';
    // manipulation of the $_GET["eBorica"] parameter
    $message = base64_decode($boricaData['eBorica']);
    $boricaResponse['TRANSACTION_CODE'] = substr($message, 0, 2);
    $boricaResponse['TRANSACTION_TIME'] = substr($message, 2, 14);
    $boricaResponse['AMOUNT'] = substr($message, 16, 12);
    $boricaResponse['TERMINAL_ID'] = substr($message, 28, 8);
    $boricaResponse['ORDER_ID'] = substr($message, 36, 15);
    $boricaResponse['RESPONSE_CODE'] = substr($message, 51, 2);
    $boricaResponse['PROTOCOL_VERSION'] = substr($message, 53, 3);
    $boricaResponse['SIGN'] = substr($message, 56, 128);

    // Getting transaction related data from cache table
    $data = CRM_Core_BAO_Cache::getItem("borica_orderID_{$boricaResponse['ORDER_ID']}", 'com_webaccessglobal_borica', null);
    $privateData['contributionID'] = $data['contributionID'];
    $contributionID = $privateData['contributionID'];
    $contribution = & new CRM_Contribute_DAO_Contribution();
    $contribution->id = $contributionID;

    if (!$contribution->find(true)) {
      CRM_Core_Error::debug_log_message("Could not find contribution record: $contributionID");
      echo "Failure: Could not find contribution record for $contributionID<p>";
      exit();
    }
    $isTest = $contribution->is_test;
    if ($isTest) {
      //Test mode public certificate file
      $publicKey = '/home/prathamesh/public_html/borica/certificate-new/BORICA-Public_test_201212.cer';
    }
    else {
      //Production mode public certificate file
      $publicKey = '/home/prathamesh/public_html/borica/certificate-new/BORICA-Public_prod_201212.cer';
    }
    
    $fp = fopen($publicKey, "r");
    $cert = fread($fp, 8192);
    fclose($fp);
    $pubkeyid = openssl_get_publickey($cert);
    $boricaResponse['SIGNATURE_OK'] = openssl_verify(substr($message, 0, strlen($message) - 128), $boricaResponse['SIGN'], $pubkeyid);
    openssl_free_key($pubkeyid);
    return $boricaResponse;
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
   * The function gets called when a new order takes place.
   *
   * @param array  $privateData  contains the CiviCRM related data
   * @param string $component    the CiviCRM component
   * @param array  $borikaData contains the Merchant related data
   *
   * @return void
   *
   */
  function newOrderNotify($success, $privateData, $component, $boricaData) {
    $ids = $input = $params = array();

    $input['component'] = strtolower($component);
    $ids['contact'] = self::retrieve('contactID', 'Integer', $privateData, true);
    $ids['contribution'] = self::retrieve('contributionID', 'Integer', $privateData, true);

    if ($input['component'] == "event") {
      $ids['event'] = self::retrieve('eventID', 'Integer', $privateData, true);
      $ids['participant'] = self::retrieve('participantID', 'Integer', $privateData, true);
      $ids['membership'] = NULL;
    }
    else {
      $ids['membership'] = self::retrieve('membershipID', 'Integer', $privateData, FALSE);
      $ids['related_contact'] = self::retrieve('relatedContactID', 'Integer', $privateData, FALSE);
      $ids['onbehalf_dupe_alert'] = self::retrieve('onBehalfDupeAlert', 'Integer', $privateData, FALSE);
      $ids['contributionRecur'] = self::retrieve('contributionRecurID', 'Integer', $privateData, FALSE);
    }

    $ids['contributionRecur'] = $ids['contributionPage'] = null;
    if (!$this->validateData($input, $ids, $objects)) {
      return false;
    }

    // make sure the invoice is valid and matches what we have in the contribution record
    $contribution = & $objects['contribution'];
    $input['invoice'] = $privateData['invoiceID'];
    $input['trxn_id'] = $boricaData['trxn_id'];
    $input['is_test'] = $privateData['is_test'];
    $input['amount'] = $boricaData['PurchaseAmount'];
    $transaction = new CRM_Core_Transaction();

    /*
     * Check the signature of transaction '1' means correct otherwise incorrect
     */
    if ($boricaData['SIGNOK'] == '1') {
      switch ($boricaData['status']) {
      case '00': // Transaction Completed
        break;
      case '94': // Transaction Cancelled
        return $this->cancelled($objects, $transaction);
        break;
      default : // Transaction Failed
        return $this->failed($objects, $transaction);
        break;
      }
    }
    else {
      //Signature is incorrect we set transaction as failed
      return $this->failed($objects, $transaction);
    }
    if ($contribution->invoice_id != $input['invoice']) {
      CRM_Core_Error::debug_log_message("Invoice values dont match between database and IPN request");
      echo "Failure: Invoice values dont match between database and IPN request<p>";
      return;
    }

    if ($contribution->total_amount != $input['amount']) {
      CRM_Core_Error::debug_log_message("Amount values dont match between database and IPN request");
      echo "Failure: Amount values dont match between database and IPN request. " . $contribution->total_amount . "/" . $input['amount'] . "<p>";
      return;
    }

    // check if contribution is already completed, if so we ignore this ipn
    if ($contribution->contribution_status_id == 1) {
      $transaction->commit();
      CRM_Core_Error::debug_log_message("returning since contribution has already been handled");
      echo "Success: Contribution has already been handled<p>";
      return true;
    }
    else {

      if (CRM_Utils_Array::value('event', $ids)) {
        $contribution->trxn_id = $ids['event'] . CRM_Core_DAO::VALUE_SEPARATOR . $ids['participant'];
      }
      elseif (CRM_Utils_Array::value('membership', $ids)) {
        $contribution->trxn_id = $ids['membership'][0] . CRM_Core_DAO::VALUE_SEPARATOR . $ids['related_contact'] . CRM_Core_DAO::VALUE_SEPARATOR . $ids['onbehalf_dupe_alert'];
      }
    }

    $this->completeTransaction($input, $ids, $objects, $transaction);
    return true;
  }

  /**
   * The function returns the component(Event/Contribute..)and whether it is Test or not
   *
   * @param array   $privateData    contains the name-value pairs of transaction related data
   *
   * @return array context of this call (test, component, payment processor id)
   * @static
   */
  static function getContext($privateData) {

    $component = null;
    $isTest = null;

    $contributionID = $privateData['contributionID'];
    $contribution = & new CRM_Contribute_DAO_Contribution();
    $contribution->id = $contributionID;

    if (!$contribution->find(true)) {
      CRM_Core_Error::debug_log_message("Could not find contribution record: $contributionID");
      echo "Failure: Could not find contribution record for $contributionID<p>";
      exit();
    }

    $isTest = $contribution->is_test;

    $duplicateTransaction = 0;
    if ($contribution->contribution_status_id == 1) {
      //contribution already handled. (some processors do two notifications so this could be valid)
      $duplicateTransaction = 1;
    }

    if ($component == 'contribute') {
      if (!$contribution->contribution_page_id) {
        CRM_Core_Error::debug_log_message("Could not find contribution page for contribution record: $contributionID");
        echo "Failure: Could not find contribution page for contribution record: $contributionID<p>";
        exit();
      }
    }
    else {
      // we are in event mode
      $eventID = $privateData['eventID'];
      if (!$eventID) {
        CRM_Core_Error::debug_log_message("Could not find event ID");
        echo "Failure: Could not find eventID<p>";
        exit();
      }
      $event = & new CRM_Event_DAO_Event();
      $event->id = $eventID;
      if (!$event->find(true)) {
        CRM_Core_Error::debug_log_message("Could not find event: $eventID");
        echo "Failure: Could not find event: $eventID<p>";
        exit();
      }
    }

    return array(
                 $isTest,
                 $duplicateTransaction
                 );
  }

}

?>
