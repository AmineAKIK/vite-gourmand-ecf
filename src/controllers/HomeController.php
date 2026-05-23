<?php
// src/controllers/HomeController.php
class HomeController {
    public function index(): void {
        $avisValides   = AvisModel::getValidated();
        $siteImages    = SiteImageModel::getAll();
        $heroUrl       = imageUrl($siteImages['hero']        ?? null, 'images/hero-traiteur-bordeaux.webp');
        $preparationUrl = imageUrl($siteImages['preparation'] ?? null, 'images/preparation-traiteur.webp');
        $preloadImages   = [$heroUrl];
        $heroSousTitre   = SiteConfigModel::get('hero_sous_titre', 'Traiteur bordelais depuis 25 ans');
        $heroParagraphe  = SiteConfigModel::get('hero_paragraphe', 'Depuis 25 ans, Vite & Gourmand accompagne les particuliers et les professionnels avec une cuisine traiteur généreuse, raffinée et préparée à Bordeaux.');
        view('pages/home', compact('avisValides', 'preloadImages', 'heroUrl', 'preparationUrl', 'heroSousTitre', 'heroParagraphe'));
    }
}
