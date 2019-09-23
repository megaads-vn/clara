<?php
namespace Megaads\Clara\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Input\InputArgument;

class AbtractCommand extends Command
{
    /**
     * Get the console command arguments.
     *
     * @return array
     */
    function getArguments()
    {
        return [
            ['name', InputArgument::IS_ARRAY, 'The names of modules will be created.'],
        ];
    }
    /**
     * Replace a tring in a file
     */
    protected function replaceInFile($filePath, $findString, $replaceString)
    {
        $fileContent = file_get_contents($filePath);
        $fileContent = str_replace($findString, $replaceString, $fileContent);
        file_put_contents($filePath, $fileContent);
    }
    /**
     * Build namespace from name
     */
    protected function buildNamespace($name = '')
    {
        return strtolower(preg_replace('/\B([A-Z])/', '-$1', $name));
    }
}
