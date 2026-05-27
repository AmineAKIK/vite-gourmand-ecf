<?php

namespace App\Controllers;

class PageController
{
    public function mentions(): void
    {
        view('pages/mentions');
    }

    public function cgv(): void
    {
        view('pages/cgv');
    }
}
