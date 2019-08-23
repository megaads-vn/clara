<?php
Module::onView('title', function ($data) {
    return $data['page'] . ': This is title from Example Module';
}, 10);
Module::onView('header', function ($data) {
    return view('example::includes.header', [
        'moduleHeader' => getModuleOption('option.header'),
    ]);
}, 10);
Module::onView('content', function ($data) {
    return 'This is content view from Example Module';
});
Module::onAction('module_loaded', function ($data) {
    var_dump('on module_loaded: ' . json_encode($data));
}, 10);
