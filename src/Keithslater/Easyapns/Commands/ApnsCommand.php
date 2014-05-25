<?php namespace Keithslater\Easyapns\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Keithslater\Easyapns\Easyapns;

class ApnsCommand extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'apns';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Run an APNS task - register, fetch, flush';

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function fire()
	{
		new Easyapns(array('task' => $this->argument('task')));
	}


	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getArguments()
	{
		return array(
			array('task', InputArgument::REQUIRED, 'register, fetch, flush'),
		);
	}
}
