<?php

class CRM_Borica_Payment extends CRM_Core_Page {
  static function handleIPN() {
    $_GET['processor_name'] = 'Borica';
    CRM_Core_Payment::handleIPN();
  }
}

