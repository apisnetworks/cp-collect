<?php

	namespace App\Commands;

	use App\Models\Server;
	use LaravelZero\Framework\Commands\Command;

	class ServerList extends Command
	{
		/**
		 * The signature of the command.
		 *
		 * @var sring
		 */
		protected $signature = 'server:list';

		/**
		 * The description of the command.
		 *
		 * @var string
		 */
		protected $description = 'List configured servers';

		/**
		 * Execute the console command.
		 *
		 * @return mixed
		 */
		public function handle()
		{
			$this->table(
				['Server name', 'IP', 'Auth'],
				Server::all(['server_name', 'ip', 'auth'])->toArray()
			);
		}
	}
