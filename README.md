# Clara
A module management in Laravel

## System requirements
 - PHP: >=5.6
 - Laravel Framework: >=5.4

## Installation

Clara is packed as a composer package. So it can be installed quickly:
1. Require the composer package

    `composer require megaads/clara`

2. Register the provider:

    `Megaads\Clara\Providers\ModuleServiceProvider`

3. Register the facade:

    `Megaads\Clara\Facades\ModuleFacade`

4. Autoloading

By default, Module classes are not loaded automatically. You can autoload your modules in composer.json
```json
{
    "autoload": {
        "psr-4": {            
            "App\\": "app/",
            "Modules\\": "app/Modules"            
        }
    },
    "extra": {
        "merge-plugin": {
            "include": [
                "app/Modules/*/module.json"
            ]
        }
    }
}
```

5. Publish Clara configurations

To override Clara default configuration options, you will need to publish Clara configuration file to the application's config directory.
```
php artisan vendor:publish --provider="Megaads\Clara\Providers\ModuleServiceProvider"
```

## Module Management

### Create module

```
php artisan module:make <ModuleName> ...
```

Folder structure

```
app
│
└───Modules
    
    └───ModuleName
        │
        └───Config
        │      app.php
        │
        └───Controllers
        │      Controller.php
        │      ...
        │
        └───Helpers
        │      helper.php
        │      ...
        │
        └───Middlewares
        │      ExampleMiddleware.php
        │      ...
        │
        └───Models
        │      ...
        │
        └───Resources
        │      Views
        │      Assets
        │      ...
        │
        └───Routes
        │      routes.php
        │      ...
        │
        └───Kernel.php
        │
        └───module.json
        │
        └───start.php
```
- module.json: the module configuration file is based on composer composer.json. All properties in the module.json will be merged to main composer.json.
- start.php: the module's start file that will be loaded every requests. So module actions, module views... can be registered in this file.

### Install module from a file or an URL

```
php artisan module:install <ZipFilePath> <ZipFileURL> ...
```

### Enable module

```
php artisan module:enable <ModuleName> ...
```

### Disable module

```
php artisan module:disable <ModuleName> ...
```

### Remove module

```
php artisan module:remove <ModuleName> ...
```

### Remove all modules

```
php artisan module:remove-all
```

## Module Action

### Fire a action

Using PHP
```php
Module::action('action_name', [params]);
```

Using blade statement
```php
@action('action_name', [params])
```

### Handle a action
```php
Module::onAction('action_name', function ($params) {  
      
}, PRIORITY);
```
Handle a action using a controller
```php
Module::onAction('action_name', 'Modules\Example\Controllers\HomeController@action', PRIORITY);
```
By default, Clara supplies actions: 
- module_made
- module_loaded
- module_disabled
- module_enabled
- module_removed
- module_removed_all

## Module View

### Register a view

Using PHP
```php
Module::view('view_name', [params], IS_MULTI_LAYER);
```
```php
Module::view('view_name', 'This is a view placeholder', IS_MULTI_LAYER);
```
```php
Module::view('view_name', function() {
    return 'This is a view placeholder';
}, IS_MULTI_LAYER);
```

Using blade statement
```php
@view('view_name', [params])
```

### Handle a view
```php
Module::onView('view_name', function ($params) {  
    return view('{module-namespace}:home.index');
}, PRIORITY);
```
Handle a view using a controller
```php
Module::onView('view_name', 'Modules\ExampleModule\Controllers\HomeController@index', PRIORITY);
```

## Module Assets

Clara will create a symbol link from module asset directory `app/Modules/{ModuleName}/Resources/Assets` to `public/modules/{module-namespace}` while a module was installed, made or enabled.
### Include a module asset
Using PHP
```php
<script type="text/javascript" src="<?= Module::asset('{module-namespace}/js/demo.js') ?>"></script>
```

Using blade statement
```php
<script type="text/javascript" src="@asset('{module-namespace}/js/demo.js')"></script>
```
### Create module asset link manually
```
php artisan module:asset:link <ModuleName>
```
### Create module asset link manually for all activated modules
```
php artisan module:asset:link --all
```
## Module Configurations

All of the configuration files for the module are stored in the `{ModuleName}/Config` directory

### Accessing Configuration Values
Sometimes you may need to access configuration values at run-time. You may do so using the `Config` class
```
Config::get('{module-namespace}::app.message', 'hello world');
```

## Module Utility Methods

### Get all modules
```php
$modules = Module::all();
```

### Get the current module
```php
$module = Module::this();
```

### Get module options
```php
$option = Module::option('option.name');
```

### Set module option
```php
$option = Module::option('option.name', 'option.value');
```

## License

The Clara is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)

## Contact us / Instant feedback

Email: info@megaads.vn | phult.contact@gmail.com

If you find a bug, please report it [here on Github](https://github.com/megaads-vn/clara/issues)
