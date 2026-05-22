<?php
// src/controllers/PageController.php

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
