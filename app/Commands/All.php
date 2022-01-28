<?php

	namespace App\Commands;

	use App\Models\Domain;

	class All extends Lookup
	{
		/**
		 * The signature of the command.
		 *
		 * @var string
		 */
		protected $signature = 'all
    {--fields=	: Optional fields to display}';

		/**
		 * The description of the command.
		 *
		 * @var string
		 */
		protected $description = 'Show accounts';

		/**
		 * Execute the console command.
		 *
		 * @return mixed
		 */
		public function handle()
		{
			$fields = $this->parseFields();
			$this->table(
				collect(static::FIELD_LABELS)->intersectByKeys($fields->flip()),
				$this->retrieveDomains($fields)->toArray()
			);
		}
	}
