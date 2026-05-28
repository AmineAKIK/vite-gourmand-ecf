<?php
$pageTitle = buildPageTitle('Paramètres');
$cspNonce  = $GLOBALS['csp_nonce'] ?? '';

$cfg = function(string $cle, string $default = '') use ($config): string {
    return sanitize($config[$cle] ?? $default);
};

$regimeTva        = $config['regime_tva'] ?? 'assujetti';
$reductionSeuil   = (float)($config['reduction_seuil'] ?? 100);
$reductionTaux    = (float)($config['reduction_taux']  ?? 10);
$reductionExample = max(120, $reductionSeuil);
$reductionExAmt   = $reductionExample * $reductionTaux / 100;

$categorieLabels = ['menu' => 'Menu / prestation', 'livraison' => 'Livraison', 'general' => 'Général'];

// Onglet actif depuis l'ancre (hash transmis via query param si besoin) ou défaut
$activeTab = $_GET['tab'] ?? 'identite';
?>

<?php partial('partials/page_title_bar', ['icon' => 'bi-sliders', 'title' => 'Paramètres']); ?>

<!-- Navigation onglets -->
<ul class="nav nav-tabs mb-4 flex-nowrap overflow-auto params-tabs" id="paramsTabs" role="tablist" style="white-space:nowrap;">
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'identite' ? 'active' : '' ?>" id="tab-identite" data-bs-toggle="tab" data-bs-target="#pane-identite" type="button" role="tab">
            <i class="bi bi-palette me-1"></i>Identité
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'personnalisation' ? 'active' : '' ?>" id="tab-personnalisation" data-bs-toggle="tab" data-bs-target="#pane-personnalisation" type="button" role="tab">
            <i class="bi bi-brush me-1"></i>Personnalisation
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'horaires' ? 'active' : '' ?>" id="tab-horaires" data-bs-toggle="tab" data-bs-target="#pane-horaires" type="button" role="tab">
            <i class="bi bi-clock me-1"></i>Horaires
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'entreprise' ? 'active' : '' ?>" id="tab-entreprise" data-bs-toggle="tab" data-bs-target="#pane-entreprise" type="button" role="tab">
            <i class="bi bi-building me-1"></i>Entreprise
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'fiscal' ? 'active' : '' ?>" id="tab-fiscal" data-bs-toggle="tab" data-bs-target="#pane-fiscal" type="button" role="tab">
            <i class="bi bi-percent me-1"></i>Fiscal &amp; TVA
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'tarification' ? 'active' : '' ?>" id="tab-tarification" data-bs-toggle="tab" data-bs-target="#pane-tarification" type="button" role="tab">
            <i class="bi bi-truck me-1"></i>Tarification
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'paiement' ? 'active' : '' ?>" id="tab-paiement" data-bs-toggle="tab" data-bs-target="#pane-paiement" type="button" role="tab">
            <i class="bi bi-credit-card me-1"></i>Paiement
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'legal' ? 'active' : '' ?>" id="tab-legal" data-bs-toggle="tab" data-bs-target="#pane-legal" type="button" role="tab">
            <i class="bi bi-file-text me-1"></i>Pages légales
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'avance' ? 'active' : '' ?>" id="tab-avance" data-bs-toggle="tab" data-bs-target="#pane-avance" type="button" role="tab">
            <i class="bi bi-gear-wide-connected me-1"></i>Avancé
        </button>
    </li>
</ul>

<div class="tab-content params-tab-content">

    <!-- ================================================================
         ONGLET 1 — Identité & charte graphique
    ================================================================ -->
    <div class="tab-pane fade <?= $activeTab === 'identite' ? 'show active' : '' ?>" id="pane-identite" role="tabpanel">
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST" action="/admin/parametres/modifier">
                    <?= csrfField() ?>
                    <input type="hidden" name="_section" value="identite">

                    <h6 class="fw-semibold mb-3">Informations du site</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-lg-4">
                            <label class="form-label fw-medium" for="site_nom">Nom du traiteur <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="site_nom" name="site_nom"
                                   value="<?= $cfg('site_nom', 'Mon Traiteur') ?>" maxlength="100" required>
                            <div class="form-text">Affiché dans la navbar, les emails, les factures.</div>
                        </div>
                        <div class="col-12 col-lg-4">
                            <label class="form-label fw-medium" for="site_slogan">Slogan / accroche</label>
                            <input type="text" class="form-control" id="site_slogan" name="site_slogan"
                                   value="<?= $cfg('site_slogan') ?>" maxlength="100"
                                   placeholder="Ex : Traiteur lyonnais depuis 1998">
                        </div>
                        <div class="col-12 col-lg-4">
                            <label class="form-label fw-medium" for="site_domaine">Nom de domaine</label>
                            <input type="text" class="form-control" id="site_domaine" name="site_domaine"
                                   value="<?= $cfg('site_domaine') ?>" maxlength="100"
                                   placeholder="montraiteur.fr">
                            <div class="form-text">Affiché dans les mentions légales et CGV.</div>
                        </div>
                        <div class="col-12 col-lg-4">
                            <label class="form-label fw-medium" for="site_email">Email de contact public</label>
                            <input type="email" class="form-control" id="site_email" name="site_email"
                                   value="<?= $cfg('site_email') ?>" maxlength="150">
                        </div>
                        <div class="col-12 col-lg-4">
                            <label class="form-label fw-medium" for="site_telephone">Téléphone public</label>
                            <input type="tel" class="form-control" id="site_telephone" name="site_telephone"
                                   value="<?= $cfg('site_telephone') ?>" maxlength="30">
                        </div>
                    </div>

                    <h6 class="fw-semibold mb-3">Adresse du traiteur</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-lg-6">
                            <label class="form-label fw-medium" for="site_adresse">Adresse (rue)</label>
                            <input type="text" class="form-control" id="site_adresse" name="site_adresse"
                                   value="<?= $cfg('site_adresse') ?>" maxlength="150">
                        </div>
                        <div class="col-6 col-lg-2">
                            <label class="form-label fw-medium" for="site_code_postal">Code postal</label>
                            <input type="text" class="form-control" id="site_code_postal" name="site_code_postal"
                                   value="<?= $cfg('site_code_postal') ?>" maxlength="5" inputmode="numeric">
                        </div>
                        <div class="col-6 col-lg-4">
                            <label class="form-label fw-medium" for="site_ville">Ville</label>
                            <input type="text" class="form-control" id="site_ville" name="site_ville"
                                   value="<?= $cfg('site_ville') ?>" maxlength="80">
                        </div>
                    </div>

                    <h6 class="fw-semibold mb-3">Charte graphique</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-6 col-lg-2">
                            <label class="form-label fw-medium" for="couleur_principale">Couleur principale</label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color" id="couleur_principale_picker"
                                       value="<?= $cfg('couleur_principale', '#8B1A2B') ?>"
                                       oninput="document.getElementById('couleur_principale').value=this.value">
                                <input type="text" class="form-control" id="couleur_principale" name="couleur_principale"
                                       value="<?= $cfg('couleur_principale', '#8B1A2B') ?>" maxlength="7"
                                       pattern="^#[0-9A-Fa-f]{6}$"
                                       oninput="if(/^#[0-9A-Fa-f]{6}$/.test(this.value))document.getElementById('couleur_principale_picker').value=this.value">
                            </div>
                        </div>
                        <div class="col-6 col-lg-2">
                            <label class="form-label fw-medium" for="couleur_secondaire">Couleur secondaire</label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color" id="couleur_secondaire_picker"
                                       value="<?= $cfg('couleur_secondaire', '#D4A843') ?>"
                                       oninput="document.getElementById('couleur_secondaire').value=this.value">
                                <input type="text" class="form-control" id="couleur_secondaire" name="couleur_secondaire"
                                       value="<?= $cfg('couleur_secondaire', '#D4A843') ?>" maxlength="7"
                                       pattern="^#[0-9A-Fa-f]{6}$"
                                       oninput="if(/^#[0-9A-Fa-f]{6}$/.test(this.value))document.getElementById('couleur_secondaire_picker').value=this.value">
                            </div>
                        </div>
                        <div class="col-6 col-lg-2">
                            <label class="form-label fw-medium" for="couleur_fond">Couleur de fond</label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color" id="couleur_fond_picker"
                                       value="<?= $cfg('couleur_fond', '#FDF6EC') ?>"
                                       oninput="document.getElementById('couleur_fond').value=this.value">
                                <input type="text" class="form-control" id="couleur_fond" name="couleur_fond"
                                       value="<?= $cfg('couleur_fond', '#FDF6EC') ?>" maxlength="7"
                                       pattern="^#[0-9A-Fa-f]{6}$"
                                       oninput="if(/^#[0-9A-Fa-f]{6}$/.test(this.value))document.getElementById('couleur_fond_picker').value=this.value">
                            </div>
                        </div>
                        <div class="col-12 col-lg-6 align-self-end">
                            <div class="p-3 rounded d-flex gap-3 align-items-center" style="background:var(--vg-creme)">
                                <span class="fw-semibold" style="color:var(--vg-bordeaux)">Aperçu →</span>
                                <span class="badge" id="preview-badge" style="background:var(--vg-bordeaux);color:#fff">Principale</span>
                                <span class="badge" id="preview-badge2" style="background:var(--vg-or);color:#fff">Secondaire</span>
                            </div>
                        </div>
                    </div>

                    <h6 class="fw-semibold mb-3">Coordonnées GPS du dépôt (livraison)</h6>
                    <?php
                        $latVal = $config['livraison_lat'] ?? '';
                        $lngVal = $config['livraison_lng'] ?? '';
                        $geoOk  = ($latVal !== '' && $latVal !== '0' && $lngVal !== '' && $lngVal !== '0');
                    ?>
                    <?php if (!$geoOk): ?>
                    <div class="alert alert-warning d-flex align-items-start gap-2 mb-3" role="alert">
                        <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
                        <div>
                            <strong>Livraison non configurée.</strong>
                            Renseignez la latitude et la longitude de votre dépôt pour activer le calcul de livraison.
                            Trouvez vos coordonnées sur <a href="https://www.google.com/maps" target="_blank" rel="noopener">Google Maps</a>
                            (clic droit → « C'est ici »).
                        </div>
                    </div>
                    <?php endif ?>
                    <div class="row g-3 mb-3">
                        <div class="col-6 col-lg-2">
                            <label class="form-label fw-medium" for="livraison_lat">Latitude</label>
                            <input type="number" step="0.0001" class="form-control" id="livraison_lat" name="livraison_lat"
                                   value="<?= htmlspecialchars($latVal) ?>" min="-90" max="90"
                                   placeholder="ex: 44.8378">
                        </div>
                        <div class="col-6 col-lg-2">
                            <label class="form-label fw-medium" for="livraison_lng">Longitude</label>
                            <input type="number" step="0.0001" class="form-control" id="livraison_lng" name="livraison_lng"
                                   value="<?= htmlspecialchars($lngVal) ?>" min="-180" max="180"
                                   placeholder="ex: -0.5792">
                        </div>
                        <div class="col-6 col-lg-2">
                            <label class="form-label fw-medium" for="livraison_rayon_max_km">Rayon max (km)</label>
                            <input type="number" class="form-control" id="livraison_rayon_max_km" name="livraison_rayon_max_km"
                                   value="<?= (int)($config['livraison_rayon_max_km'] ?? 50) ?>" min="1" max="500">
                            <div class="form-text">Au-delà, commande refusée.</div>
                        </div>
                        <div class="col-6 col-lg-2">
                            <label class="form-label fw-medium" for="commandes_max_par_jour">Commandes max / jour</label>
                            <input type="number" class="form-control" id="commandes_max_par_jour" name="commandes_max_par_jour"
                                   value="<?= (int)($config['commandes_max_par_jour'] ?? 0) ?>" min="0" max="999">
                            <div class="form-text">0 = illimité.</div>
                        </div>
                        <div class="col-12 col-lg-6">
                            <label class="form-label fw-medium" for="livraison_codes_postaux_gratuits">Codes postaux livraison gratuite</label>
                            <input type="text" class="form-control" id="livraison_codes_postaux_gratuits"
                                   name="livraison_codes_postaux_gratuits"
                                   value="<?= htmlspecialchars($config['livraison_codes_postaux_gratuits'] ?? '') ?>"
                                   maxlength="500" placeholder="33000,33100,33200">
                            <div class="form-text">Séparés par des virgules.</div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-vg">
                        <i class="bi bi-save me-1"></i>Enregistrer l'identité
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ================================================================
         ONGLET 2 — Personnalisation (images + textes hero)
    ================================================================ -->
    <div class="tab-pane fade <?= $activeTab === 'personnalisation' ? 'show active' : '' ?>" id="pane-personnalisation" role="tabpanel">
        <form method="POST" action="/admin/accueil/modifier" enctype="multipart/form-data" novalidate>
            <?= csrfField() ?>

            <!-- Textes hero -->
            <div class="card shadow-sm mb-4">
                <div class="card-header fw-semibold">
                    <i class="bi bi-type me-2 text-vg"></i>Textes de la page d'accueil
                </div>
                <div class="card-body d-flex flex-column gap-4">
                    <div>
                        <label class="form-label fw-medium" for="hero_sous_titre">
                            Sous-titre <span class="text-danger">*</span>
                            <small class="text-muted fw-normal ms-1">— affiché en doré</small>
                        </label>
                        <textarea id="hero_sous_titre" name="hero_sous_titre" class="form-control"
                                  rows="2" maxlength="60" required><?= $cfg('hero_sous_titre') ?></textarea>
                        <div class="d-flex justify-content-between">
                            <div class="form-text">Accroche courte, mise en valeur en couleur dorée.</div>
                            <small class="text-muted mt-1"><span id="count-sous-titre">0</span>/60</small>
                        </div>
                    </div>
                    <div>
                        <label class="form-label fw-medium" for="hero_paragraphe">
                            Paragraphe d'introduction
                            <small class="text-muted fw-normal ms-1">— affiché en blanc</small>
                        </label>
                        <textarea id="hero_paragraphe" name="hero_paragraphe" class="form-control"
                                  rows="3" maxlength="200"><?= $cfg('hero_paragraphe') ?></textarea>
                        <div class="d-flex justify-content-between">
                            <div class="form-text">Appuie sur Entrée pour créer un saut de ligne.</div>
                            <small class="text-muted mt-1"><span id="count-paragraphe">0</span>/200</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Images -->
            <div class="card shadow-sm mb-4">
                <div class="card-header fw-semibold">
                    <i class="bi bi-images me-2 text-vg"></i>Images du site
                </div>
                <div class="card-body">
                    <div class="row g-4">

                        <!-- Logo navbar -->
                        <div class="col-12 col-lg-4">
                            <p class="fw-medium mb-2">Logo navbar</p>
                            <?php $logoUrl = $images['logo'] ?? null; ?>
                            <?php if ($logoUrl): ?>
                                <img src="<?= sanitize($logoUrl) ?>" alt="Logo actuel"
                                     class="img-fluid rounded mb-2" style="max-height:60px;object-fit:contain;display:block;">
                            <?php else: ?>
                                <p class="text-muted small mb-2">Aucun logo — le nom du site s'affiche.</p>
                            <?php endif; ?>
                            <input type="file" class="form-control image-picker" name="logo" id="logo-input"
                                   accept="<?= sanitize(\App\Services\MenuAdminService::acceptedImageMimeTypes()) ?>">
                            <div class="form-text"><?= sanitize(\App\Services\MenuAdminService::acceptedImageFormatsLabel()) ?> — PNG transparent recommandé, min. 300×100 px</div>
                        </div>

                        <!-- Favicon -->
                        <div class="col-12 col-lg-4">
                            <p class="fw-medium mb-2">Favicon (onglet navigateur)</p>
                            <?php $faviconUrl = $images['favicon'] ?? null; ?>
                            <?php if ($faviconUrl): ?>
                                <img src="<?= sanitize($faviconUrl) ?>" alt="Favicon actuel"
                                     class="rounded mb-2" style="width:32px;height:32px;object-fit:contain;display:block;">
                            <?php else: ?>
                                <p class="text-muted small mb-2">Aucun favicon — favicon par défaut utilisé.</p>
                            <?php endif; ?>
                            <input type="file" class="form-control" name="favicon"
                                   accept="<?= sanitize(\App\Services\MenuAdminService::acceptedImageMimeTypes()) ?>">
                            <div class="form-text">PNG ou ICO — carré, 32×32 ou 64×64 px</div>
                        </div>

                        <!-- OG image -->
                        <div class="col-12 col-lg-4">
                            <p class="fw-medium mb-2">Image de partage (og:image)</p>
                            <?php $ogUrl = $images['og_image'] ?? null; ?>
                            <?php if ($ogUrl): ?>
                                <img src="<?= sanitize($ogUrl) ?>" alt="og:image actuelle"
                                     class="img-fluid rounded mb-2" style="max-height:80px;width:100%;object-fit:cover;" id="preview-og_image">
                            <?php else: ?>
                                <p class="text-muted small mb-2">Aucune — image par défaut utilisée.</p>
                            <?php endif; ?>
                            <input type="file" class="form-control image-picker" name="og_image"
                                   accept="<?= sanitize(\App\Services\MenuAdminService::acceptedImageMimeTypes()) ?>"
                                   data-preview="preview-og_image">
                            <div class="form-text"><?= sanitize(\App\Services\MenuAdminService::acceptedImageFormatsLabel()) ?> — 1200×630 px recommandé</div>
                        </div>

                        <!-- Image hero -->
                        <div class="col-12 col-lg-6">
                            <p class="fw-medium mb-2">Image de fond (bannière)</p>
                            <img src="<?= sanitize(imageUrl($images['hero'] ?? null, 'images/hero-traiteur.webp')) ?>"
                                 alt="Image hero actuelle" class="img-fluid rounded mb-2"
                                 style="max-height:160px;width:100%;object-fit:cover;" id="preview-hero">
                            <input type="file" class="form-control image-picker" name="hero"
                                   accept="<?= sanitize(\App\Services\MenuAdminService::acceptedImageMimeTypes()) ?>"
                                   data-preview="preview-hero">
                            <div class="form-text"><?= sanitize(\App\Services\MenuAdminService::acceptedImageFormatsLabel()) ?> — 1920×600 px recommandé</div>
                        </div>

                        <!-- Image équipe -->
                        <div class="col-12 col-lg-6">
                            <p class="fw-medium mb-2">Image section "Notre équipe"</p>
                            <img src="<?= sanitize(imageUrl($images['preparation'] ?? null, 'images/preparation-traiteur-generique.webp')) ?>"
                                 alt="Image équipe actuelle" class="img-fluid rounded mb-2"
                                 style="max-height:160px;width:100%;object-fit:cover;" id="preview-preparation">
                            <input type="file" class="form-control image-picker" name="preparation"
                                   accept="<?= sanitize(\App\Services\MenuAdminService::acceptedImageMimeTypes()) ?>"
                                   data-preview="preview-preparation">
                            <div class="form-text"><?= sanitize(\App\Services\MenuAdminService::acceptedImageFormatsLabel()) ?> — 1000×700 px recommandé</div>
                        </div>

                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-vg">
                <i class="bi bi-save me-1"></i>Enregistrer la personnalisation
            </button>
        </form>
    </div>

    <!-- ================================================================
         ONGLET 3 — Horaires
    ================================================================ -->
    <div class="tab-pane fade <?= $activeTab === 'horaires' ? 'show active' : '' ?>" id="pane-horaires" role="tabpanel">
        <div class="card shadow-sm" style="max-width:700px;">
            <form method="POST" action="/employe/horaires/modifier" novalidate>
                <?= csrfField() ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0" aria-label="Horaires d'ouverture">
                        <thead>
                            <tr style="background:rgba(0,0,0,.03); border-bottom:1px solid rgba(0,0,0,.08);">
                                <th scope="col" class="ps-3 text-vg fw-semibold">Jour</th>
                                <th scope="col" class="text-vg fw-semibold">Ouverture</th>
                                <th scope="col" class="text-vg fw-semibold pe-3">Fermeture</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($horaires as $h): ?>
                            <tr>
                                <td class="fw-semibold ps-3"><?= sanitize($h['jour']) ?></td>
                                <td>
                                    <input type="text" class="form-control form-control-sm"
                                           name="horaires[<?= (int)$h['horaire_id'] ?>][ouverture]"
                                           value="<?= sanitize($h['heure_ouverture'] ?? '') ?>"
                                           placeholder="Fermé"
                                           aria-label="Heure d'ouverture - <?= sanitize($h['jour']) ?>">
                                </td>
                                <td class="pe-3">
                                    <input type="text" class="form-control form-control-sm"
                                           name="horaires[<?= (int)$h['horaire_id'] ?>][fermeture]"
                                           value="<?= sanitize($h['heure_fermeture'] ?? '') ?>"
                                           placeholder="18:00"
                                           aria-label="Heure de fermeture - <?= sanitize($h['jour']) ?>">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex gap-2 p-3 border-top">
                    <button type="submit" class="btn btn-vg">
                        <i class="bi bi-save me-1"></i>Enregistrer les horaires
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================================================================
         ONGLET 4 — Informations entreprise
    ================================================================ -->
    <div class="tab-pane fade <?= $activeTab === 'entreprise' ? 'show active' : '' ?>" id="pane-entreprise" role="tabpanel">
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST" action="/admin/parametres/modifier">
                    <?= csrfField() ?>
                    <input type="hidden" name="_section" value="entreprise">

                    <div class="row g-3">
                        <div class="col-12 col-lg-6">
                            <label class="form-label fw-medium" for="entreprise_nom">Nom commercial</label>
                            <input type="text" class="form-control" id="entreprise_nom" name="entreprise_nom"
                                   value="<?= $cfg('entreprise_nom', siteName()) ?>" maxlength="100">
                        </div>
                        <div class="col-12 col-lg-3">
                            <label class="form-label fw-medium" for="entreprise_siret">SIRET <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="entreprise_siret" name="entreprise_siret"
                                   value="<?= $cfg('entreprise_siret') ?>" maxlength="14" inputmode="numeric" placeholder="14 chiffres">
                            <div class="form-text">Obligatoire pour finaliser des factures.</div>
                        </div>
                        <div class="col-12 col-lg-3">
                            <label class="form-label fw-medium" for="entreprise_forme_juridique">Forme juridique</label>
                            <input type="text" class="form-control" id="entreprise_forme_juridique" name="entreprise_forme_juridique"
                                   value="<?= $cfg('entreprise_forme_juridique') ?>" maxlength="60" placeholder="EI, EURL, SARL…">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-medium" for="entreprise_adresse">Adresse</label>
                            <input type="text" class="form-control" id="entreprise_adresse" name="entreprise_adresse"
                                   value="<?= $cfg('entreprise_adresse') ?>" maxlength="150">
                        </div>
                        <div class="col-12 col-lg-3">
                            <label class="form-label fw-medium" for="entreprise_code_postal">Code postal</label>
                            <input type="text" class="form-control" id="entreprise_code_postal" name="entreprise_code_postal"
                                   value="<?= $cfg('entreprise_code_postal') ?>" maxlength="5" inputmode="numeric">
                        </div>
                        <div class="col-12 col-lg-4">
                            <label class="form-label fw-medium" for="entreprise_ville">Ville</label>
                            <input type="text" class="form-control" id="entreprise_ville" name="entreprise_ville"
                                   value="<?= $cfg('entreprise_ville') ?>" maxlength="80">
                        </div>
                        <div class="col-12 col-lg-5">
                            <label class="form-label fw-medium" for="entreprise_telephone">Téléphone</label>
                            <input type="tel" class="form-control" id="entreprise_telephone" name="entreprise_telephone"
                                   value="<?= $cfg('entreprise_telephone') ?>" maxlength="20">
                        </div>

                        <div class="col-12 col-lg-6">
                            <label class="form-label fw-medium" for="entreprise_email">Email de contact</label>
                            <input type="email" class="form-control" id="entreprise_email" name="entreprise_email"
                                   value="<?= $cfg('entreprise_email') ?>" maxlength="150">
                        </div>
                        <div class="col-12 col-lg-6">
                            <label class="form-label fw-medium" for="entreprise_tva_intracom">N° TVA intracommunautaire</label>
                            <input type="text" class="form-control" id="entreprise_tva_intracom" name="entreprise_tva_intracom"
                                   value="<?= $cfg('entreprise_tva_intracom') ?>" maxlength="20" placeholder="FR12345678901">
                            <div class="form-text">Laissez vide si non assujetti.</div>
                        </div>

                        <div class="col-12"><hr class="my-1"><small class="text-muted fw-semibold">Coordonnées bancaires</small></div>
                        <div class="col-12 col-lg-5">
                            <label class="form-label fw-medium" for="banque_iban">IBAN</label>
                            <input type="text" class="form-control" id="banque_iban" name="banque_iban"
                                   value="<?= $cfg('banque_iban') ?>" maxlength="34" placeholder="FR76 1234 …">
                        </div>
                        <div class="col-12 col-lg-3">
                            <label class="form-label fw-medium" for="banque_bic">BIC / SWIFT</label>
                            <input type="text" class="form-control" id="banque_bic" name="banque_bic"
                                   value="<?= $cfg('banque_bic') ?>" maxlength="11" placeholder="BNPAFRPPXXX">
                        </div>
                        <div class="col-12 col-lg-4">
                            <label class="form-label fw-medium" for="banque_nom_banque">Nom de la banque</label>
                            <input type="text" class="form-control" id="banque_nom_banque" name="banque_nom_banque"
                                   value="<?= $cfg('banque_nom_banque') ?>" maxlength="80">
                        </div>
                    </div>

                    <div class="mt-3">
                        <button type="submit" class="btn btn-vg">
                            <i class="bi bi-save me-1"></i>Enregistrer entreprise
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ================================================================
         ONGLET 5 — Régime fiscal & TVA
    ================================================================ -->
    <div class="tab-pane fade <?= $activeTab === 'fiscal' ? 'show active' : '' ?>" id="pane-fiscal" role="tabpanel">
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="POST" action="/admin/parametres/modifier" class="mb-4">
                    <?= csrfField() ?>
                    <input type="hidden" name="_section" value="fiscal">

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-lg-4">
                            <label class="form-label fw-medium" for="regime_tva">Régime TVA</label>
                            <select class="form-select" id="regime_tva" name="regime_tva">
                                <option value="assujetti"     <?= $regimeTva === 'assujetti'     ? 'selected' : '' ?>>Assujetti à la TVA</option>
                                <option value="non_assujetti" <?= $regimeTva === 'non_assujetti' ? 'selected' : '' ?>>Non assujetti (art. 293 B CGI)</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-lg-4">
                            <label class="form-label fw-medium" for="mention_facture">Mention pied de facture</label>
                            <textarea class="form-control form-control-sm" id="mention_facture" name="mention_facture"
                                      rows="3" maxlength="500"><?= $cfg('mention_facture') ?></textarea>
                        </div>
                        <div class="col-12 col-lg-4">
                            <label class="form-label fw-medium" for="mention_ticket">Mention pied de ticket</label>
                            <textarea class="form-control form-control-sm" id="mention_ticket" name="mention_ticket"
                                      rows="3" maxlength="500"><?= $cfg('mention_ticket') ?></textarea>
                        </div>
                        <div class="col-12 col-lg-4">
                            <label class="form-label fw-medium" for="mention_acompte">Mention pied d'acompte</label>
                            <textarea class="form-control form-control-sm" id="mention_acompte" name="mention_acompte"
                                      rows="3" maxlength="500"><?= $cfg('mention_acompte') ?></textarea>
                        </div>
                    </div>

                    <div class="mt-3">
                        <button type="submit" class="btn btn-vg">
                            <i class="bi bi-save me-1"></i>Enregistrer régime fiscal
                        </button>
                    </div>
                </form>

                <hr>
                <h6 class="fw-semibold mb-3">Taux de TVA</h6>

                <div class="table-responsive mb-3">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Libellé</th>
                                <th class="text-end">Taux</th>
                                <th>Catégorie</th>
                                <th class="text-center">Par défaut</th>
                                <th class="text-center">Actif</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tousLesToux as $t): ?>
                            <tr class="<?= !$t['actif'] ? 'text-muted' : '' ?>">
                                <td>
                                    <?= sanitize($t['libelle']) ?>
                                    <?php if ($t['note']): ?>
                                    <small class="text-muted d-block" style="font-size:.75rem"><?= sanitize($t['note']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-semibold text-nowrap"><?= number_format((float)$t['taux'], 2) ?> %</td>
                                <td><span class="badge bg-secondary"><?= sanitize($categorieLabels[$t['categorie']] ?? $t['categorie']) ?></span></td>
                                <td class="text-center">
                                    <?php if ($t['par_defaut']): ?>
                                        <i class="bi bi-check-circle-fill text-success"></i>
                                    <?php else: ?>
                                        <form method="POST" action="/admin/taux-tva/defaut" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="taux_id"   value="<?= (int)$t['taux_id'] ?>">
                                            <input type="hidden" name="categorie" value="<?= sanitize($t['categorie']) ?>">
                                            <button type="submit" class="btn btn-link btn-sm p-0 text-muted" title="Définir par défaut">
                                                <i class="bi bi-circle"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <form method="POST" action="/admin/taux-tva/toggle" class="d-inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="taux_id" value="<?= (int)$t['taux_id'] ?>">
                                        <input type="hidden" name="actif"   value="<?= $t['actif'] ? '0' : '1' ?>">
                                        <button type="submit" class="btn btn-link btn-sm p-0"
                                                title="<?= $t['actif'] ? 'Désactiver' : 'Activer' ?>"
                                                <?= $t['par_defaut'] ? 'disabled' : '' ?>>
                                            <i class="bi <?= $t['actif'] ? 'bi-toggle-on text-success' : 'bi-toggle-off text-muted' ?>"></i>
                                        </button>
                                    </form>
                                </td>
                                <td class="text-end text-nowrap small text-muted">id <?= (int)$t['taux_id'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <details>
                    <summary class="btn btn-outline-secondary btn-sm mb-3">
                        <i class="bi bi-plus-circle me-1"></i>Ajouter un taux de TVA
                    </summary>
                    <form method="POST" action="/admin/taux-tva/creer" class="p-3 border rounded mt-2">
                        <?= csrfField() ?>
                        <div class="row g-3">
                            <div class="col-12 col-lg-5">
                                <label class="form-label form-label-sm">Libellé <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" name="libelle"
                                       placeholder="Ex. Taux spécial 20%" required maxlength="80">
                            </div>
                            <div class="col-6 col-lg-2">
                                <label class="form-label form-label-sm">Taux (%) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0" max="100" class="form-control form-control-sm"
                                       name="taux" required placeholder="10.00">
                            </div>
                            <div class="col-6 col-lg-2">
                                <label class="form-label form-label-sm">Catégorie</label>
                                <select class="form-select form-select-sm" name="categorie">
                                    <?php foreach ($categorieLabels as $val => $lbl): ?>
                                    <option value="<?= $val ?>"><?= $lbl ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-lg-3">
                                <label class="form-label form-label-sm">Note / référence légale</label>
                                <input type="text" class="form-control form-control-sm" name="note"
                                       placeholder="Art. 278bis CGI…" maxlength="255">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-vg btn-sm">
                                    <i class="bi bi-plus me-1"></i>Créer le taux
                                </button>
                            </div>
                        </div>
                    </form>
                </details>
            </div>
        </div>
    </div>

    <!-- ================================================================
         ONGLET 6 — Tarification
    ================================================================ -->
    <div class="tab-pane fade <?= $activeTab === 'tarification' ? 'show active' : '' ?>" id="pane-tarification" role="tabpanel">
        <div class="card shadow-sm" style="max-width:700px;">
            <div class="card-body">
                <form method="POST" action="/admin/parametres/modifier">
                    <?= csrfField() ?>
                    <input type="hidden" name="_section" value="tarification">
                    <input type="hidden" name="hero_sous_titre" value="<?= $cfg('hero_sous_titre') ?>">
                    <input type="hidden" name="hero_paragraphe" value="<?= $cfg('hero_paragraphe') ?>">

                    <h6 class="fw-semibold mb-3">Frais de livraison</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-6">
                            <label class="form-label fw-medium" for="livraison_base">Frais fixes (€)</label>
                            <div class="input-group">
                                <input type="number" id="livraison_base" name="livraison_base"
                                       class="form-control" min="0" step="0.01"
                                       value="<?= $cfg('livraison_base', '5.00') ?>" required>
                                <span class="input-group-text">€</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-medium" for="livraison_km">Tarif au km (€/km)</label>
                            <div class="input-group">
                                <input type="number" id="livraison_km" name="livraison_km"
                                       class="form-control" min="0" step="0.01"
                                       value="<?= $cfg('livraison_km', '0.59') ?>" required>
                                <span class="input-group-text">€/km</span>
                            </div>
                        </div>
                    </div>

                    <h6 class="fw-semibold mb-3">Réduction automatique</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-medium" for="reduction_seuil">Seuil (€)</label>
                            <div class="input-group">
                                <input type="number" id="reduction_seuil" name="reduction_seuil"
                                       class="form-control" min="0" step="0.01"
                                       value="<?= $cfg('reduction_seuil', '100.00') ?>" required>
                                <span class="input-group-text">€</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-medium" for="reduction_taux">Taux (%)</label>
                            <div class="input-group">
                                <input type="number" id="reduction_taux" name="reduction_taux"
                                       class="form-control" min="0" max="100" step="1"
                                       value="<?= $cfg('reduction_taux', '10') ?>" required>
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 rounded mb-3" style="background:var(--vg-creme)">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1 text-vg"></i>
                            Exemple : seuil <strong id="prev-seuil"><?= $cfg('reduction_seuil', '100.00') ?> €</strong>,
                            taux <strong id="prev-taux"><?= $cfg('reduction_taux', '10') ?>%</strong>
                            → réduction de <strong id="prev-montant"><?= number_format($reductionExAmt, 2) ?> €</strong>
                            sur une commande de <strong id="prev-commande"><?= number_format($reductionExample, 2) ?> €</strong>.
                        </small>
                    </div>

                    <button type="submit" class="btn btn-vg">
                        <i class="bi bi-save me-1"></i>Enregistrer tarification
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ================================================================
         ONGLET 7 — Conditions de paiement
    ================================================================ -->
    <div class="tab-pane fade <?= $activeTab === 'paiement' ? 'show active' : '' ?>" id="pane-paiement" role="tabpanel">
        <div class="card shadow-sm" style="max-width:700px;">
            <div class="card-body">
                <form method="POST" action="/admin/parametres/modifier">
                    <?= csrfField() ?>
                    <input type="hidden" name="_section" value="paiement">

                    <div class="row g-3">
                        <div class="col-12 col-lg-6">
                            <label class="form-label fw-medium" for="acompte_taux_defaut">Acompte par défaut (%)</label>
                            <div class="input-group">
                                <input type="number" id="acompte_taux_defaut" name="acompte_taux_defaut"
                                       class="form-control" min="0" max="100" step="1"
                                       value="<?= $cfg('acompte_taux_defaut', '30') ?>">
                                <span class="input-group-text">%</span>
                            </div>
                            <div class="form-text">Taux pré-rempli lors de la création d'une facture d'acompte.</div>
                        </div>
                        <div class="col-12 col-lg-6">
                            <label class="form-label fw-medium" for="delai_paiement_jours">Délai de paiement (jours)</label>
                            <div class="input-group">
                                <input type="number" id="delai_paiement_jours" name="delai_paiement_jours"
                                       class="form-control" min="0" max="365" step="1"
                                       value="<?= $cfg('delai_paiement_jours', '30') ?>">
                                <span class="input-group-text">jours</span>
                            </div>
                        </div>
                        <div class="col-12 col-lg-6">
                            <label class="form-label fw-medium" for="penalites_retard_taux">Pénalités de retard (%/an)</label>
                            <div class="input-group">
                                <input type="number" id="penalites_retard_taux" name="penalites_retard_taux"
                                       class="form-control" min="0" step="0.01"
                                       value="<?= $cfg('penalites_retard_taux', '12.00') ?>">
                                <span class="input-group-text">%</span>
                            </div>
                            <div class="form-text">Légalement obligatoire sur les factures B2B.</div>
                        </div>
                        <div class="col-12 col-lg-6">
                            <label class="form-label fw-medium" for="indemnite_recouvrement">Indemnité forfaitaire (€)</label>
                            <div class="input-group">
                                <input type="number" id="indemnite_recouvrement" name="indemnite_recouvrement"
                                       class="form-control" min="0" step="0.01"
                                       value="<?= $cfg('indemnite_recouvrement', '40.00') ?>">
                                <span class="input-group-text">€</span>
                            </div>
                            <div class="form-text">Forfait légal de recouvrement en cas de retard.</div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <button type="submit" class="btn btn-vg">
                            <i class="bi bi-save me-1"></i>Enregistrer conditions
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ================================================================
         ONGLET 8 — Pages légales
    ================================================================ -->
    <div class="tab-pane fade <?= $activeTab === 'legal' ? 'show active' : '' ?>" id="pane-legal" role="tabpanel">
        <div class="card shadow-sm">
            <div class="card-body">
                <p class="text-muted mb-4">
                    Si ces champs sont remplis, votre contenu personnalisé remplace le texte généré automatiquement sur
                    <a href="/cgv" target="_blank">/cgv</a> et <a href="/mentions-legales" target="_blank">/mentions-legales</a>.
                    Laissez vide pour conserver le texte généré depuis vos paramètres entreprise.
                </p>
                <form method="POST" action="/admin/parametres/modifier">
                    <?= csrfField() ?>
                    <input type="hidden" name="_section" value="legal">
                    <div class="row g-4">
                        <div class="col-12 col-lg-6">
                            <label class="form-label fw-medium" for="cgv_contenu">Conditions Générales de Vente</label>
                            <textarea id="cgv_contenu" name="cgv_contenu" class="form-control"
                                      rows="14" maxlength="20000"
                                      placeholder="Laissez vide pour le texte généré automatiquement."><?= $cfg('cgv_contenu') ?></textarea>
                            <div class="d-flex justify-content-between mt-1">
                                <div class="form-text">Les sauts de ligne sont conservés.</div>
                                <small class="text-muted"><span id="count-cgv">0</span>/20 000</small>
                            </div>
                        </div>
                        <div class="col-12 col-lg-6">
                            <label class="form-label fw-medium" for="mentions_contenu">Mentions légales</label>
                            <textarea id="mentions_contenu" name="mentions_contenu" class="form-control"
                                      rows="14" maxlength="20000"
                                      placeholder="Laissez vide pour le texte généré automatiquement."><?= $cfg('mentions_contenu') ?></textarea>
                            <div class="d-flex justify-content-between mt-1">
                                <div class="form-text">Les sauts de ligne sont conservés.</div>
                                <small class="text-muted"><span id="count-mentions">0</span>/20 000</small>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-vg">
                            <i class="bi bi-save me-1"></i>Enregistrer les pages légales
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="tab-pane fade <?= $activeTab === 'avance' ? 'show active' : '' ?>" id="pane-avance" role="tabpanel">
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h5 mb-3">Rappels automatiques (Cron)</h2>
                <p class="text-muted small mb-3">
                    Configurez un cron job Railway pointant sur
                    <code><?= htmlspecialchars(BASE_URL) ?>/cron/rappels?token=VOTRE_TOKEN</code>
                    avec la commande <code>curl -s "URL"</code>, planifié chaque matin à 07h00.
                </p>
                <form method="POST" action="/admin/parametres/modifier">
                    <?= csrfField() ?>
                    <input type="hidden" name="_section" value="avance">
                    <div class="row g-3">
                        <div class="col-12 col-lg-4">
                            <label class="form-label fw-medium" for="devis_template">Template devis</label>
                            <select class="form-select" id="devis_template" name="devis_template">
                                <option value="sobre" <?= ($config['devis_template'] ?? 'sobre') === 'sobre' ? 'selected' : '' ?>>Sobre (comptable)</option>
                                <option value="premium" <?= ($config['devis_template'] ?? '') === 'premium' ? 'selected' : '' ?>>Premium (commercial, 2 colonnes)</option>
                            </select>
                            <div class="form-text">Le template premium affiche un en-tête coloré aux couleurs de la charte.</div>
                        </div>
                        <div class="col-12 col-lg-8">
                            <label class="form-label fw-medium" for="cron_secret_token">Token secret cron</label>
                            <div class="input-group">
                                <input type="text" class="form-control font-monospace" id="cron_secret_token"
                                       name="cron_secret_token"
                                       value="<?= htmlspecialchars($config['cron_secret_token'] ?? '') ?>"
                                       maxlength="128" placeholder="Laisser vide pour désactiver">
                                <button type="button" class="btn btn-outline-secondary" id="btn-gen-token"
                                        title="Générer un token aléatoire">
                                    <i class="bi bi-arrow-repeat"></i>
                                </button>
                            </div>
                            <div class="form-text">Chaîne aléatoire longue — utilisée comme clé secrète dans l'URL cron.</div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-vg mt-3">
                        <i class="bi bi-save me-1"></i>Enregistrer
                    </button>
                </form>
            </div>
        </div>
    </div>

</div><!-- /.tab-content -->
<script nonce="<?= cspNonce() ?>">
(function () {
    var btn = document.getElementById('btn-gen-token');
    if (btn) {
        btn.addEventListener('click', function () {
            var arr = new Uint8Array(32);
            crypto.getRandomValues(arr);
            document.getElementById('cron_secret_token').value =
                Array.from(arr).map(b => b.toString(16).padStart(2, '0')).join('');
        });
    }
}());
</script>

<script nonce="<?= $cspNonce ?>">
(function () {
    // Persiste l'onglet actif dans l'URL (hash → query param pour le PHP côté serveur)
    var tabs = document.querySelectorAll('#paramsTabs [data-bs-toggle="tab"]');
    tabs.forEach(function (btn) {
        btn.addEventListener('shown.bs.tab', function () {
            var pane = btn.getAttribute('data-bs-target').replace('#pane-', '');
            history.replaceState(null, '', '?tab=' + pane);
        });
    });

    // Activation auto depuis #hash dans l'URL (ex: redirect avec #horaires)
    var hash = window.location.hash.replace('#', '');
    if (hash) {
        var target = document.querySelector('[data-bs-target="#pane-' + hash + '"]');
        if (target) {
            var tab = new bootstrap.Tab(target);
            tab.show();
        }
    }

    // Aperçu couleurs en temps réel
    function bindColor(inputId, pickerId, cssVar, previewId) {
        var input  = document.getElementById(inputId);
        var picker = document.getElementById(pickerId);
        var badge  = previewId ? document.getElementById(previewId) : null;
        if (!input) return;
        function apply(val) {
            if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
                document.documentElement.style.setProperty(cssVar, val);
                if (badge) badge.style.background = val;
            }
        }
        input.addEventListener('input', function () { apply(this.value); });
        if (picker) picker.addEventListener('input', function () { apply(this.value); });
    }
    bindColor('couleur_principale', 'couleur_principale_picker', '--vg-bordeaux', 'preview-badge');
    bindColor('couleur_secondaire', 'couleur_secondaire_picker', '--vg-or', 'preview-badge2');
    bindColor('couleur_fond',       'couleur_fond_picker',       '--vg-creme', null);

    // Exemple réduction
    var seuil    = document.getElementById('reduction_seuil');
    var taux     = document.getElementById('reduction_taux');
    var pSeuil   = document.getElementById('prev-seuil');
    var pTaux    = document.getElementById('prev-taux');
    var pCommande = document.getElementById('prev-commande');
    var pMontant = document.getElementById('prev-montant');
    if (seuil && taux) {
        function updateReduc() {
            var s = parseFloat(seuil.value) || 0;
            var t = parseFloat(taux.value)  || 0;
            var total = Math.max(s + 20, s * 1.2, 120);
            if (pSeuil)    pSeuil.textContent    = s.toFixed(2) + ' €';
            if (pTaux)     pTaux.textContent     = t.toFixed(0) + '%';
            if (pCommande) pCommande.textContent = total.toFixed(2) + ' €';
            if (pMontant)  pMontant.textContent  = (total * t / 100).toFixed(2) + ' €';
        }
        seuil.addEventListener('input', updateReduc);
        taux.addEventListener('input', updateReduc);
    }

    // Compteurs textareas
    function bindCounter(inputId, countId) {
        var el  = document.getElementById(inputId);
        var cnt = document.getElementById(countId);
        if (!el || !cnt) return;
        function update() { cnt.textContent = el.value.length.toLocaleString('fr-FR'); }
        update();
        el.addEventListener('input', update);
    }
    bindCounter('cgv_contenu',      'count-cgv');
    bindCounter('mentions_contenu', 'count-mentions');
    bindCounter('hero_sous_titre',  'count-sous-titre');
    bindCounter('hero_paragraphe',  'count-paragraphe');

    // Prévisualisation images à l'upload
    document.querySelectorAll('input[data-preview]').forEach(function (input) {
        input.addEventListener('change', function () {
            if (!input.files[0]) return;
            var reader = new FileReader();
            reader.onload = function (e) {
                var preview = document.getElementById(input.dataset.preview);
                if (preview) preview.src = e.target.result;
            };
            reader.readAsDataURL(input.files[0]);
        });
    });
}());
</script>
