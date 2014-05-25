<?php
return array(
	'developmentMode' => 'sandbox', // or production
	'showErrors' => true,

	'productionCertificate' => 'apns-prod.pem',
	'productionCertificatePassphrase' => '',
	'productionAPNSGateway' => 'ssl://gateway.push.apple.com:2195',
	'productionFeedbackService' => 'ssl://feedback.push.apple.com:2196',

	'sandboxCertificate' => 'apns-dev.pem',
	'sandboxCertificatePassphrase' => '',
	'sandboxAPNSGateway' => 'ssl://gateway.sandbox.push.apple.com:2195',
	'sandboxFeedbackService' => 'ssl://feedback.sandbox.push.apple.com:2196'
);