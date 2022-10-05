<?php

namespace Megaads\Clara\Commands;

use Illuminate\Support\Facades\Artisan;

class ModuleMigrationStatusCommand extends AbtractCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'module:migration:status 
                    {module=n/a : Name of module run migration}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run module migration with multiple localization if it enable';

    /**
     * Execute the console command.
     */
    public function handle() {
        $module = $this->argument('module');

        $modulePath =  app_path() . '/Modules/' . $module . '/Migrations';
        if (file_exists($modulePath)) {
            $options = [
                '--path' => "/app/Modules/$module/Migrations"
            ];
            Artisan::call('migrate:status', $options);
            $result = Artisan::output();
            echo $result;
        } else {
            $this->error("The module no exists. Please check again!");
        }
    }
}