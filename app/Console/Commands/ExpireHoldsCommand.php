<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Hold;

class ExpireHoldsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:expire-holds-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $holds = Hold::where('status', 'active')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($holds as $hold) {
            $hold->status = 'expired';
            $hold->save();
        }

        $this->info('Expired holds updated.');

        return self::SUCCESS;
    }
}
