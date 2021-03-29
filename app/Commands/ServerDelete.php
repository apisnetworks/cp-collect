<?php

	namespace App\Commands;

	use App\Models\Server;
	use LaravelZero\Framework\Commands\Command;

	class ServerDelete extends Command
	{
		/**
		 * The signature of the command.
		 *
		 * @var string
		 */
		protected $signature = 'server:delete 
        {name            : Server hostname to add}
        ';

		/**
		 * The description of the command.
		 *
		 * @var string
		 */
		protected $description = 'Delete a panel server';

		/**
		 * Execute the console command.
		 *
		 * @return mixed
		 */
		public function handle()
		{
			$server = $this->argument('name');
			$record = Server::findOrFail($server);
			$record->delete();
		}
	}
