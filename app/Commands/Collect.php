<?php

	namespace App\Commands;

	use App\Clients\Broker;
	use App\Clients\CollectionClient;
	use App\Models\Domain;
	use App\Models\Server;
	use Illuminate\Database\QueryException;
	use Illuminate\Support\Facades\DB;
	use LaravelZero\Framework\Commands\Command;
	use React\EventLoop\Factory;
	use React\EventLoop\Loop;
	use Throwable;
	use function array_sum;
	use function count;

	class Collect extends Command
	{
		/**
		 * The signature of the command.
		 *
		 * @var string
		 */
		protected $signature = 'collect';

		/**
		 * The description of the command.
		 *
		 * @var string
		 */
		protected $description = 'Collect site inventory from servers';

		/**
		 * Execute the console command.
		 *
		 * @return mixed
		 */
		public function handle()
		{
			$loop = Loop::get();
			$servers = Server::all();
			$bar = $this->output->createProgressBar(count($servers));

			foreach ($servers as $s) {
				$client = Broker::create($s, $loop);

				$client->collect()->always(static function () use ($bar) {
					$bar->advance();
					echo "\n";
				})->done(function ($val) use ($client) {
					$siteIds = [];
					foreach ($val as $site => $meta) {
						$siteIds[] = (int)substr($site, 4);
						$sites = [$this->map($meta, $site, $client)];
						if (null === $sites[0]['di_invoice']) {
							$this->warn("Missing invoice on " . $sites[0]['domain'] . " (${site})/" . $client->getName() . " - skipping");
							continue;
						}
						foreach ($meta['aliases']['aliases'] ?? [] as $addon) {
							$sites[] = [
									'domain'      => $addon,
									'addon'       => true,
									'admin_email' => null
								] + $this->map($meta, $site, $client);
						}

						try {
							DB::transaction(function () use ($client, $sites, $site) {
								Domain::where('server_name', $client->getName())->where('site_id',
									(int)substr($site, 4))->delete();
								Domain::insert($sites);
							});
						} catch (QueryException $e) {
							$this->error("Failed to update records: " . $e->getMessage());
							exit(1);
						}
					}
					Domain::where('server_name', $client->getName())->whereNotIn('site_id',
						$siteIds)->update(['status' => 'deleted']);
					$this->info(
						sprintf(" ✔️ Completed %s (+%d accounts, +%d domains)\n",
							$client->getName(),
							count($val),
							array_sum(array_map(static function ($meta) {
								return count($meta['aliases']['aliases'] ?? []) + 1;
							}, $val))
						)
					);
				}, function (Throwable $reason) use ($s) {
					$this->warn(" ❌ Skipping " . $s->server_name . ': ' . $reason->getMessage());
				});
			}

			$loop->run();
		}

		private function map(array $meta, string $site, CollectionClient $client): array
		{

			return [
				'domain'      => $meta['siteinfo']['domain'],
				'addon'       => false,
				'admin_email' => $meta['siteinfo']['email'],
				'server_name' => $client->getName(),
				'status'      => $meta['active'] ? 'active' : 'disabled',
				'site_id'     => (int)substr($site, 4),
				'di_invoice'  => $meta['billing']['invoice'] ?? $meta['billing']['parent_invoice']
			];
		}
	}
