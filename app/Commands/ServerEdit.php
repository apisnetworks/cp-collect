<?php

	namespace App\Commands;

	use App\Models\Server;
	use Illuminate\Support\Arr;
	use LaravelZero\Framework\Commands\Command;

	class ServerEdit extends Command
	{
		/**
		 * The signature of the command.
		 *
		 * @var string
		 */
		protected $signature = 'server:edit 
        {name            : Server record to edit}
        {--name=         : New server nodename}
        {--ip=           : Internal IP assignment}
        {--auth=         : Authentication mechanism: "api" or "ssh" key}
        {--key=          : Authentication token, API key, SSH key, or key alias}
    ';

		/**
		 * The description of the command.
		 *
		 * @var string
		 */
		protected $description = 'Edit panel server';

		/**
		 * Execute the console command.
		 *
		 * @return mixed
		 */
		public function handle()
		{
			$server = $this->argument('name');
			$record = Server::findOrFail($server);

			$options = $this->options();
			foreach (['ip', 'auth', 'key'] as $opt) {
				$field = $opt;
				if ($opt === 'key') {
					$field = 'auth_key';
				} else if ($opt === 'name') {
					$field = 'server_name';
				}
				if ($this->option($opt)) {
					$record->{$field} = Arr::get($options, $opt);
				}
			}

			$record->save();
		}
	}
