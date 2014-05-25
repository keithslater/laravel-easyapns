<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateApnsMessagesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('apns_messages', function($table)
		{
			$table->increments('pid');
			$table->string('clientid', 64)->nullable();
			$table->integer('fk_device');
			$table->string('message');
			$table->dateTime('delivery');

			$table->enum('status', array(
				'queued', 'delivered', 'failed'
			))->default('queued');

			$table->timestamps();

			$table->index('clientid');
			$table->index('fk_device');
			$table->index('status');
			$table->index('created_at');
			$table->index('updated_at');
			$table->index('message');
			$table->index('delivery');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('apns_messages');
	}

}
