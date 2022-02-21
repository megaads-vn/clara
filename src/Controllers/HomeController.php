<?php

namespace Megaads\Clara\Controllers;

class HomeController extends BaseController
{

    public function requestIndex()
    {
        return view('clara::requests.index');
    }
}