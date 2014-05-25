<?php namespace Keithslater\Easyapns;

use Illuminate\Support\ServiceProvider;
use Keithslater\Easyapns\Commands\ApnsCommand;
use App;

class EasyapnsServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->package('keithslater/laravel-easyapns');

		App::bind('apns', function($app)
		{
			return new ApnsCommand;
		});

		$this->commands('apns');
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		//
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array();
	}

}
