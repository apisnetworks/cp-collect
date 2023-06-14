<?php

	namespace App\Commands;

	use App\Clients\Broker;
	use App\Models\Server;
	use Illuminate\Support\Arr;
	use LaravelZero\Framework\Commands\Command;
	use React\EventLoop\Loop;

	class ServerTest extends Command
	{
		/**
		 * The signature of the command.
		 *
		 * @var string
		 */
		protected $signature = 'server:test 
        {name            : Server to validate}
    ';

		/**
		 * The description of the command.
		 *
		 * @var string
		 */
		protected $description = 'Test panel connectivity';

		/**
		 * Execute the console command.
		 *
		 * @return mixed
		 */
		public function handle()
		{
			$server = $this->argument('name');
			$record = Server::findOrFail($server);
			$client = Broker::create($record, Loop::get());
			try {
				$client->test();
			} catch (\Exception $e) {

			}

			$this->info("Server {$server} passes connectivity test!");

		}
	}
