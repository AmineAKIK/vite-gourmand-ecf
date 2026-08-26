<?php

namespace App\Controllers;

use App\Config\Configuration;
use App\Models\AvisModel;
use App\Models\SiteImageModel;

class HomeController
{
    public function index(): void
    {
        $avisValides = AvisModel::getHomepage();
        $siteImages = SiteImageModel::getAll();
        $heroUrl = isset($siteImages['hero']) && $siteImages['hero'] !== ''
            ? imageUrl($siteImages['hero'], '')
            : null;
        $preloadImages = $heroUrl !== null ? [$heroUrl] : [];
        $heroSousTitre = Configuration::get('content.home.hero_subtitle');
        $heroParagraphe = Configuration::get('content.home.hero_paragraph');
        $introTitle = Configuration::get('content.home.intro_title');
        $introBody = Configuration::get('content.home.intro_body');
        $ctaLabel = Configuration::get('content.home.cta_label');
        $ctaUrl = Configuration::get('content.home.cta_url');
        $reviewsTitle = Configuration::get('content.home.reviews_title');
        $reviewsDescription = Configuration::get('content.home.reviews_description');
        $seoTitle = Configuration::get('seo.home.title');
        $metaDescription = Configuration::get('seo.home.description');

        view('pages/home', compact(
            'avisValides', 'preloadImages', 'heroUrl', 'heroSousTitre', 'heroParagraphe',
            'introTitle', 'introBody', 'ctaLabel', 'ctaUrl', 'reviewsTitle',
            'reviewsDescription', 'seoTitle', 'metaDescription',
        ));
    }
}
