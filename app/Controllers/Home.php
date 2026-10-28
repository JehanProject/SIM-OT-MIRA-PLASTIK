<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index(): string
    {
        helper('url');

        return view('mira_plastik');
    }
}
