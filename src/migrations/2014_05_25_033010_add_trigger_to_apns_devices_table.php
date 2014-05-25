<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTriggerToApnsDevicesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		DB::unprepared("CREATE TRIGGER `Archive` BEFORE UPDATE ON `apns_devices` FOR EACH ROW INSERT INTO `apns_device_history` VALUES (
			NULL,
			OLD.`clientid`,
			OLD.`appname`,
			OLD.`appversion`,
			OLD.`deviceuid`,
			OLD.`devicetoken`,
			OLD.`devicename`,
			OLD.`devicemodel`,
			OLD.`deviceversion`,
			OLD.`pushbadge`,
			OLD.`pushalert`,
			OLD.`pushsound`,
			OLD.`development`,
			OLD.`status`,
			NOW()
		);");
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		DB::unprepared("DROP TRIGGER Archive");
	}

}
