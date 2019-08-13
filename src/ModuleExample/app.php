<?php
Clara::onView('title', function ($data) {
    return $data['page'] . ': This is title from Example Module';
}, 10);
Clara::onView('header', function ($data) {
    return view('example::includes.header', [
        'moduleHeader' => getModuleOption('option.header'),
    ]);
}, 10);
Clara::onView('content', function ($data) {
    return 'This is content view from Example Module';
});
Clara::onAction('init', function ($data) {
    var_dump('on init action: ' . json_encode($data));
}, 10);
