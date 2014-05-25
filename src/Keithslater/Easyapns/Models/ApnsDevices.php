<?php namespace Keithslater\Easyapns\Models;

class ApnsDevices extends \Eloquent {
	protected $table = 'apns_devices';
	protected $primaryKey = 'pid';
}