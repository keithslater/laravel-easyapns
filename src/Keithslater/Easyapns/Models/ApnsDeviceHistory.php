<?php namespace Keithslater\Easyapns\Models;

class ApnsDeviceHistory extends \Eloquent {
	protected $table = 'apns_device_history';
	protected $primaryKey = 'pid';
}