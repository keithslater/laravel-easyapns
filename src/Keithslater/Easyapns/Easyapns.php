<?php namespace Keithslater\Easyapns;

use Config;
use Keithslater\Easyapns\Models\ApnsDevices;
use Keithslater\Easyapns\Models\ApnsMessages;
use Log;

class Easyapns {

	/**
	 * Production or Sandbox app
	 * @var string
	 * @access private
	 */
	private $DEVELOPMENT; // or 'sandbox'

	/**
	 * Array of APNS Connection Settings
	 *
	 * @var array
	 * @access private
	 */
	private $apnsData;

	/**
	 * Whether to trigger errors
	 *
	 * @var bool
	 * @access private
	 */
	private $showErrors;

	/**
	 * Absolute path to your Production Certificate
	 *
	 * @var string
	 * @access private
	 */
	private $certificate;

	/** Apples Production Certificate Passphrase
	 *
	 * @var string
	 * @access private
	 */
	private $passphrase;

	/**
	 * Apples Production APNS Gateway
	 *
	 * @var string
	 * @access private
	 */
	private $ssl;

	/**
	 * Apples Production APNS Feedback Service
	 *
	 * @var string
	 * @access private
	 */
	private $feedback;

	/**
	 * Absolute path to your Development Certificate
	 *
	 * @var string
	 * @access private
	 */
	private $sandboxCertificate;

	/**
	 * Apples Sandbox Certificate Passphrase
	 *
	 * @var string
	 * @access private
	 */
	private $sandboxPassphrase;

	/**
	 * Apples Sandbox APNS Gateway
	 *
	 * @var string
	 * @access private
	 */
	private $sandboxSsl;

	/**
	 * Apples Sandbox APNS Feedback Service
	 *
	 * @var string
	 * @access private
	 */
	private $sandboxFeedback;

	/**
	 * Message to push to user
	 *
	 * @var string
	 * @access private
	 */
	private $message;

	/**
	 * Streams connected to APNS server[s]
	 *
	 * @var array
	 * @access private
	 */
	private $sslStreams;

	function __construct($args = null, $environment = null) {

		$config_path = app('path') .'/config/packages/keithslater/easyapns/';

		$this->DEVELOPMENT = Config::get('laravel-easyapns::developmentMode');
		$this->showErrors = Config::get('laravel-easyapns::showErrors');

		$this->certificate = $config_path. Config::get('laravel-easyapns::productionCertificate');
		$this->passphrase = Config::get('laravel-easyapns::productionCertificatePassphrase');
		$this->ssl = Config::get('laravel-easyapns::productionAPNSGateway');
		$this->feedback = Config::get('laravel-easyapns::productionFeedbackService');

		$this->sandboxCertificate = $config_path. Config::get('laravel-easyapns::sandboxCertificate');
		$this->sandboxPassphrase = Config::get('laravel-easyapns::sandboxCertificatePassphrase');
		$this->sandboxSsl = Config::get('laravel-easyapns::sandboxAPNSGateway');
		$this->sandboxFeedback = Config::get('laravel-easyapns::sandboxFeedbackService');

		$this->checkSetup();

		$this->apnsData = array(
			'production'=>array(
				'certificate' => $this->certificate,
				'ssl' => $this->ssl,
				'feedback' => $this->feedback,
				'passphrase' => $this->passphrase
			),
			'sandbox'=>array(
				'certificate' => $this->sandboxCertificate,
				'ssl' => $this->sandboxSsl,
				'feedback' => $this->sandboxFeedback,
				'passphrase' => $this->sandboxPassphrase
			)
		);

		if(!empty($args)){
			switch($args['task']){
				case "register":
					$this->_registerDevice(
						$args['appname'],
						$args['appversion'],
						$args['deviceuid'],
						$args['devicetoken'],
						$args['devicename'],
						$args['devicemodel'],
						$args['deviceversion'],
						$args['pushbadge'],
						$args['pushalert'],
						$args['pushsound'],
						isset($args['clientid'])?$args['clientid']:null,
						$environment
					);
					break;

				case "fetch":
					$this->_fetchMessages();
					break;

				case "flush":
					$this->_flushMessages();
					break;

				default:
					echo "No APNS Task Provided...\n";
					break;
			}
		}


	}

	public static function test() {
		return "Testing";
	}

	/**
	 * Check Setup
	 *
	 * Check to make sure that the certificates are available and also provide a notice if they are not as secure as they could be.
	 *
	 * @access private
	 */
	private function checkSetup(){
		if(!file_exists($this->certificate)) $this->_triggerError('Missing Production Certificate.');
		if(!file_exists($this->sandboxCertificate)) $this->_triggerError('Missing Sandbox Certificate.');

		if (!isset($this->passphrase) || !isset($this->sandboxPassphrase))
			$this->_triggerError('You need to specify the passphrase for the production and sandbox certificate.');

		clearstatcache();
		$certificateMod = substr(sprintf('%o', fileperms($this->certificate)), -3);
		$sandboxCertificateMod = substr(sprintf('%o', fileperms($this->sandboxCertificate)), -3);

		if($certificateMod>644)  $this->_triggerError('Production Certificate is insecure! Suggest chmod 644.');
		if($sandboxCertificateMod>644)  $this->_triggerError('Sandbox Certificate is insecure! Suggest chmod 644.');
	}

	/**
	 * Register Apple device
	 *
	 * Using your Delegate file to auto register the device on application launch.  This will happen automatically from the Delegate.m file in your iPhone Application using our code.
	 *
	 * @param string $appname Application Name
	 * @param string $appversion Application Version
	 * @param string $deviceuid 40 charater unique user id of Apple device
	 * @param string $devicetoken 64 character unique device token tied to device id
	 * @param string $devicename User selected device name
	 * @param string $devicemodel Modle of device 'iPhone' or 'iPod'
	 * @param string $deviceversion Current version of device
	 * @param string $pushbadge Whether Badge Pushing is Enabled or Disabled
	 * @param string $pushalert Whether Alert Pushing is Enabled or Disabled
	 * @param string $pushsound Whether Sound Pushing is Enabled or Disabled
	 * @param string $clientid The clientid of the app for message grouping
	 * @access private
	 */
	private function _registerDevice($appname, $appversion, $deviceuid, $devicetoken, $devicename, $devicemodel, $deviceversion, $pushbadge, $pushalert, $pushsound, $clientid = '', $environment){

		if(strlen($appname)==0) $this->_triggerError('Application Name must not be blank.', E_USER_ERROR);
		else if(strlen($appversion)==0) $this->_triggerError('Application Version must not be blank.', E_USER_ERROR);
		else if(strlen($deviceuid)>40) $this->_triggerError('Device ID may not be more than 40 characters in length.', E_USER_ERROR);
		else if(strlen($devicetoken)!=64) $this->_triggerError('Device Token must be 64 characters in length.', E_USER_ERROR);
		else if(strlen($devicename)==0) $this->_triggerError('Device Name must not be blank.', E_USER_ERROR);
		else if(strlen($devicemodel)==0) $this->_triggerError('Device Model must not be blank.', E_USER_ERROR);
		else if(strlen($deviceversion)==0) $this->_triggerError('Device Version must not be blank.', E_USER_ERROR);
		else if($pushbadge!='disabled' && $pushbadge!='enabled') $this->_triggerError('Push Badge must be either Enabled or Disabled.', E_USER_ERROR);
		else if($pushalert!='disabled' && $pushalert!='enabled') $this->_triggerError('Push Alert must be either Enabled or Disabled.', E_USER_ERROR);
		else if($pushsound!='disabled' && $pushsound!='enabled') $this->_triggerError('Push Sound must be either Enabled or Disabled.', E_USER_ERROR);
		else if(!is_null($environment) && $environment!='sandbox' && $environment!='production') $this->_triggerError('Default environment must be either sandbox, production or NULL if you want to use default value.', E_USER_ERROR);

		//setting environment using default private value if no value provided
		$environment = is_null($environment)?$this->DEVELOPMENT:$environment;

		$apns_devices = ApnsDevices::where('deviceuid', '=', $deviceuid)->where('appname', '=', $appname)->first();

		if (is_null($apns_devices)) {
			$apns_devices = new ApnsDevices();
			$apns_devices->deviceuid = $deviceuid;
			$apns_devices->appname = $appname;
		}
		$apns_devices->clientid = $clientid;
		$apns_devices->appversion = $appversion;
		$apns_devices->devicetoken = $devicetoken;
		$apns_devices->devicename = $devicename;
		$apns_devices->devicemodel = $devicemodel;
		$apns_devices->deviceversion = $deviceversion;
		$apns_devices->pushbadge = $pushbadge;
		$apns_devices->pushalert = $pushalert;
		$apns_devices->pushsound = $pushsound;
		$apns_devices->development = $environment;
		$apns_devices->status = 'active';
		$apns_devices->save();
	}

	/**
	 * Unregister Apple device
	 *
	 * This gets called automatically when Apple's Feedback Service responds with an invalid token.
	 *
	 * @param string $token 64 character unique device token tied to device id
	 * @access private
	 */
	private function _unregisterDevice($token){
		$device = ApnsDevices::where('devicetoken', '=', $token)->first();
		$device->status = 'uninstalled';
		$device->save();
	}

	/**
	 * Fetch Messages
	 *
	 * This gets called by a cron job that runs as often as you want.  You might want to set it for every minute.
	 *
	 * @access private
	 */
	private function _fetchMessages(){
		// only send one message per user... oldest message first
		$messages = ApnsMessages::select(array('apns_messages.pid', 'message', 'devicetoken', 'development'))
			->leftJoin('apns_devices', function($join){
				$join->on('apns_devices.pid', '=', 'apns_messages.fk_device');
			})
			->where('apns_messages.status', '=', 'queued')
			->where('apns_messages.delivery', '<=', 'NOW()')
			->where('apns_devices.status', '=', 'active')
			->groupBy('apns_messages.fk_device')
			->orderBy('apns_messages.created_at')
			->take(100)->get();

		$this->_iterateMessages($messages);
	}

	/**
	 * Flush Messages
	 *
	 * This gets called by a cron job that runs as often as you want.  You might want to set it for every minute.
	 * Like _fetchMessages, but sends all the messages for each device (_fetchMessage sends only the first message for device)
	 *
	 * @access private
	 */
	private function _flushMessages(){
		// only send one message per user... oldest message first
		$messages = ApnsMessages::select(array('apns_messages.pid', 'message', 'devicetoken', 'development'))
			->leftJoin('apns_devices', function($join){
			$join->on('apns_devices.pid', '=', 'apns_messages.fk_device');
		})
			->where('apns_messages.status', '=', 'queued')
			->where('apns_messages.delivery', '<=', 'NOW()')
			->where('apns_devices.status', '=', 'active')
			->orderBy('apns_messages.created_at')
			->take(100)->get();
		;

		$this->_iterateMessages($messages);
	}

	/**
	 * Iterate Messages
	 *
	 * This gets called by _fetchMessages and _flushMessages to loop over the list of messages that they selected
	 * to be sent out from the database.
	 *
	 * @param string $sql Query which selects messages in the database
	 * @access private
	 */
	private function _iterateMessages($messages) {

		if (count($messages) > 0) {
			foreach ($messages as $row) {
				$pid = $row->pid;
				$message = $row->message;
				$token = $row->devicetoken;
				$development = $row->development;

				// Connect the socket the first time it's needed.
				if(!isset($this->sslStreams[$development])) {
					$this->_connectSSLSocket($development);
				}
				$this->_pushMessage($pid, $message, $token, $development);
			}

			// Close streams and check feedback service
			foreach($this->sslStreams as $key=>$socket) {
				$this->_closeSSLSocket($key);
				$this->_checkFeedback($key);
			}
		}
	}

	/**
	 * Connect the SSL stream (sandbox or production)
	 *
	 * @param $development string Development environment - sandbox or production
	 * @return bool|resource status whether the socket connected or not.
	 * @access private
	 */
	private function _connectSSLSocket($development) {
		$ctx = stream_context_create();
		stream_context_set_option($ctx, 'ssl', 'local_cert', $this->apnsData[$development]['certificate']);
		stream_context_set_option($ctx, 'ssl', 'passphrase', $this->apnsData[$development]['passphrase']);
		$this->sslStreams[$development] = stream_socket_client($this->apnsData[$development]['ssl'], $error, $errorString, 100, (STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT), $ctx);
		if(!$this->sslStreams[$development]){
			unset($this->sslStreams[$development]);
			$this->_triggerError("Failed to connect to APNS: {$error} {$errorString}.");
		}
		return $this->sslStreams[$development];
	}

	/**
	 * Close the SSL stream (sandbox or production)
	 *
	 * @param $development string Development environment - sandbox or production
	 * @return void
	 * @access private
	 */
	private function _closeSSLSocket($development) {
		if(isset($this->sslStreams[$development])) {
			fclose($this->sslStreams[$development]);
			unset($this->sslStreams[$development]);
		}
	}

	/**
	 * Push APNS Messages
	 *
	 * This gets called automatically by _fetchMessages.  This is what actually deliveres the message.
	 *
	 * @param int $pid
	 * @param string $message JSON encoded string
	 * @param string $token 64 character unique device token tied to device id
	 * @param string $development Which SSL to connect to, Sandbox or Production
	 * @access private
	 */
	private function _pushMessage($pid, $message, $token, $development){
		if(strlen($pid)==0) $this->_triggerError('Missing message pid.', E_USER_ERROR);
		if(strlen($message)==0) $this->_triggerError('Missing message.', E_USER_ERROR);
		if(strlen($token)==0) $this->_triggerError('Missing message token.', E_USER_ERROR);
		if(strlen($development)==0) $this->_triggerError('Missing development status.', E_USER_ERROR);

		$fp = false;
		if(isset($this->sslStreams[$development])) {
			$fp = $this->sslStreams[$development];
		}

		if(!$fp){
			$this->_pushFailed($pid);
			$this->_triggerError("A connected socket to APNS wasn't available.");
		}
		else {
			// "For optimum performance, you should batch multiple notifications in a single transmission over the
			// interface, either explicitly or using a TCP/IP Nagle algorithm."

			// Simple notification format (Bytes: content.) :
			// 1: 0. 2: Token length. 32: Device Token. 2: Payload length. 34: Payload
			//$msg = chr(0).pack("n",32).pack('H*',$token).pack("n",strlen($message)).$message;

			// Enhanced notification format: ("recommended for most providers")
			// 1: 1. 4: Identifier. 4: Expiry. 2: Token length. 32: Device Token. 2: Payload length. 34: Payload
			$expiry = time()+120; // 2 minute validity hard coded!
			$msg = chr(1).pack("N",$pid).pack("N",$expiry).pack("n",32).pack('H*',$token).pack("n",strlen($message)).$message;

			$fwrite = fwrite($fp, $msg);
			if(!$fwrite) {
				$this->_pushFailed($pid);
				$this->_closeSSLSocket($development);
				$this->_triggerError("Failed writing to stream.", E_USER_ERROR);

			}
			else {
				// "Provider Communication with Apple Push Notification Service"
				// http://developer.apple.com/library/ios/#documentation/NetworkingInternet/Conceptual/RemoteNotificationsPG/CommunicatingWIthAPS/CommunicatingWIthAPS.html#//apple_ref/doc/uid/TP40008194-CH101-SW1
				// "If you send a notification and APNs finds the notification malformed or otherwise unintelligible, it
				// returns an error-response packet prior to disconnecting. (If there is no error, APNs doesn't return
				// anything.)"
				//
				// This complicates the read if it blocks.
				// The timeout (if using a stream_select) is dependent on network latency.
				// default socket timeout is 60 seconds
				// Without a read, we leave a false positive on this push's success.
				// The next write attempt will fail correctly since the socket will be closed.
				//
				// This can be done if we start batching the write

				// Read response from server if any. Or if the socket was closed.
				// [Byte: data.] 1: 8. 1: status. 4: Identifier.
				$tv_sec = 1;
				$tv_usec = null; // Timeout. 1 million micro seconds = 1 second
				$r = array($fp); $we = null; // Temporaries. "Only variables can be passed as reference."
				$numChanged = stream_select($r, $we, $we, $tv_sec, $tv_usec);
				if(false===$numChanged) {
					$this->_triggerError("Failed selecting stream to read.", E_USER_ERROR);
				}
				else if($numChanged>0) {
					$command = ord(fread($fp, 1));
					$status = ord(fread($fp, 1));
					$identifier = implode('', unpack("N", fread($fp, 4)));
					$statusDesc = array(
						0 => 'No errors encountered',
						1 => 'Processing error',
						2 => 'Missing device token',
						3 => 'Missing topic',
						4 => 'Missing payload',
						5 => 'Invalid token size',
						6 => 'Invalid topic size',
						7 => 'Invalid payload size',
						8 => 'Invalid token',
						255 => 'None (unknown)',
					);

					if($status>0) {
						// $identifier == $pid
						$this->_pushFailed($pid);
						$desc = isset($statusDesc[$status])?$statusDesc[$status]: 'Unknown';
						// The socket has also been closed. Cause reopening in the loop outside.
						$this->_closeSSLSocket($development);
						$this->_triggerError("APNS responded with error for pid($identifier). status($status: $desc)", E_USER_ERROR);

					}
					else {
						// Apple docs state that it doesn't return anything on success though
						$this->_pushSuccess($pid);
					}

					$this->_triggerError("APNS responded with command($command) status($status) pid($identifier).", E_USER_NOTICE);
				} else {
					$this->_pushSuccess($pid);
				}
			}
		}
	}

	/**
	 * Fetch APNS Messages
	 *
	 * This gets called automatically by _pushMessage.  This will check with APNS for any invalid tokens and disable them from receiving further notifications.
	 *
	 * @param string $development Which SSL to connect to, Sandbox or Production
	 * @access private
	 */
	private function _checkFeedback($development){
		$ctx = stream_context_create();
		stream_context_set_option($ctx, 'ssl', 'local_cert', $this->apnsData[$development]['certificate']);
		stream_context_set_option($ctx, 'ssl', 'passphrase', $this->apnsData[$development]['passphrase']);
		stream_context_set_option($ctx, 'ssl', 'verify_peer', false);
		$fp = stream_socket_client($this->apnsData[$development]['feedback'], $error,$errorString, 100, (STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT), $ctx);

		if(!$fp) $this->_triggerError("Failed to connect to device: {$error} {$errorString}.");
		while ($devcon = fread($fp, 38)){
			$arr = unpack("H*", $devcon);
			$rawhex = trim(implode("", $arr));
			$token = substr($rawhex, 12, 64);
			if(!empty($token)){
				$this->_unregisterDevice($token);
				$this->_triggerError("Unregistering Device Token: {$token}.");
			}
		}
		fclose($fp);
	}

	/**
	 * APNS Push Success
	 *
	 * This gets called automatically by _pushMessage.  When no errors are present, then the message was delivered.
	 *
	 * @param int $pid Primary ID of message that was delivered
	 * @access private
	 */
	private function _pushSuccess($pid){
		$message = ApnsMessages::find($pid)->first();
		$message->status = 'delivered';
		$message->save();
	}

	/**
	 * APNS Push Failed
	 *
	 * This gets called automatically by _pushMessage.  If an error is present, then the message was NOT delivered.
	 *
	 * @param int $pid Primary ID of message that was delivered
	 * @access private
	 */
	private function _pushFailed($pid){
		$message = ApnsMessages::find($pid)->first();
		$message->status = 'failed';
		$message->save();
	}

	/**
	 * Trigger Error
	 *
	 * Use PHP error handling to trigger User Errors or Notices.  If logging is enabled, errors will be written to the log as well.
	 * Disable on screen errors by setting showErrors to false;
	 *
	 * @param string $error Error String
	 * @param int $type Type of Error to Trigger
	 * @access public
	 */
	function _triggerError($error, $type=E_USER_NOTICE){

		Log::error($error);

		if($this->showErrors) trigger_error($error, $type);
	}

	/**
	 * Start a New Message
	 *
	 * <code>
	 * <?php
	 * $db = new DbConnect('localhost','dbuser','dbpass','dbname');
	 * $db->show_errors();
	 * $apns = new APNS($db); // CREATE THE OBJECT
	 * $apns->newMessage(1, '2010-01-01 00:00:00'); // START A MESSAGE... SECOND ARGUMENT ACCEPTS ANY DATETIME STRING
	 * $apns->addMessageAlert('You got your emails.'); // ALERTS ARE TRICKY... SEE EXAMPLES
	 * $apns->addMessageBadge(9); // PASS A NUMBER
	 * $apns->addMessageSound('bingbong.aiff'); // ADD A SOUND
	 * $apns->queueMessage(); // AND SEND IT ON IT'S WAY
	 *
	 * $apns->newMessage(array(1,3,4,5,8,15,16)); // SEND MESSAGE TO MORE THAN ONE USER
	 * $apns->addMessageAlert('Greetings Everyone!');
	 * $apns->queueMessage();
	 * ?>
	 * </code>
	 *
	 * @param mixed $fk_device Foreign Key, or Array of Foreign Keys to the device you want to send a message to.
	 * @param string $delivery Possible future date to send the message.
	 * @access public
	 */
	public function newMessage($fk_device=NULL, $delivery=NULL, $clientId=NULL){
		if(isset($this->message)){
			unset($this->message);
			$this->_triggerError('An existring message already created but not delivered. The previous message has been removed. Use queueMessage() to complete a message.');
		}

		// If no device is specified then that means we sending a message to all.
		if (is_null($fk_device))
		{
			if (!is_null($clientId)) {
				$devices = ApnsDevices::where('status', '=', 'active')->where('clientid', '=', $clientId)->get();
			} else {
				$devices = ApnsDevices::where('status', '=', 'active')->get();
			}

			$ids = array();

			foreach ($devices as $device) {
				$ids[] = $device->pid;
			}

			$fk_device = $ids;
		}

		$this->message = array();
		$this->message['aps'] = array();
		$this->message['aps']['clientid'] = $clientId;
		$this->message['send']['to'] = $fk_device;
		$this->message['send']['when'] = $delivery;
	}

	/**
	 * Start a New Message. Like newMessage, but takes the deviceUId instead of fk_device.
	 * Actually fetches the pid from the db and then calls the plain newMessage.
	 *
	 * @param mixed $deviceUId The DeviceUId you want to send the message to.
	 * @param string $delivery Possible future date to send the message.
	 * @access public
	 */
	public function newMessageByDeviceUId($deviceUId=NULL, $delivery=NULL, $clientId=NULL) {

		$devices = ApnsDevices::where('deviceuid', '=', $deviceUId)->first();

		if (!is_null($devices)) {
			$this->newMessage($devices->pid, $delivery, $clientId);
			return true;
		}

		return false;
	}

	/**
	 * Queue Message for Delivery
	 *
	 * <code>
	 * <?php
	 * $db = new DbConnect('localhost','dbuser','dbpass','dbname');
	 * $db->show_errors();
	 * $apns = new APNS($db);
	 * $apns->newMessage(1, '2010-01-01 00:00:00');
	 * $apns->addMessageAlert('You got your emails.');
	 * $apns->addMessageBadge(9);
	 * $apns->addMessageSound('bingbong.aiff');
	 * $apns->queueMessage(); // ADD THE MESSAGE TO QUEUE
	 * ?>
	 * </code>
	 *
	 * @access public
	 */
	public function queueMessage(){
		// check to make sure a message was created
		if (!isset($this->message))
			$this->_triggerError('You cannot Queue a message that has not been created. Use newMessage() to create a new message.');

		// loop through possible users
		$to = $this->message['send']['to'];
		$when = $this->message['send']['when'];
		$clientId = is_null($this->message['aps']['clientid']) ? '' : $this->message['aps']['clientid'];
		$list = (is_array($to)) ? $to : array($to);
		unset($this->message['send']);

		// Lets make sure that the recipients are integers. If not then just remove
		foreach ($list as $key => $val)
			if (!is_numeric($val)) {
				$this->_triggerError("TO id was not an integer: $val.");
				unset($list[$key]);
			}

		// No recipients left?
		if (empty($list))
			$this->_triggerError('No valid recipient was provided.');
		// Get the devices.
		// fetch the users id and check to make sure they have certain notifications enabled before trying to send anything to them.
		$devices = ApnsDevices::whereIn('pid', $list)->where('status', '=', 'active')->get();

		if (count($devices) == 0)
		{
			$this->_triggerError('This user does not exist in the database. Message will not be delivered.');
		}
		else
		{
			foreach ($devices as $device)
			{
				$deliver = true;

				// Device id.
				$deviceid = $device->pid;
				// Get the push settings.
				$pushbadge = $device->pushbadge;
				$pushalert = $device->pushalert;
				$pushsound = $device->pushsound;

				// has user disabled messages?
				if($pushbadge=='disabled' && $pushalert=='disabled' && $pushsound=='disabled')
					$deliver = false;

				if($deliver===false && !is_null($devices)) {
					$this->_triggerError('This user has disabled all push notifications. Message will not be delivered.');
				}
				else if($deliver===true) {
					// make temp copy of message so we can cut out stuff this user may not get
					$usermessage = $this->message;

					// only send badge if user will get it
					if($pushbadge=='disabled'){
						$this->_triggerError('This user has disabled Push Badge Notifications, Badge will not be delivered.');
						unset($usermessage['aps']['badge']);
					}

					// only send alert if user will get it
					if($pushalert=='disabled'){
						$this->_triggerError('This user has disabled Push Alert Notifications, Alert will not be delivered.');
						unset($usermessage['aps']['alert']);
					}

					// only send sound if user will get it
					if($pushsound=='disabled'){
						$this->_triggerError('This user has disabled Push Sound Notifications, Sound will not be delivered.');
						unset($usermessage['aps']['sound']);
					}

					if(is_null($usermessage['aps']['clientid'])) {
						unset($usermessage['aps']['clientid']);
					}

					if(empty($usermessage['aps'])) {
						unset($usermessage['aps']);
					}

					$fk_device = $deviceid;
					$message = json_encode($usermessage);

					$delivery = (!empty($when)) ? $when: date('Y-m-d H:i:s');

					$apns_messages = new ApnsMessages();
					$apns_messages->clientid = $clientId;
					$apns_messages->fk_device = $fk_device;
					$apns_messages->message = $message;
					$apns_messages->delivery = $delivery;
					$apns_messages->status = 'queued';
					$apns_messages->save();

					unset($usermessage);
				}
			}
		}
		unset($this->message);
	}

	/**
	 * Add Message Alert
	 *
	 * <code>
	 * <?php
	 * $db = new DbConnect('localhost','dbuser','dbpass','dbname');
	 * $db->show_errors();
	 * $apns = new APNS($db);
	 *
	 * // SIMPLE ALERT
	 * $apns->newMessage(1, '2010-01-01 00:00:00');
	 * $apns->addMessageAlert('Message received from Bob'); // MAKES DEFAULT BUTTON WITH BOTH 'Close' AND 'View' BUTTONS
	 * $apns->queueMessage();
	 *
	 * // CUSTOM 'View' BUTTON
	 * $apns->newMessage(1, '2010-01-01 00:00:00');
	 * $apns->addMessageAlert('Bob wants to play poker', 'PLAY'); // MAKES THE 'View' BUTTON READ 'PLAY'
	 * $apns->queueMessage();
	 *
	 * // NO 'View' BUTTON
	 * $apns->newMessage(1, '2010-01-01 00:00:00');
	 * $apns->addMessageAlert('Bob wants to play poker', ''); // MAKES AN ALERT WITH JUST AN 'OK' BUTTON
	 * $apns->queueMessage();
	 *
	 * // CUSTOM LOCALIZATION STRING FOR YOUR APP
	 * $apns->newMessage(1, '2010-01-01 00:00:00');
	 * $apns->addMessageAlert(NULL, NULL, 'GAME_PLAY_REQUEST_FORMAT', array('Jenna', 'Frank'));
	 * $apns->queueMessage();
	 * ?>
	 * </code>
	 *
	 * @param int $number
	 * @access public
	 */
	public function addMessageAlert($alert=NULL, $actionlockey=NULL, $lockey=NULL, $locargs=NULL){
		if(!$this->message) $this->_triggerError('Must use newMessage() before calling this method.', E_USER_ERROR);
		if(isset($this->message['aps']['alert'])){
			unset($this->message['aps']['alert']);
			$this->_triggerError('An existring alert was already created but not delivered. The previous alert has been removed.');
		}
		switch(true){
			case (!empty($alert) && empty($actionlockey) && empty($lockey) && empty($locargs)):
				if(!is_string($alert)) $this->_triggerError('Invalid Alert Format. See documentation for correct procedure.', E_USER_ERROR);
				$this->message['aps']['alert'] = (string)$alert;
				break;

			case (!empty($alert) && !empty($actionlockey) && empty($lockey) && empty($locargs)):
				if(!is_string($alert)) $this->_triggerError('Invalid Alert Format. See documentation for correct procedure.', E_USER_ERROR);
				else if(!is_string($actionlockey)) $this->_triggerError('Invalid Action Loc Key Format. See documentation for correct procedure.', E_USER_ERROR);
				$this->message['aps']['alert']['body'] = (string)$alert;
				$this->message['aps']['alert']['action-loc-key'] = (string)$actionlockey;
				break;

			case (empty($alert) && empty($actionlockey) && !empty($lockey) && !empty($locargs)):
				if(!is_string($lockey)) $this->_triggerError('Invalid Loc Key Format. See documentation for correct procedure.', E_USER_ERROR);
				$this->message['aps']['alert']['loc-key'] = (string)$lockey;
				$this->message['aps']['alert']['loc-args'] = $locargs;
				break;

			default:
				$this->_triggerError('Invalid Alert Format. See documentation for correct procedure.', E_USER_ERROR);
				break;
		}
	}

	/**
	 * Add Message Badge
	 *
	 * <code>
	 * <?php
	 * $db = new DbConnect('localhost','dbuser','dbpass','dbname');
	 * $db->show_errors();
	 * $apns = new APNS($db);
	 * $apns->newMessage(1, '2010-01-01 00:00:00');
	 * $apns->addMessageBadge(9); // HAS TO BE A NUMBER
	 * $apns->queueMessage();
	 * ?>
	 * </code>
	 *
	 * @param int $number
	 * @access public
	 */
	public function addMessageBadge($number=NULL){
		if(!$this->message) $this->_triggerError('Must use newMessage() before calling this method.', E_USER_ERROR);
		if($number) {
			if(isset($this->message['aps']['badge'])) $this->_triggerError('Message Badge has already been created. Overwriting with '.$number.'.');
			$this->message['aps']['badge'] = (int)$number;
		}
	}

	/**
	 * Add Message Custom
	 *
	 * <code>
	 * <?php
	 * $db = new DbConnect('localhost','dbuser','dbpass','dbname');
	 * $db->show_errors();
	 * $apns = new APNS($db);
	 * $apns->newMessage(1, '2010-01-01 00:00:00');
	 * $apns->addMessageCustom('acme1', 42); // CAN BE NUMBER...
	 * $apns->addMessageCustom('acme2', 'foo'); // ... STRING
	 * $apns->addMessageCustom('acme3', array('bang', 'whiz')); // OR ARRAY
	 * $apns->queueMessage();
	 * ?>
	 * </code>
	 *
	 * @param string $key Name of Custom Object you want to pass back to your iPhone App
	 * @param mixed $value Mixed Value you want to pass back.  Can be int, bool, string, or array.
	 * @access public
	 */
	public function addMessageCustom($key=NULL, $value=NULL){
		if(!$this->message) $this->_triggerError('Must use newMessage() before calling this method.', E_USER_ERROR);
		if(!empty($key) && !empty($value)) {
			if(isset($this->message[$key])){
				unset($this->message[$key]);
				$this->_triggerError('This same Custom Key already exists and has not been delivered. The previous values have been removed.');
			}
			if(!is_string($key)) $this->_triggerError('Invalid Key Format. Key must be a string. See documentation for correct procedure.', E_USER_ERROR);
			$this->message[$key] = $value;
		}
	}

	/**
	 * Add Message Sound
	 *
	 * <code>
	 * <?php
	 * $db = new DbConnect('localhost','dbuser','dbpass','dbname');
	 * $db->show_errors();
	 * $apns = new APNS($db);
	 * $apns->newMessage(1, '2010-01-01 00:00:00');
	 * $apns->addMessageSound('bingbong.aiff'); // STRING OF FILE NAME
	 * $apns->queueMessage();
	 * ?>
	 * </code>
	 *
	 * @param string $sound Name of sound file in your Resources Directory
	 * @access public
	 */
	public function addMessageSound($sound=NULL){
		if(!$this->message) $this->_triggerError('Must use newMessage() before calling this method.', E_USER_ERROR);
		if($sound) {
			if(isset($this->message['aps']['sound'])) $this->_triggerError('Message Sound has already been created. Overwriting with '.$sound.'.');
			$this->message['aps']['sound'] = (string)$sound;
		}
	}

	/**
	 * Process all queued messages
	 *
	 * <code>
	 * <?php
	 * $db = new DbConnect('localhost','dbuser','dbpass','dbname');
	 * $db->show_errors();
	 * $apns = new APNS($db);
	 * $apns->newMessage(1, '2010-01-01 00:00:00');
	 * $apns->addMessageSound('bingbong.aiff');
	 * $apns->queueMessage();
	 * $apns->processQueue(); // SEND ALL MESSAGES NOW
	 * ?>
	 * </code>
	 *
	 * @access public
	 */
	public function processQueue(){
		$this->_fetchMessages();
	}


}