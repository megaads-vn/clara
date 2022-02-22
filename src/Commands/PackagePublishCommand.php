<?php

namespace Megaads\Clara\Commands;

use Illuminate\Console\Command;

class PackagePublishCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clara:publish 
                            {--tag=n/a : What do you want to publish.}
                            {--force : Is overwrite exists file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $tag = $this->option('tag');
        $force = (int) $this->option('force');
        if ($tag == 'n/a') {
            $this->error('Please, choose the tag you want to publish');
        } else {
            $artisanOptions = [
                '--provider' => "Megaads\Clara\Providers\ModuleServiceProvider",
                '--tag' => $tag,
            ];
            if ($force) {
                $artisanOptions['--force'] = true;
            }
            \Artisan::call('vendor:publish', $artisanOptions);
            $result = \Artisan::output();
            $this->info($result);
        }
    }
}
