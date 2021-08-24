<?php 
namespace Megaads\Clara\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class ModuleMigrationCommand extends AbtractCommand 
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'module:migration 
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
        $modulePath =  app_path() . '/Modules/' . $module;
        if (file_exists($modulePath)) {
            $locales = [];
            $defaultLocale = '';
            if (env('LOCALIZATION')) {
                $locales = getModuleLocale();
                $defaultLocale = Config::get('localization::module.default_locale');
            }   
            if (!empty($locales)) {
                foreach ($locales as $locale) {
                    if ($defaultLocale == $locale) {
                        $locale = '';
                    }
                    $env = ($locale == '') ? 'env.php' : $locale . '.env.php';
                    if (!file_exists(base_path() . '/' . $env)) {
                        $this->error('File ' . $env . ' does not exists. Please check again!');
                    }
                    $findRes = $this->getFileParams('DB_DATABASE', base_path() . '/' . $env);
                    if (!empty($findRes)) {
                        Config::set('database.connections.mysql.database', $findRes);
                        DB::purge('mysql');
                        $db = Config::get('database.connections.mysql.database');
                        $this->info($db);
                        Artisan::call("migrate", [
                            '--database' => 'mysql',
                            '--path' => 'app/Modules/' . $module . '/Migrations/'
                        ]);
                    }
                }
            } else {
                Artisan::call("migrate", [
                    '--database' => 'mysql',
                    '--path' => 'app/Modules/' . $module . '/Migrations/'
                ]);
            }
        } else {
            $this->error("The module no exists. Please check again!");
        }
    }

    protected function getFileParams($search, $filePath) {
        $retval = '';
        $matches = array();
        $handle = @fopen($filePath, "r");
        if ($handle)
        {
            while (!feof($handle))
            {
                $buffer = fgets($handle);
                if(strpos($buffer, $search) !== FALSE)
                    $matches[] = $buffer;
            }
            fclose($handle);
            $retval = str_replace(['"' .$search . '" => "', '"', ','], '', $matches[0]);
        }
        //show results:
        return trim($retval);
    }
}