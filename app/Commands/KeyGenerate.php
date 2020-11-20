<?php

	namespace App\Commands;

	use App\Models\Server;
	use Illuminate\Console\Command;
	use Illuminate\Console\ConfirmableTrait;
	use Illuminate\Encryption\Encrypter;
	use Illuminate\Support\Str;

	class KeyGenerate extends Command
	{
		use ConfirmableTrait;

		/**
		 * The name and signature of the console command.
		 *
		 * @var string
		 */
		protected $signature = 'key:generate
                    {--show : Display the key instead of modifying files}
                    {--force : Force the operation to run when in production rolling encrypted keys}';

		/**
		 * The console command description.
		 *
		 * @var string
		 */
		protected $description = 'Set the application key';

		/**
		 * Execute the console command.
		 *
		 * @return void
		 */
		public function handle()
		{
			$key = $this->generateRandomKey();

			if ($this->option('show')) {
				return $this->line('<comment>' . $key . '</comment>');
			}

			// Next, we will replace the application key in the environment file so it is
			// automatically setup for this developer. This key gets generated using a
			// secure random byte generator and is later base64 encoded for storage.
			if (!$this->setKeyInEnvironmentFile($key)) {
				return;
			}

			$this->laravel['config']['app.key'] = $key;

			$this->info('Application key set successfully.');
		}

		/**
		 * Generate a random key for the application.
		 *
		 * @return string
		 */
		protected function generateRandomKey()
		{
			return 'base64:' . base64_encode(
					Encrypter::generateKey($this->laravel['config']['app.cipher'])
				);
		}

		/**
		 * Set the application key in the environment file.
		 *
		 * @param string $key
		 * @return bool
		 */
		protected function setKeyInEnvironmentFile($key)
		{
			$currentKey = $this->laravel['config']['app.key'];

			if (strlen($currentKey) !== 0 && (!$this->confirmToProceed())) {
				return false;
			}

			$this->updateEncryption($currentKey, $key);
			$this->writeNewEnvironmentFileWith($key);

			return true;
		}

		private function updateEncryption(string $old, string $new): void
		{
			foreach (Server::all() as $s) {
				if (!$s->auth_key) {
					continue;
				}
				$updated = (new Encrypter($this->parseKey($new), $this->laravel['config']['app.cipher']))->encrypt(
					$s->auth_key
				);
				$s->setAuthKeyAttribute($updated, false);
				$s->save();
			}

			$this->laravel->singleton('encrypter', function ($app) use ($new) {
				$config = $app->make('config')->get('app');
				return new Encrypter($this->parseKey($new), $config['cipher']);
			});
		}

		private function parseKey(string $key)
		{
			if (Str::startsWith($key, $prefix = 'base64:')) {
				$key = base64_decode(Str::after($key, $prefix));
			}

			return $key;
		}

		/**
		 * Write a new environment file with the given key.
		 *
		 * @param string $key
		 * @return void
		 */
		protected function writeNewEnvironmentFileWith($key)
		{
			file_put_contents($this->laravel->environmentFilePath(), preg_replace(
				$this->keyReplacementPattern(),
				'APP_KEY=' . $key,
				file_get_contents($this->laravel->environmentFilePath())
			));
		}

		/**
		 * Get a regex pattern that will match env APP_KEY with any random key.
		 *
		 * @return string
		 */
		protected function keyReplacementPattern()
		{
			$escaped = preg_quote('=' . $this->laravel['config']['app.key'], '/');

			return "/^APP_KEY{$escaped}/m";
		}
	}