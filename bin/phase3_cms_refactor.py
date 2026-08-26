from pathlib import Path


def patch(path: str, old: str, new: str) -> None:
    p = Path(path)
    text = p.read_text(encoding="utf-8")
    if old not in text:
        raise SystemExit(f"expected snippet missing in {path}: {old[:120]!r}")
    p.write_text(text.replace(old, new, 1), encoding="utf-8")


registry_old = """            self::tenant('content.home.hero_subtitle', 'hero_sous_titre', ConfigurationType::STRING, false, $admin, 'content', 'Sous-titre du hero de la page d’accueil.', null, ['max_length' => 60]),
            self::tenant('content.home.hero_paragraph', 'hero_paragraphe', ConfigurationType::TEXT, false, $admin, 'content', 'Paragraphe du hero de la page d’accueil.', null, ['max_length' => 200]),
            self::tenant('legal.terms_content'"""
registry_new = """            self::tenant('content.home.hero_subtitle', 'hero_sous_titre', ConfigurationType::STRING, false, $admin, 'content', 'Sous-titre du hero de la page d’accueil.', null, ['max_length' => 120]),
            self::tenant('content.home.hero_paragraph', 'hero_paragraphe', ConfigurationType::TEXT, false, $admin, 'content', 'Paragraphe du hero de la page d’accueil.', null, ['max_length' => 500]),
            self::tenant('content.home.intro_title', 'home_intro_titre', ConfigurationType::STRING, false, $admin, 'content', 'Titre de présentation de la page d’accueil.', null, ['max_length' => 120]),
            self::tenant('content.home.intro_body', 'home_intro_texte', ConfigurationType::TEXT, false, $admin, 'content', 'Texte de présentation de la page d’accueil.', null, ['max_length' => 2000]),
            self::tenant('content.home.cta_label', 'home_cta_libelle', ConfigurationType::STRING, false, $admin, 'content', 'Libellé du CTA de la page d’accueil.', null, ['max_length' => 80]),
            self::tenant('content.home.cta_url', 'home_cta_url', ConfigurationType::STRING, false, $admin, 'content', 'URL du CTA de la page d’accueil.', null, ['max_length' => 255]),
            self::tenant('content.home.reviews_title', 'home_avis_titre', ConfigurationType::STRING, false, $admin, 'content', 'Titre du bloc avis.', null, ['max_length' => 120]),
            self::tenant('content.home.reviews_description', 'home_avis_description', ConfigurationType::TEXT, false, $admin, 'content', 'Introduction du bloc avis.', null, ['max_length' => 500]),
            self::tenant('content.contact.title', 'contact_titre', ConfigurationType::STRING, false, $admin, 'content', 'Titre de la page contact.', null, ['max_length' => 120]),
            self::tenant('content.contact.intro', 'contact_intro', ConfigurationType::TEXT, false, $admin, 'content', 'Introduction de la page contact.', null, ['max_length' => 500]),
            self::tenant('contact.response_sla_hours', 'contact_delai_reponse_heures', ConfigurationType::INTEGER, false, $admin, 'content', 'Délai de réponse public annoncé, en heures.', null, ['min' => 1, 'max' => 720]),
            self::tenant('content.footer.text', 'footer_texte', ConfigurationType::TEXT, false, $admin, 'content', 'Texte éditorial optionnel du footer.', null, ['max_length' => 500]),
            self::tenant('seo.home.title', 'seo_home_titre', ConfigurationType::STRING, false, $admin, 'seo', 'Titre SEO de la page d’accueil.', null, ['max_length' => 70]),
            self::tenant('seo.home.description', 'seo_home_description', ConfigurationType::TEXT, false, $admin, 'seo', 'Meta description de la page d’accueil.', null, ['max_length' => 180]),
            self::tenant('seo.contact.title', 'seo_contact_titre', ConfigurationType::STRING, false, $admin, 'seo', 'Titre SEO de la page contact.', null, ['max_length' => 70]),
            self::tenant('seo.contact.description', 'seo_contact_description', ConfigurationType::TEXT, false, $admin, 'seo', 'Meta description de la page contact.', null, ['max_length' => 180]),
            self::tenant('legal.terms_content'"""
patch('src/Config/ConfigurationRegistry.php', registry_old, registry_new)

home_old = """        $heroSousTitre = Configuration::get('content.home.hero_subtitle');
        $heroParagraphe = Configuration::get('content.home.hero_paragraph');

        view('pages/home', compact(
            'avisValides',
            'preloadImages',
            'heroUrl',
            'heroSousTitre',
            'heroParagraphe',
        ));"""
home_new = """        $heroSousTitre = Configuration::get('content.home.hero_subtitle');
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
        ));"""
patch('src/Controllers/HomeController.php', home_old, home_new)

patch('src/Controllers/ContactController.php', 'use App\\Domain\\InputPolicy;', 'use App\\Config\\Configuration;\nuse App\\Domain\\InputPolicy;')
patch('src/Controllers/ContactController.php', "        view('pages/contact', compact('sujet'));", """        $contactTitle = Configuration::get('content.contact.title');
        $contactIntro = Configuration::get('content.contact.intro');
        $seoTitle = Configuration::get('seo.contact.title');
        $metaDescription = Configuration::get('seo.contact.description');
        view('pages/contact', compact('sujet', 'contactTitle', 'contactIntro', 'seoTitle', 'metaDescription'));""")
patch('src/Controllers/ContactController.php', "        flash('success', 'Votre message a bien été envoyé ! Nous vous répondrons sous 48h.');", """        $slaHours = Configuration::get('contact.response_sla_hours');
        $message = 'Votre message a bien été envoyé.';
        if (is_int($slaHours) && $slaHours > 0) {
            $message .= ' Délai de réponse annoncé : ' . $slaHours . ' h.';
        }
        flash('success', $message);""")

patch('src/Controllers/PageController.php', 'use App\\Models\\SiteConfigModel;', 'use App\\Config\\Configuration;')
patch('src/Controllers/PageController.php', "$mentionsContenu = SiteConfigModel::get('mentions_contenu') ?? '';", "$mentionsContenu = Configuration::get('legal.notices_content') ?? '';")
patch('src/Controllers/PageController.php', "$cgvContenu = SiteConfigModel::get('cgv_contenu') ?? '';", "$cgvContenu = Configuration::get('legal.terms_content') ?? '';")

Path('src/Views/pages/home.php').write_text('''<?php
$pageTitle = is_string($seoTitle ?? null) && trim($seoTitle) !== '' ? $seoTitle : buildPageTitle();
?>
<section class="hero hero-home text-center" aria-label="Présentation de l’entreprise">
    <?php if (!empty($heroUrl)): ?><img src="<?= sanitize($heroUrl) ?>" class="hero-bg" alt="" aria-hidden="true" fetchpriority="high" decoding="async"><?php endif; ?>
    <div class="container hero-content">
        <h1 class="fw-bold mb-3"><?= sanitize(siteName()) ?></h1>
        <?php $subtitle = is_string($heroSousTitre ?? null) && trim($heroSousTitre) !== '' ? $heroSousTitre : siteSlogan(); ?>
        <?php if ($subtitle !== ''): ?><p class="subtitle mb-4"><?= sanitize($subtitle) ?></p><?php endif; ?>
        <?php if (is_string($heroParagraphe ?? null) && trim($heroParagraphe) !== ''): ?><p class="lead text-white-50 col-lg-8 mx-auto"><?= nl2br(sanitize($heroParagraphe)) ?></p><?php endif; ?>
        <?php if (is_string($ctaLabel ?? null) && trim($ctaLabel) !== '' && is_string($ctaUrl ?? null) && trim($ctaUrl) !== ''): ?><a href="<?= sanitize($ctaUrl) ?>" class="btn btn-brand btn-lg mt-2"><?= sanitize($ctaLabel) ?></a><?php endif; ?>
    </div>
</section>
<?php if (trim((string)($introTitle ?? '')) !== '' || trim((string)($introBody ?? '')) !== ''): ?>
<section class="py-5"><div class="container col-lg-8 text-center">
    <?php if (trim((string)$introTitle) !== ''): ?><h2><?= sanitize($introTitle) ?></h2><?php endif; ?>
    <?php if (trim((string)$introBody) !== ''): ?><p class="lead text-muted mb-0"><?= nl2br(sanitize($introBody)) ?></p><?php endif; ?>
</div></section>
<?php endif; ?>
<?php if (!empty($avisValides)): ?>
<section class="py-5 bg-surface-subtle" aria-labelledby="avis-titre"><div class="container">
    <h2 id="avis-titre" class="text-center mb-2"><?= sanitize((is_string($reviewsTitle ?? null) && trim($reviewsTitle) !== '') ? $reviewsTitle : 'Avis clients') ?></h2>
    <?php if (is_string($reviewsDescription ?? null) && trim($reviewsDescription) !== ''): ?><p class="text-center text-muted mb-5"><?= sanitize($reviewsDescription) ?></p><?php endif; ?>
    <div class="row g-4"><?php foreach ($avisValides as $avis): ?><div class="col-12 col-md-6 col-lg-4"><article class="card h-100 p-3"><div class="card-body">
        <div class="stars mb-2" aria-label="Note : <?= (int)$avis['note'] ?> sur 5"><?= str_repeat('★', (int)$avis['note']) . str_repeat('☆', 5 - (int)$avis['note']) ?></div>
        <p class="card-text fst-italic">“<?= htmlspecialchars(html_entity_decode(trim($avis['description'] ?? ''), ENT_QUOTES, 'UTF-8'), ENT_COMPAT, 'UTF-8') ?>”</p>
        <footer class="text-muted small mt-3"><strong><?= sanitize(personFullName($avis)) ?></strong><?php if (!empty($avis['menu_titre'])): ?> · Menu : <?= sanitize($avis['menu_titre']) ?><?php endif; ?></footer>
    </div></article></div><?php endforeach; ?></div>
</div></section>
<?php endif; ?>
''', encoding='utf-8')

contact = Path('src/Views/pages/contact.php')
text = contact.read_text(encoding='utf-8')
text = text.replace("<?php $pageTitle = buildPageTitle('Contact'); ?>", "<?php $pageTitle = is_string($seoTitle ?? null) && trim($seoTitle) !== '' ? $seoTitle : buildPageTitle('Contact'); ?>")
text = text.replace('<h1 class="fw-bold mt-2">Contactez-nous</h1>\n                <p class="text-muted">Une question ou une demande particulière ? Envoyez-nous votre message.</p>', '''<h1 class="fw-bold mt-2"><?= sanitize((is_string($contactTitle ?? null) && trim($contactTitle) !== '') ? $contactTitle : 'Contact') ?></h1>
                <?php if (is_string($contactIntro ?? null) && trim($contactIntro) !== ''): ?><p class="text-muted"><?= sanitize($contactIntro) ?></p><?php endif; ?>''')
contact.write_text(text, encoding='utf-8')

layout = Path('src/Views/layouts/main.php')
text = layout.read_text(encoding='utf-8')
text = text.replace('    <title><?= sanitize($pageTitle ?? buildPageTitle()) ?></title>', '    <title><?= sanitize($pageTitle ?? buildPageTitle()) ?></title>\n    <?php if (isset($metaDescription) && is_string($metaDescription) && trim($metaDescription) !== \'\'): ?><meta name="description" content="<?= sanitize($metaDescription) ?>"><?php endif; ?>')
text = text.replace('    <meta property="og:title" content="<?= sanitize(siteName()) ?><?= siteSlogan() !== \'\' ? \' — \' . sanitize(siteSlogan()) : \'\' ?>">', '    <meta property="og:title" content="<?= sanitize($pageTitle ?? buildPageTitle()) ?>">')
text = text.replace("    <?php if (siteSlogan() !== ''): ?>\n        <meta property=\"og:description\" content=\"<?= sanitize(siteSlogan()) ?>\">\n    <?php endif; ?>", "    <?php if (isset($metaDescription) && is_string($metaDescription) && trim($metaDescription) !== ''): ?><meta property=\"og:description\" content=\"<?= sanitize($metaDescription) ?>\"><?php endif; ?>")
text = text.replace('        <div class="text-center mt-3 pt-3 border-top border-secondary">', '''        <?php $footerText = \\App\\Config\\Configuration::get('content.footer.text'); ?>
        <?php if (is_string($footerText) && trim($footerText) !== ''): ?><p class="text-center text-secondary mt-3 mb-0"><?= sanitize($footerText) ?></p><?php endif; ?>
        <div class="text-center mt-3 pt-3 border-top border-secondary">''')
layout.write_text(text, encoding='utf-8')

admin = Path('src/Controllers/Admin/ParametresController.php')
text = admin.read_text(encoding='utf-8')
old = '''        $sousTitre = $_POST['hero_sous_titre'] ?? '';
        $paragraphe = $_POST['hero_paragraphe'] ?? '';
        if (!is_string($sousTitre) || !is_string($paragraphe)) {
            flash('error', 'Le contenu de personnalisation est invalide.');
            redirect('/admin/parametres#personnalisation');
        }

        try {
            ConfigurationWriter::writeStorageKey('hero_sous_titre', trim($sousTitre));
            ConfigurationWriter::writeStorageKey('hero_paragraphe', trim($paragraphe, " \\t\\r"));
        } catch (ConfigurationInvalidException) {'''
new = '''        $contentKeys = [
            'hero_sous_titre', 'hero_paragraphe', 'home_intro_titre', 'home_intro_texte',
            'home_cta_libelle', 'home_cta_url', 'home_avis_titre', 'home_avis_description',
            'contact_titre', 'contact_intro', 'contact_delai_reponse_heures', 'footer_texte',
            'seo_home_titre', 'seo_home_description', 'seo_contact_titre', 'seo_contact_description',
        ];
        try {
            foreach ($contentKeys as $storageKey) {
                $raw = $_POST[$storageKey] ?? '';
                if (!is_string($raw)) {
                    throw new ConfigurationInvalidException('Configuration invalid: ' . $storageKey);
                }
                ConfigurationWriter::writeStorageKey($storageKey, trim($raw));
            }
        } catch (ConfigurationInvalidException) {'''
if old not in text:
    raise SystemExit('ParametresController personalization snippet missing')
admin.write_text(text.replace(old, new, 1), encoding='utf-8')

view = Path('src/Views/pages/admin/parametres.php')
text = view.read_text(encoding='utf-8')
text = text.replace("value=\"<?= $cfg('site_nom', 'Mon Traiteur') ?>\"", "value=\"<?= $cfg('site_nom') ?>\"")
text = text.replace('placeholder="Ex : Traiteur lyonnais depuis 1998"', 'placeholder="Accroche propre à votre entreprise"')
text = text.replace('Accroche courte, mise en valeur en couleur dorée.', 'Accroche courte affichée dans le hero.')
text = text.replace('<p class="text-muted small mb-2">Aucun favicon — favicon par défaut utilisé.</p>', '<p class="text-muted small mb-2">Aucun favicon configuré.</p>')
text = text.replace('<p class="text-muted small mb-2">Aucune — image par défaut utilisée.</p>', '<p class="text-muted small mb-2">Aucune image de partage configurée.</p>')
text = text.replace("<img src=\"<?= sanitize(imageUrl($images['hero'] ?? null, 'images/hero-traiteur.webp')) ?>\"\n                                 alt=\"Image hero actuelle\" class=\"img-fluid rounded mb-2\"\n                                 style=\"max-height:160px;width:100%;object-fit:cover;\" id=\"preview-hero\">", "<?php if (!empty($images['hero'])): ?><img src=\"<?= sanitize($images['hero']) ?>\" alt=\"Image hero actuelle\" class=\"img-fluid rounded mb-2\" style=\"max-height:160px;width:100%;object-fit:cover;\" id=\"preview-hero\"><?php else: ?><p class=\"text-muted small\">Aucune image hero configurée.</p><?php endif; ?>")
text = text.replace("<img src=\"<?= sanitize(imageUrl($images['preparation'] ?? null, 'images/preparation-traiteur-generique.webp')) ?>\"\n                                 alt=\"Image équipe actuelle\" class=\"img-fluid rounded mb-2\"\n                                 style=\"max-height:160px;width:100%;object-fit:cover;\" id=\"preview-preparation\">", "<?php if (!empty($images['preparation'])): ?><img src=\"<?= sanitize($images['preparation']) ?>\" alt=\"Image de présentation actuelle\" class=\"img-fluid rounded mb-2\" style=\"max-height:160px;width:100%;object-fit:cover;\" id=\"preview-preparation\"><?php else: ?><p class=\"text-muted small\">Aucune image de présentation configurée.</p><?php endif; ?>")
marker = '''                </div>
            </div>

            <!-- Images -->'''
fields = '''                    <hr class="my-4">
                    <h6 class="fw-semibold">Contenu éditorial & SEO</h6>
                    <div class="row g-3">
                        <div class="col-lg-6"><label class="form-label" for="home_intro_titre">Titre de présentation</label><input class="form-control" id="home_intro_titre" name="home_intro_titre" maxlength="120" value="<?= $cfg('home_intro_titre') ?>"></div>
                        <div class="col-12"><label class="form-label" for="home_intro_texte">Texte de présentation</label><textarea class="form-control" id="home_intro_texte" name="home_intro_texte" rows="4" maxlength="2000"><?= $cfg('home_intro_texte') ?></textarea></div>
                        <div class="col-lg-6"><label class="form-label" for="home_cta_libelle">CTA — libellé</label><input class="form-control" id="home_cta_libelle" name="home_cta_libelle" maxlength="80" value="<?= $cfg('home_cta_libelle') ?>"></div>
                        <div class="col-lg-6"><label class="form-label" for="home_cta_url">CTA — URL</label><input class="form-control" id="home_cta_url" name="home_cta_url" maxlength="255" value="<?= $cfg('home_cta_url') ?>"></div>
                        <div class="col-lg-6"><label class="form-label" for="home_avis_titre">Titre des avis</label><input class="form-control" id="home_avis_titre" name="home_avis_titre" maxlength="120" value="<?= $cfg('home_avis_titre') ?>"></div>
                        <div class="col-lg-6"><label class="form-label" for="home_avis_description">Introduction des avis</label><input class="form-control" id="home_avis_description" name="home_avis_description" maxlength="500" value="<?= $cfg('home_avis_description') ?>"></div>
                        <div class="col-lg-6"><label class="form-label" for="contact_titre">Titre contact</label><input class="form-control" id="contact_titre" name="contact_titre" maxlength="120" value="<?= $cfg('contact_titre') ?>"></div>
                        <div class="col-lg-6"><label class="form-label" for="contact_delai_reponse_heures">Délai de réponse annoncé (h)</label><input type="number" min="1" max="720" class="form-control" id="contact_delai_reponse_heures" name="contact_delai_reponse_heures" value="<?= $cfg('contact_delai_reponse_heures') ?>"></div>
                        <div class="col-12"><label class="form-label" for="contact_intro">Introduction contact</label><textarea class="form-control" id="contact_intro" name="contact_intro" maxlength="500" rows="2"><?= $cfg('contact_intro') ?></textarea></div>
                        <div class="col-12"><label class="form-label" for="footer_texte">Texte du footer</label><textarea class="form-control" id="footer_texte" name="footer_texte" maxlength="500" rows="2"><?= $cfg('footer_texte') ?></textarea></div>
                        <div class="col-lg-6"><label class="form-label" for="seo_home_titre">SEO accueil — titre</label><input class="form-control" id="seo_home_titre" name="seo_home_titre" maxlength="70" value="<?= $cfg('seo_home_titre') ?>"></div>
                        <div class="col-lg-6"><label class="form-label" for="seo_home_description">SEO accueil — description</label><input class="form-control" id="seo_home_description" name="seo_home_description" maxlength="180" value="<?= $cfg('seo_home_description') ?>"></div>
                        <div class="col-lg-6"><label class="form-label" for="seo_contact_titre">SEO contact — titre</label><input class="form-control" id="seo_contact_titre" name="seo_contact_titre" maxlength="70" value="<?= $cfg('seo_contact_titre') ?>"></div>
                        <div class="col-lg-6"><label class="form-label" for="seo_contact_description">SEO contact — description</label><input class="form-control" id="seo_contact_description" name="seo_contact_description" maxlength="180" value="<?= $cfg('seo_contact_description') ?>"></div>
                    </div>
                </div>
            </div>

            <!-- Images -->'''
if marker not in text:
    raise SystemExit('admin personalization marker missing')
view.write_text(text.replace(marker, fields, 1), encoding='utf-8')

Path('tests/Unit/WhiteLabel/EditorialConfigurationContractTest.php').write_text('''<?php

use App\\Config\\ConfigurationRegistry;
use PHPUnit\\Framework\\TestCase;

final class EditorialConfigurationContractTest extends TestCase
{
    public function testEditorialSurfaceIsCanonicalTenantConfiguration(): void
    {
        $keys = [
            'content.home.hero_subtitle', 'content.home.hero_paragraph', 'content.home.intro_title',
            'content.home.intro_body', 'content.home.cta_label', 'content.home.cta_url',
            'content.home.reviews_title', 'content.home.reviews_description', 'content.contact.title',
            'content.contact.intro', 'contact.response_sla_hours', 'content.footer.text',
            'seo.home.title', 'seo.home.description', 'seo.contact.title', 'seo.contact.description',
            'legal.terms_content', 'legal.notices_content',
        ];
        foreach ($keys as $key) {
            self::assertTrue(ConfigurationRegistry::has($key), $key);
        }
    }

    public function testRuntimeDoesNotInventHistoricalEditorialClaimsOrFallbackAssets(): void
    {
        $root = dirname(__DIR__, 3);
        $runtime = '';
        foreach (['src/Controllers', 'src/Views'] as $dir) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir));
            foreach ($it as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $runtime .= file_get_contents($file->getPathname()) ?: '';
                }
            }
        }
        foreach (['25 ans', 'sous 48h', 'hero-traiteur.webp', 'preparation-traiteur-generique.webp', 'Mon Traiteur'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $runtime, $forbidden);
        }
    }
}
''', encoding='utf-8')
