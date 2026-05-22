<?php
// src/controllers/HomeController.php
require_once __DIR__ . '/../models/MenuModel.php';

class HomeController {
    public function index(): void {
        view('pages/home');
    }
}
