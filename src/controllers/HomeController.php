<?php
// src/controllers/HomeController.php
class HomeController {
    public function index(): void {
        $avisValides    = AvisModel::getValidated();
        $preloadImages  = ['/images/hero-traiteur-bordeaux.webp'];
        view('pages/home', compact('avisValides', 'preloadImages'));
    }
}
