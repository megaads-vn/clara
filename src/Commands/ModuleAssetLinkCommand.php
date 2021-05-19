<?php
namespace Megaads\Clara\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Megaads\Clara\Utils\ModuleUtil;
use Symfony\Component\Console\Input\InputArgument;

class ModuleAssetLinkCommand extends AbtractCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'module:asset:link';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create module asset link';
    /**
     * Get the console command arguments.
     *
     * @return array
     */
    public function getArguments()
    {
        return [
            ['name', InputArgument::IS_ARRAY, 'The names of modules will be linked.'],
        ];
    }
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $names = $this->argument('name');
        foreach ($names as $name) {
            $moduleNamespace = $this->buildNamespace($name);
            $moduleConfigs = ModuleUtil::getAllModuleConfigs();
            if ($moduleConfigs['modules'][$moduleNamespace] == null) {
                $this->response([
                    "status" => "fail",
                    "message" => "Link $name module asset failed. The module's not existed.",
                    "module" => [
                        "name" => $name,
                        "namespace" => $moduleNamespace,
                    ],
                ]);
            } else {
                // link module assets
                ModuleUtil::linkModuleAssets($moduleConfigs['modules'][$moduleNamespace]);                
                $this->response([
                    "status" => "successful",
                    "message" => "$name module assets linked successfully.",
                    "module" => [
                        "name" => $name,
                        "namespace" => $moduleNamespace,
                    ],
                ]);
            }
        }
    }
}
