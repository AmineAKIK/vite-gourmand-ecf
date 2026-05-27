<?php
// src/views/pages/mentions.php
$pageTitle = buildPageTitle('Mentions légales');
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <nav aria-label="Fil d'Ariane">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/">Accueil</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Mentions légales</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<?php if (!empty($mentionsContenu)): ?>
<div class="container py-3">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <h1 class="fw-bold mb-4">Mentions légales</h1>
            <hr class="mb-4">
            <div class="legal-custom-content">
                <?= nl2br(sanitize($mentionsContenu)) ?>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<?php
$nom      = siteConfigValue('entreprise_nom',          siteName());
$forme    = siteConfigValue('entreprise_forme_juridique', '');
$adresse  = siteConfigValue('entreprise_adresse',       siteAddress());
$cp       = siteConfigValue('entreprise_code_postal',   sitePostalCode());
$ville    = siteConfigValue('entreprise_ville',         siteCity());
$tel      = siteConfigValue('entreprise_telephone',     sitePhone());
$email    = siteConfigValue('entreprise_email',         siteEmail());
$siret    = siteConfigValue('entreprise_siret',         '');
$tvaIntra = siteConfigValue('entreprise_tva_intracom',  '');
$domaine  = siteDomain();
$nomFull  = trim($nom . ($forme ? ' ' . $forme : ''));
?>
<div class="container py-3">
    <div class="row justify-content-center">
        <div class="col-lg-9">

            <nav aria-label="Fil d'Ariane">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/">Accueil</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Mentions légales</li>
                </ol>
            </nav>

            <h1 class="fw-bold mb-4">Mentions légales</h1>

            <hr class="mb-4">

            <section class="mb-5" aria-labelledby="editeur">
                <h2 id="editeur" class="h4 fw-bold text-vg">1. Éditeur du site</h2>
                <p>Le site <strong><?= sanitize($domaine) ?></strong> est édité par :</p>
                <address>
                    <strong><?= sanitize($nomFull) ?></strong><br>
                    <?php if ($adresse): ?><?= sanitize($adresse) ?><br><?php endif; ?>
                    <?php if ($cp || $ville): ?><?= sanitize(trim($cp . ' ' . $ville)) ?> — France<br><?php endif; ?>
                    <?php if ($tel): ?>Tél. : <?= sanitize($tel) ?><br><?php endif; ?>
                    <?php if ($email): ?>Email : <?= sanitize($email) ?><br><?php endif; ?>
                    <?php if ($siret): ?><br><strong>SIRET :</strong> <?= sanitize($siret) ?><br><?php endif; ?>
                    <?php if ($tvaIntra): ?><strong>TVA intracommunautaire :</strong> <?= sanitize($tvaIntra) ?><br><?php endif; ?>
                </address>
            </section>

            <section class="mb-5" aria-labelledby="hebergeur">
                <h2 id="hebergeur" class="h4 fw-bold text-vg">2. Hébergeur</h2>
                <address>
                    <strong>Railway</strong><br>
                    340 Pine Street, Suite 1802<br>
                    San Francisco, CA 94104 — États-Unis<br>
                    Site : <a href="https://railway.app" rel="noopener noreferrer" target="_blank">railway.app</a>
                </address>
            </section>

            <section class="mb-5" aria-labelledby="propriete">
                <h2 id="propriete" class="h4 fw-bold text-vg">3. Propriété intellectuelle</h2>
                <p>
                    L'ensemble du contenu de ce site (textes, images, logos, photographies, vidéos, etc.)
                    est la propriété exclusive de <strong><?= sanitize($nomFull) ?></strong> ou de ses partenaires,
                    et est protégé par le droit d'auteur conformément aux articles L.111-1 et suivants du
                    Code de la propriété intellectuelle.
                </p>
                <p>
                    Toute reproduction, représentation, modification, publication ou adaptation de tout ou
                    partie des éléments du site, quel que soit le moyen ou le procédé utilisé, est interdite
                    sans l'autorisation écrite préalable de <?= sanitize($nom) ?>.
                </p>
            </section>

            <section class="mb-5" aria-labelledby="responsabilite">
                <h2 id="responsabilite" class="h4 fw-bold text-vg">4. Responsabilité</h2>
                <p>
                    <?= sanitize($nom) ?> s'efforce de maintenir les informations publiées sur ce site exactes
                    et à jour. Cependant, l'entreprise ne peut garantir l'exactitude, la précision ou
                    l'exhaustivité des informations mises à disposition sur ce site.
                </p>
                <p>
                    En conséquence, <?= sanitize($nom) ?> décline toute responsabilité pour tout dommage
                    résultant de l'utilisation des informations contenues sur ce site ou de l'accès à
                    des sites tiers via des liens hypertextes.
                </p>
            </section>

            <section class="mb-5" aria-labelledby="donnees">
                <h2 id="donnees" class="h4 fw-bold text-vg">5. Données personnelles et RGPD</h2>
                <p>
                    Conformément au Règlement Général sur la Protection des Données (RGPD — UE 2016/679)
                    et à la loi Informatique et Libertés, vous disposez des droits suivants sur vos données :
                </p>
                <ul>
                    <li><strong>Droit d'accès</strong> : obtenir une copie de vos données personnelles.</li>
                    <li><strong>Droit de rectification</strong> : corriger des données inexactes ou incomplètes.</li>
                    <li><strong>Droit à l'effacement</strong> : demander la suppression de vos données.</li>
                    <li><strong>Droit à la portabilité</strong> : recevoir vos données dans un format structuré.</li>
                    <li><strong>Droit d'opposition</strong> : vous opposer au traitement de vos données.</li>
                </ul>
                <p>
                    Les données collectées (nom, prénom, email, adresse, téléphone) sont utilisées
                    exclusivement pour la gestion des commandes et la communication client.
                    Elles ne sont jamais cédées à des tiers à des fins commerciales.
                </p>
                <p>
                    <?php if ($email): ?>
                    <strong>Responsable du traitement :</strong> <?= sanitize($nom) ?> — <?= sanitize($email) ?><br>
                    <?php endif; ?>
                    <strong>Durée de conservation :</strong> 3 ans à compter de la dernière commande.<br>
                    <strong>Réclamations :</strong> vous pouvez adresser une réclamation à la
                    <a href="https://www.cnil.fr" rel="noopener noreferrer" target="_blank">CNIL</a>.
                </p>
            </section>

            <section class="mb-5" aria-labelledby="cookies">
                <h2 id="cookies" class="h4 fw-bold text-vg">6. Cookies</h2>
                <p>
                    Ce site utilise uniquement des cookies de session nécessaires au fonctionnement
                    de l'espace client (authentification, panier). Aucun cookie publicitaire ou de
                    traçage tiers n'est utilisé.
                </p>
            </section>

            <section class="mb-5" aria-labelledby="droit">
                <h2 id="droit" class="h4 fw-bold text-vg">7. Droit applicable</h2>
                <p>
                    Les présentes mentions légales sont régies par le droit français. En cas de litige,
                    et à défaut de résolution amiable, les tribunaux compétents de <strong><?= sanitize($ville ?: 'France') ?></strong>
                    seront saisis.
                </p>
            </section>

        </div>
    </div>
</div>
<?php endif; ?>
