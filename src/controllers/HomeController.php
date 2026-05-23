<?php
// src/controllers/HomeController.php
class HomeController {
    public function index(): void {
        $avisValides   = AvisModel::getValidated();
        $siteImages    = SiteImageModel::getAll();
        $heroUrl       = imageUrl($siteImages['hero']        ?? null, 'images/hero-traiteur-bordeaux.webp');
        $preparationUrl = imageUrl($siteImages['preparation'] ?? null, 'images/preparation-traiteur.webp');
        $preloadImages  = [$heroUrl];
        $heroSousTitre  = SiteConfigModel::get('hero_sous_titre', 'Traiteur bordelais depuis 25 ans');
        view('pages/home', compact('avisValides', 'preloadImages', 'heroUrl', 'preparationUrl', 'heroSousTitre'));
    }
}
