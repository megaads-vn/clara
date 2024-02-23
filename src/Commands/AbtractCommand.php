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

    protected function response($data)
    {
        $response = json_encode($data);
        if ($data['status'] == 'successful') {
            $this->displayMessage($data['message'], 's');
        } else {
            $this->displayMessage($data['message'], 'e');
        }
    }

    /**
     * @param $msg
     * @param $type
     * @return void
     */
    protected function displayMessage($msg, $type = 'i')
    {
        switch ($type) {
            case 'e': //error
                echo "\033[1;3;31m$msg \e[0m\n";
                break;
            case 's': //success
                echo "\033[1;3;32m$msg \e[0m\n";
                break;
            case 'w': //warning
                echo "\033[1;3;33m$msg \e[0m\n";
                break;
            case 'i': //info
                echo "\033[1;3;36m$msg \e[0m\n";
                break;
            default:
                # code...
                break;
        }
    }
}
