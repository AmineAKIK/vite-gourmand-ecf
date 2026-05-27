<?php
// src/views/pages/admin/comptabilite.php
$isAssujetti  = ($regimeTva ?? 'assujetti') === 'assujetti';
$totalTTC     = (float)($synthese['total_ttc']        ?? 0);
$totalHT      = (float)($synthese['total_ht']         ?? 0);
$totalTVA     = (float)($synthese['total_tva']        ?? 0);
$encaisse     = (float)($synthese['montant_encaisse'] ?? 0);
$soldeRestant = (float)($synthese['solde_restant']    ?? 0);
$nbCommandes  = (int)  ($synthese['nb_commandes']     ?? 0);

$siret = trim((string)($config['entreprise_siret'] ?? ''));
?>

<div class="container py-4 comptabilite-page">

    <?php partial('partials/page_title_bar', ['icon' => 'bi-archive', 'title' => 'Comptabilité']); ?>
    <p class="text-muted mb-4">
        Exports des données financières. Toutes les commandes au statut accepté ou ultérieur sont comptabilisées.
        <?php if (!$isAssujetti): ?>
        <span class="badge bg-secondary ms-1">Régime non-assujetti TVA (art. 293 B CGI)</span>
        <?php endif; ?>
    </p>

    <?php if (!$siret): ?>
    <div class="alert alert-warning d-flex gap-2 align-items-start mb-4">
        <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
        <div>
            <strong>SIRET manquant.</strong>
            Les exports incluent vos coordonnées d'entreprise. Sans SIRET, les documents générés ne sont pas
            fiscalement valides.
            <a href="/admin/parametres#entreprise" class="alert-link ms-1">Configurer l'entreprise →</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- KPI snapshot (toutes périodes) -->
    <section class="stats-kpi-grid mb-5" aria-label="Soldes globaux">
        <article class="stats-kpi-card">
            <span class="stats-kpi-label">CA TTC cumulé</span>
            <strong class="stats-kpi-value"><?= sanitize(formatPrice($totalTTC)) ?></strong>
            <?php if ($isAssujetti): ?>
            <span class="stats-kpi-note">HT : <?= sanitize(formatPrice($totalHT)) ?></span>
            <?php endif; ?>
        </article>
        <?php if ($isAssujetti): ?>
        <article class="stats-kpi-card">
            <span class="stats-kpi-label">TVA collectée</span>
            <strong class="stats-kpi-value"><?= sanitize(formatPrice($totalTVA)) ?></strong>
            <span class="stats-kpi-note">Toutes périodes</span>
        </article>
        <?php endif; ?>
        <article class="stats-kpi-card">
            <span class="stats-kpi-label">Encaissé</span>
            <strong class="stats-kpi-value"><?= sanitize(formatPrice($encaisse)) ?></strong>
            <?php if ($soldeRestant > 0): ?>
            <span class="stats-kpi-note stats-kpi-note--alert">Solde restant : <?= sanitize(formatPrice($soldeRestant)) ?></span>
            <?php else: ?>
            <span class="stats-kpi-note">Tout soldé</span>
            <?php endif; ?>
        </article>
        <article class="stats-kpi-card">
            <span class="stats-kpi-label">Commandes</span>
            <strong class="stats-kpi-value"><?= sanitize(formatInteger($nbCommandes)) ?></strong>
            <span class="stats-kpi-note">Acceptées et au-delà</span>
        </article>
    </section>

    <!-- Exports -->
    <section class="comptabilite-exports">
        <h2 class="h5 mb-4 fw-semibold">Exports CSV</h2>

        <div class="row g-4">

            <!-- Export 1 — Journal commandes -->
            <div class="col-12 col-lg-4">
                <div class="card h-100 shadow-sm comptabilite-export-card">
                    <div class="card-body d-flex flex-column">
                        <div class="comptabilite-export-icon mb-3">
                            <i class="bi bi-file-earmark-spreadsheet text-vg" style="font-size:2rem"></i>
                        </div>
                        <h3 class="h6 fw-bold mb-1">Journal des commandes</h3>
                        <p class="small text-muted flex-grow-1">
                            Une ligne par commande. Contient : numéro, dates, client, total TTC
                            <?= $isAssujetti ? ', HT, TVA' : '' ?>,
                            encaissé, solde restant, statut paiement.
                            Idéal pour la saisie en comptabilité générale.
                        </p>
                        <form method="GET" action="/admin/comptabilite/export" class="comptabilite-export-form mt-2">
                            <input type="hidden" name="format" value="commandes">
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label form-label-sm">Du</label>
                                    <input type="date" class="form-control form-control-sm" name="date_debut">
                                </div>
                                <div class="col-6">
                                    <label class="form-label form-label-sm">Au</label>
                                    <input type="date" class="form-control form-control-sm" name="date_fin"
                                           value="<?= date('Y-m-d') ?>">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-vg btn-sm w-100">
                                <i class="bi bi-download me-1"></i>Télécharger
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Export 2 — Journal lignes -->
            <div class="col-12 col-lg-4">
                <div class="card h-100 shadow-sm comptabilite-export-card">
                    <div class="card-body d-flex flex-column">
                        <div class="comptabilite-export-icon mb-3">
                            <i class="bi bi-list-ul text-vg" style="font-size:2rem"></i>
                        </div>
                        <h3 class="h6 fw-bold mb-1">Journal des lignes</h3>
                        <p class="small text-muted flex-grow-1">
                            Une ligne par menu dans chaque commande. Contient le détail du prix brut,
                            remise appliquée, frais de livraison<?= $isAssujetti ? ', HT et TVA par ligne' : '' ?>.
                            Pour une ventilation analytique par prestation.
                        </p>
                        <form method="GET" action="/admin/comptabilite/export" class="comptabilite-export-form mt-2">
                            <input type="hidden" name="format" value="lignes">
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label form-label-sm">Du</label>
                                    <input type="date" class="form-control form-control-sm" name="date_debut">
                                </div>
                                <div class="col-6">
                                    <label class="form-label form-label-sm">Au</label>
                                    <input type="date" class="form-control form-control-sm" name="date_fin"
                                           value="<?= date('Y-m-d') ?>">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-vg btn-sm w-100">
                                <i class="bi bi-download me-1"></i>Télécharger
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Export 3 — Récapitulatif mensuel TVA -->
            <div class="col-12 col-lg-4">
                <div class="card h-100 shadow-sm comptabilite-export-card">
                    <div class="card-body d-flex flex-column">
                        <div class="comptabilite-export-icon mb-3">
                            <i class="bi bi-calendar-month text-vg" style="font-size:2rem"></i>
                        </div>
                        <h3 class="h6 fw-bold mb-1">Récapitulatif mensuel</h3>
                        <p class="small text-muted flex-grow-1">
                            Agrégé par mois : nombre de commandes, CA TTC<?= $isAssujetti ? ', CA HT et TVA collectée' : '' ?>,
                            panier moyen. Pour les déclarations<?= $isAssujetti ? ' TVA (CA3, CA12)' : '' ?> et le suivi du seuil de franchise.
                        </p>
                        <form method="GET" action="/admin/comptabilite/export" class="comptabilite-export-form mt-2">
                            <input type="hidden" name="format" value="mensuel">
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label form-label-sm">Du</label>
                                    <input type="date" class="form-control form-control-sm" name="date_debut">
                                </div>
                                <div class="col-6">
                                    <label class="form-label form-label-sm">Au</label>
                                    <input type="date" class="form-control form-control-sm" name="date_fin"
                                           value="<?= date('Y-m-d') ?>">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-vg btn-sm w-100">
                                <i class="bi bi-download me-1"></i>Télécharger
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- Notes pratiques -->
    <section class="comptabilite-notes mt-5">
        <h2 class="h6 fw-semibold mb-3">Notes pratiques</h2>
        <ul class="small text-muted mb-0">
            <li>Les fichiers CSV utilisent le séparateur <strong>;</strong> (point-virgule) et l'encodage <strong>UTF-8 avec BOM</strong> — compatibles Excel et LibreOffice sans manipulation.</li>
            <li>La <strong>date de comptabilisation</strong> correspond à la date d'acceptation de la commande (ou à défaut à la date de commande) — c'est la date fiscalement pertinente pour le CA.</li>
            <?php if ($isAssujetti): ?>
            <li>Les montants <strong>HT</strong> sont calculés depuis le TTC en appliquant le taux TVA snapshot enregistré au moment de la commande — cohérents même si les taux changent.</li>
            <li>Pour votre déclaration TVA, utilisez le <strong>récapitulatif mensuel</strong> : la colonne « TVA collectée » correspond à la TVA à reverser sur chaque mois.</li>
            <?php else: ?>
            <li>Régime non-assujetti TVA (art. 293 B CGI) : aucune TVA n'est collectée ni à reverser. Vos factures ne doivent pas mentionner de TVA.</li>
            <?php endif; ?>
            <li>Pour transmettre les données à votre expert-comptable, le <strong>journal des commandes</strong> ou le <strong>journal des lignes</strong> selon la granularité souhaitée.</li>
        </ul>
    </section>

</div>
