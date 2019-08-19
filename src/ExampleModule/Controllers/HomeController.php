<?php

namespace Modules\Example\Controllers;

use Illuminate\Http\Request;
use Modules\Example\Controllers\Controller;
use Module;

class HomeController extends Controller
{
    public function __construct()
    {        
        Module::onView("content", function() {
            return "This is content view from Example Module HomeController";
        }, 5);
    }
    public function index(Request $request)
    {
        $message = config("example::app.message");
        return view('example::home.welcome', [
            'message' => $message,
        ]);
    }
}
