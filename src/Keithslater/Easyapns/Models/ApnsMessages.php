<?php namespace Keithslater\Easyapns\Models;

class ApnsMessages extends \Eloquent {
	protected $table = 'apns_messages';
	protected $primaryKey = 'pid';
}