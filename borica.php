<?php

require_once 'borica.civix.php';
require_once 'boricapayment.php';
/**
 * Implementation of hook_civicrm_config
 */
function borica_civicrm_config(&$config) {
  _borica_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 */
function borica_civicrm_xmlMenu(&$files) {
  _borica_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 */
function borica_civicrm_install() {
  return _borica_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 */
function borica_civicrm_uninstall() {

  CRM_Core_DAO::executeQuery("DELETE pp FROM civicrm_payment_processor pp
RIGHT JOIN  civicrm_payment_processor_type ppt ON ppt.id = pp.payment_processor_type_id
WHERE ppt.name = 'Borica'");

  $affectedRows = mysql_affected_rows();

  if($affectedRows)
    CRM_Core_Session::setStatus("Borica Payment Processor Message:
    <br />Entries for Borica Payment Processor are now Deleted!
    <br />");

  return _borica_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 */
function borica_civicrm_enable() {

  CRM_Core_DAO::executeQuery("UPDATE civicrm_payment_processor pp
RIGHT JOIN  civicrm_payment_processor_type ppt ON ppt.id = pp.payment_processor_type_id
SET pp.is_active = 1
WHERE ppt.name = 'Borica'");

  $affectedRows = mysql_affected_rows();

  if($affectedRows)
    CRM_Core_Session::setStatus("Borica Payment Processor Message:
    <br />Entries for Borica Payment Processor are now Enabled!
    <br />");

  return _borica_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 */
function borica_civicrm_disable() {

  CRM_Core_DAO::executeQuery("UPDATE civicrm_payment_processor pp
RIGHT JOIN  civicrm_payment_processor_type ppt ON ppt.id = pp.payment_processor_type_id
SET pp.is_active = 0
WHERE ppt.name = 'Borica'");

  $affectedRows = mysql_affected_rows();

  if($affectedRows)
    CRM_Core_Session::setStatus("Borica Payment Processor Message:
    <br />Entries for Borica Payment Processor are now Disabled!
    <br />");

  return _borica_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 */
function borica_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _borica_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 */
function borica_civicrm_managed(&$entities) {
$entities[] = array(
    'module' => 'com.webaccessglobal.borica',
    'name' => 'Borica',
    'entity' => 'PaymentProcessorType',
    'params' => array(
      'version' => 3,
      'name' => 'Borica',
      'title' => 'Borica',
      'description' => 'Borica Payment Processor',
      'class_name' => 'com.webaccessglobal.borica',
      'billing_mode' => 'notify',
      'user_name_label' => 'Terminal ID',
      'pasword_label' => 'Private Key Pass',
      'url_site_default' => 'https://gate.borica.bg/etlog',
      'url_site_test_default' => 'https://gatet.borica.bg/etlog',
      'payment_type' => 1,
    ),
  );
  return _borica_civix_civicrm_managed($entities);
}
