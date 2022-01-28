<?php

	namespace App\Commands;

	use App\Models\Server;
	use Illuminate\Support\Arr;
	use LaravelZero\Framework\Commands\Command;
	use RuntimeException;

	class ServerAdd extends Command
	{
		/**
		 * The signature of the command.
		 *
		 * @var string
		 */
		protected $signature = 'server:add 
        {name            : Server nodename to add}
        {--ip=           : Internal IP assignment}
        {--auth=         : Authentication mechanism: "api" or "ssh" key}
        {--key=          : Authentication token, API key, SSH key, or key alias}
    ';

		/**
		 * The description of the command.
		 *
		 * @var string
		 */
		protected $description = 'Add a panel server';

		/**
		 * Execute the console command.
		 *
		 * @return mixed
		 */
		public function handle()
		{
			if (!$this->app['config']['app.key']) {
				throw new RuntimeException("APP_KEY is not set. Run key:generate first");
			}
			$server = $this->argument('name');
			$record = Server::whereServerName($server);
			if ($record->exists()) {
				$record->delete();
				//throw new \InvalidArgumentException("Server ${server} already exists. Delete server first");
			}
			$record = new Server();

			$options = $this->options();
			$ip = Arr::get($options, 'ip');

			if (!$ip) {
				$ip = gethostbyname($server);
				$this->warn("No IP address specified - assuming $server maps to $ip");
			}
			$record->fill(array_filter([
				'server_name' => $server,
				'ip'          => $ip,
				'auth'        => $options['auth'] ?? 'native',
				'auth_key'    => $options['key'] ?? null,
			]));
			$record->test();
			$record->save();
		}
	}
