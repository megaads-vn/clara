<?php

namespace Megaads\Clara\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class ModuleMigrationMakeCommand extends AbtractCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'module:migration:make 
                    {module=n/a : Name of module run migration}
                    {name=n/a : Table name}';
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
        $name = $this->argument('name');

        $modulePath =  app_path() . '/Modules/' . $module . '/Migrations';
        if (file_exists($modulePath) && $name !== 'n/a') {
            Artisan::call('make:migration', [
                'name' => $name,
                '--path' => "/app/Modules/$module/Migrations"
            ]);
            $result = Artisan::output();
            echo "\033[32m $result\e[0m";
        } else {
            $this->error("The module no exists. Please check again!");
        }
    }
}