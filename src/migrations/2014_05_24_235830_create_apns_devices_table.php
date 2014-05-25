<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class CreateApnsDevicesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('apns_devices', function($table)
		{
			$table->increments('pid');
			$table->string('clientid', 64);
			$table->string('appname');
			$table->string('appversion', 25)->nullable();
			$table->char('deviceuid', 40);
			$table->char('devicetoken', 64)->nullable();
			$table->string('devicename');
			$table->string('devicemodel', 100);
			$table->string('deviceversion', 25);

			$table->enum('pushbadge', array(
				'disabled', 'enabled'
			))->default('disabled');

			$table->enum('pushalert', array(
				'disabled', 'enabled'
			))->default('disabled');

			$table->enum('pushsound', array(
				'disabled', 'enabled'
			))->default('disabled');

			$table->enum('development', array(
				'production', 'sandbox'
			))->default('production');

			$table->enum('status', array(
				'active', 'uninstalled'
			))->default('active');

			$table->timestamps();

			$table->unique(array('appname', 'deviceuid'));
			$table->unique(array('appname', 'devicetoken'));
			$table->index('clientid')->nullable();
			$table->index('devicetoken');
			$table->index('devicename');
			$table->index('devicemodel');
			$table->index('deviceversion');
			$table->index('pushbadge');
			$table->index('pushalert');
			$table->index('pushsound');
			$table->index('development');
			$table->index('status');
			$table->index('created_at');
			$table->index('updated_at');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('apns_devices');

	}

}
