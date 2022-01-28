<?php

	namespace App\Commands;

	use App\Models\Domain;
	use Illuminate\Support\Collection;
	use LaravelZero\Framework\Commands\Command;

	class Lookup extends Command
	{
		const FIELD_LABELS = [
			'domain'      => 'Domain',
			'server_name' => 'Node name',
			'di_invoice'  => 'Invoice',
			'admin_email' => 'Email',
			'domainold'   => 'Old domain',
			'status'      => 'Status',
			'addon'       => 'Addon domain',
		];

		const DEFAULT_FIELDS = [
			'domain',
			'name',
			'invoice'
		];

		const FIELD_NICE_MAP = [
			'invoice'   => 'di_invoice',
			'name'      => 'server_name',
			'email'     => 'admin_email',
			'domainold' => 'original_domain',
		];

		/**
		 * The signature of the command.
		 *
		 * @var string
		 */
		protected $signature = 'lookup
    {name		: Domain name or invoice to lookup}
    {--fields=	: Optional fields to display}';

		/**
		 * The description of the command.
		 *
		 * @var string
		 */
		protected $description = 'Find account metadata';

		/**
		 * Execute the console command.
		 *
		 * @return mixed
		 */
		public function handle()
		{
			$fields = $this->parseFields();

			$this->table(
				collect($fields->flip())->replace(self::FIELD_LABELS)->intersectByKeys($fields->flip()),
				$this->retrieveDomains($fields)->toArray()
			);
		}

		protected function retrieveDomains(Collection $fields): Collection
		{
			$columns = $fields->values()->toArray();
			$eloquent = (new Domain)->newFromBuilder();

			if ($this->hasArgument('name')) {
				$eloquent = $eloquent->where('domain', '=', $this->argument('name'));
			}

			if (!$fields->contains("status")) {
				$eloquent = $eloquent->where('status', '!=', 'deleted');
			}

			return $eloquent->get($columns);
		}

		protected function parseFields(): Collection
		{
			$fields = $this->option('fields') ?
				explode(',', $this->option('fields')) : static::DEFAULT_FIELDS;
			$fields = array_combine($fields, $fields);

			return collect($fields)->map(static function ($v) {
				return static::FIELD_NICE_MAP[$v] ?? $v;
			});
		}
	}
