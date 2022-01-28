<?php

namespace App\Commands;

use App\Models\Domain;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Output\OutputInterface;

class Clean extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'clean';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Remove "deleted" domains from database';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
		if ($this->output->isVerbose()) {
			Domain::where('status','deleted')->get()->each(function (Domain $model) {
				$line = $model->domain;
				if ($this->output->isVeryVerbose()) {
					$line .= " " . $model->server_name;
				}
				$this->info($line);
			});
		}
		$count = Domain::where('status','deleted')->delete();
		$this->info("Deleted ${count} domains from database");
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        $schedule->command(static::class)->daily();
    }
}
