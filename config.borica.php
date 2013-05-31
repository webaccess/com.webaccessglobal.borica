<?php
/*
 * FIX ME: Change below paths as per your need
 */

// Path for public key certificate for devlopment, received from BANK
define('TEST_MODE_PUBLIC_CERT', '/home/htdocs/certificates/BORICA-Public_test_201212.cer');

// Path for public key certificate for production, received from BANK
define('PROD_MODE_PUBLIC_CERT', '/home/htdocs/certificates/BORICA-Public_prod_201212.cer');

// Path for private key for Test environment transactions created by merchant
define('TEST_MODE_PRIVATE_KEY', '/home/htdocs/certificates/testborica.key');

// Path for private key for production environment transactions created by merchant
define('PROD_MODE_PRIVATE_KEY', '/home/htdocs/certificates/liveborica.key');
