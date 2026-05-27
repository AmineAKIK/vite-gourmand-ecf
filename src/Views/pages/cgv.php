<?php
// src/views/pages/cgv.php
$pageTitle = buildPageTitle('Conditions Générales de Vente');

$nom     = siteConfigValue('entreprise_nom',          siteName());
$forme   = siteConfigValue('entreprise_forme_juridique', '');
$email   = siteConfigValue('entreprise_email',         siteEmail());
$ville   = siteConfigValue('entreprise_ville',         siteCity());
$domaine = siteDomain();
$nomFull = trim($nom . ($forme ? ' ' . $forme : ''));

$livraisonBase = livraisonBase();
$livraisonKm   = livraisonKm();
$seuil         = reductionSeuilMontant();
$taux          = reductionTauxPourcentage();
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">

            <nav aria-label="Fil d'Ariane">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/">Accueil</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Conditions générales de vente</li>
                </ol>
            </nav>

            <h1 class="fw-bold mb-2">Conditions générales de vente</h1>

            <hr class="mb-4">

            <section class="mb-5" aria-labelledby="cgv-objet">
                <h2 id="cgv-objet" class="h4 fw-bold text-vg">1. Objet</h2>
                <p>
                    Les présentes Conditions Générales de Vente (CGV) s'appliquent à toutes les prestations
                    de service proposées par <strong><?= sanitize($nomFull) ?></strong> (ci-après « le Prestataire »)
                    auprès de tout client particulier ou professionnel (ci-après « le Client »),
                    qu'elles soient conclues en ligne sur <strong><?= sanitize($domaine) ?></strong> ou directement
                    en boutique.
                </p>
                <p>
                    Toute commande passée implique l'acceptation pleine et entière des présentes CGV,
                    sans réserve ni restriction.
                </p>
            </section>

            <section class="mb-5" aria-labelledby="cgv-delais">
                <h2 id="cgv-delais" class="h4 fw-bold text-vg">2. Délais de commande minimum</h2>
                <p>
                    Afin de garantir la fraîcheur et la qualité de nos prestations, toute commande doit
                    être passée selon les délais minimums suivants :
                </p>
                <ul>
                    <li><strong>Prestation standard (jusqu'à 20 personnes) :</strong> minimum <strong>5 jours ouvrés</strong> avant la date de l'événement.</li>
                    <li><strong>Prestation intermédiaire (21 à 50 personnes) :</strong> minimum <strong>10 jours ouvrés</strong> avant la date de l'événement.</li>
                    <li><strong>Grande prestation (plus de 50 personnes) :</strong> minimum <strong>15 jours ouvrés</strong> avant la date de l'événement.</li>
                </ul>
                <p>
                    Aucune commande ne peut être acceptée en dehors de ces délais. Le Prestataire se
                    réserve le droit de refuser exceptionnellement toute demande ne respectant pas ces
                    critères, sans que cela n'ouvre droit à indemnisation.
                </p>
            </section>

            <section class="mb-5" aria-labelledby="cgv-prix">
                <h2 id="cgv-prix" class="h4 fw-bold text-vg">3. Prix et modalités de paiement</h2>
                <p>
                    Les prix sont exprimés en euros TTC. Ils sont calculés sur la base du menu choisi,
                    du nombre de personnes et des éventuelles options supplémentaires.
                </p>
                <p>
                    Le paiement est exigible à la confirmation de commande. Les moyens de paiement
                    acceptés sont : carte bancaire (Visa, Mastercard), virement bancaire, chèque
                    (libellé à l'ordre de <?= sanitize($nom) ?>).
                </p>
                <p>
                    En cas de non-paiement à l'échéance, des pénalités de retard au taux légal en
                    vigueur seront appliquées de plein droit, ainsi qu'une indemnité forfaitaire de
                    recouvrement de 40 €.
                </p>
            </section>

            <section class="mb-5" aria-labelledby="cgv-reduction">
                <h2 id="cgv-reduction" class="h4 fw-bold text-vg">4. Réductions fidélité</h2>
                <p>
                    Une <strong>réduction de <?= (int)$taux ?> %</strong> est appliquée automatiquement
                    sur le montant total hors livraison lorsque la commande dépasse
                    <strong><?= formatPrice($seuil) ?></strong> (hors frais de livraison).
                </p>
                <p>
                    Cette réduction est non cumulable avec d'autres offres promotionnelles en cours.
                </p>
            </section>

            <section class="mb-5" aria-labelledby="cgv-livraison">
                <h2 id="cgv-livraison" class="h4 fw-bold text-vg">5. Livraison</h2>
                <p>
                    La livraison est incluse dans le prix pour toute prestation réalisée
                    <strong>à <?= sanitize(siteCity()) ?></strong>.
                </p>
                <p>
                    Pour les prestations hors de <?= sanitize(siteCity()) ?>, des frais de livraison sont appliqués selon
                    le barème suivant :
                </p>
                <ul>
                    <li>Forfait fixe : <strong><?= formatPrice($livraisonBase) ?></strong></li>
                    <li>Plus : <strong><?= number_format($livraisonKm, 2, ',', ' ') ?> € par kilomètre</strong> depuis le centre ville jusqu'au lieu de livraison.</li>
                </ul>
                <p>
                    La distance est calculée en kilomètres via le trajet routier le plus court.
                    Le Client est informé du montant exact des frais de livraison avant la confirmation
                    de sa commande.
                </p>
                <p>
                    En cas d'inaccessibilité du lieu de livraison ou d'absence du Client au moment de
                    la livraison, le Prestataire se réserve le droit de facturer un second passage.
                </p>
            </section>

            <section class="mb-5" aria-labelledby="cgv-materiel">
                <h2 id="cgv-materiel" class="h4 fw-bold text-vg">6. Matériel et retour du matériel</h2>
                <p>
                    Le Prestataire peut mettre à disposition du matériel de service (plats, couverts,
                    ustensiles, mobilier de service, etc.) dans le cadre de certaines prestations.
                    Ce matériel reste la propriété exclusive de <?= sanitize($nom) ?>.
                </p>
                <p>
                    Le Client s'engage à restituer l'intégralité du matériel prêté dans un état
                    propre et conforme à son état initial, dans un délai maximum de
                    <strong>10 jours ouvrés</strong> suivant la date de la prestation.
                </p>
                <div class="alert alert-warning" role="alert" aria-label="Pénalité de retard de retour matériel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <strong>Pénalité de non-restitution :</strong> en cas de non-retour du matériel
                    dans le délai imparti de 10 jours ouvrés, une pénalité forfaitaire de
                    <strong>600,00 € TTC</strong> sera facturée au Client, sans mise en demeure
                    préalable. Cette pénalité couvre les frais de remplacement et de gestion
                    administrative du matériel manquant.
                </div>
                <p>
                    En cas de casse ou de dégradation du matériel, le coût de remplacement à neuf
                    sera facturé en sus de la pénalité éventuelle de retard, sur présentation
                    d'une facture de remplacement.
                </p>
            </section>

            <section class="mb-5" aria-labelledby="cgv-annulation">
                <h2 id="cgv-annulation" class="h4 fw-bold text-vg">7. Annulation et modification</h2>
                <p>
                    Toute annulation doit être notifiée par écrit (email ou courrier) à
                    <?= sanitize($email) ?>. Les conditions d'annulation sont les suivantes :
                </p>
                <ul>
                    <li><strong>Plus de 15 jours ouvrés avant la prestation :</strong> remboursement intégral.</li>
                    <li><strong>Entre 10 et 15 jours ouvrés :</strong> retenue de 25 % du montant total.</li>
                    <li><strong>Entre 5 et 10 jours ouvrés :</strong> retenue de 50 % du montant total.</li>
                    <li><strong>Moins de 5 jours ouvrés :</strong> aucun remboursement, la totalité est due.</li>
                </ul>
            </section>

            <section class="mb-5" aria-labelledby="cgv-responsabilite">
                <h2 id="cgv-responsabilite" class="h4 fw-bold text-vg">8. Responsabilité et allergènes</h2>
                <p>
                    Le Client est tenu d'informer le Prestataire de toute allergie ou régime alimentaire
                    spécifique au moment de la commande. <?= sanitize($nom) ?> s'engage à prendre toutes
                    les précautions nécessaires mais ne saurait être tenu responsable en cas d'information
                    incomplète communiquée par le Client.
                </p>
            </section>

            <section class="mb-5" aria-labelledby="cgv-litiges">
                <h2 id="cgv-litiges" class="h4 fw-bold text-vg">9. Litiges et droit applicable</h2>
                <p>
                    Les présentes CGV sont régies par le droit français. En cas de litige, les parties
                    s'engagent à rechercher une solution amiable avant toute action judiciaire.
                    À défaut d'accord, le tribunal compétent de <strong><?= sanitize($ville ?: 'France') ?></strong> sera saisi.
                </p>
                <p>
                    Conformément à l'article L.612-1 du Code de la consommation, le Client peut
                    recourir gratuitement au médiateur de la consommation dont relève le Prestataire.
                </p>
            </section>

        </div>
    </div>
</div>
