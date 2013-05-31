com.webaccessglobal.borica
==========================

Borica payment processor extension for CiviCRM

This document serves as a brief description of how you go about setting up Borica to work with CiviCRM 4.3.x

1: Configure and Install Borica extension in CiviCRM extensions.(http://wiki.civicrm.org/confluence/display/CRMDOC40/Extensions+Admin)

2. Configure Borica payment processor.
   A: Creating merchant account on Borica:-
      For creating merchant account you need to provide call back URL as https://<YOUR HOST>/civicrm/payment/ipn/borica as your response URL.
      Borica will provide you some certificate files for production and testing environment.
      Development URL: https://gatet.borica.bg/etlog/
      Production URL: https://gate.borica.bg/etlog/
      The merchant must generate three requests for certificates, each of them with a different private key:
      	  - certificate for verification of the signature on a real BOReq message;
	  - certificate for verification of the signature on a test BOReq message;
	  - client certificate for access to eTransactionLog(-test).
      use following command to create private keys( create different diffrent private keys ):
      	  openssl genrsa -out privateKeyName. key[-des3] 1024 
      Notes:
	  • the parameter -des3 is used for protection of the generated private key by a password;
	  • the size of this key in bits must be 1024.
      Use following command to create certificates
      	  openssl req -new -key privateKeyName.key-out reqName.csr
      After this merchant needs to send this certificates to bank for signing the certificates.
      Bank will provide Terminal ID.
      Note: the names of the created files, which are requests for certificates, should clearly indicate the purpose of these certificates. All fields in the request must be filled-in correctly. The field common Name in the request for certificate for eTransaction Log must correspond to the certificate name for access to the application.

   B: Configure certificate files
      After Signing the certificates bank will provide you files which contains:
      1: Certificate for accessing eTransaciton Log.
      2: Certificate which contains public key for devlopment.
      3: Certificate which contains public key for production.

      1: The access to eTransaciton Log and eTransaction Log-test requires insertion in the merchant’s browser of the certificate signed by BORIKA-BANKSERVICE, which should be transformed into a „.р12” file.
      	 openssl pkcs12 -export -inkey privateKeyNamekey-in certificate_name.cer -out keystore_name.p12
	 where:
		- privateKeyName.key – the name of the private key, through which the certificate request has been generated, for access to eTransaction Log.
		- certificate_name.cer– the returned certificate with the signature of BORICA-BANKSERVICE for access to eTransaction Log.
	 After generating keystore_name.p12 file merchant needs to import this in their browser as a certificate, which allow them to access eTransaciton Logs of Borica.

      So we have following files:-
      1: Private key for Test environment transactions.
      2: Private key for production environment transactions.
      3: Keystore_name.p12 for browser.
      4: Public key certificate for devlopment.
      5: Public key certificate for production.
      Place these files in one folder on the server.

    C: Configuring borica payment processor in CiviCRM
       1: Merchant needs to add Terminal ID provided by BANK.
       2: "Private Key Pass" contains password for private key.

    D: Modifications needed in extension file
       1: com.webaccessglobal.borica/config.borica.php
       	  Change certificates and private key path's.





