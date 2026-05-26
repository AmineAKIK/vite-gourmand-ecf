<?php
// src/views/pages/employe/document_preview.php
$type = $document['type_document'] ?? 'facture';
$isTicket = $type === 'ticket';
$typeLabel = $isTicket ? 'Ticket de caisse' : 'Facture';
$entreprise = $document['entreprise'] ?? [];
?>
<div class="container py-5 facturation-page">

    <?php partial('partials/page_title_bar', ['icon' => $isTicket ? 'bi-receipt' : 'bi-file-earmark-text', 'title' => 'Aperçu ' . strtolower($typeLabel)]); ?>

    <div class="facturation-toolbar mb-4">
        <a href="/employe/document/edit?id=<?= (int)$document['document_id'] ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-pencil me-1"></i>Retour éditeur
        </a>
        <button type="button" class="btn btn-vg btn-sm" data-print-document>
            <i class="bi bi-printer me-1"></i>Imprimer
        </button>
    </div>

    <article class="document-preview <?= $isTicket ? 'document-preview-ticket' : '' ?>">
        <header class="document-preview-header">
            <div>
                <p class="document-brand">Vite &amp; Gourmand</p>
                <address>
                    <?= sanitize($entreprise['adresse'] ?? 'Bordeaux') ?><br>
                    <?= sanitize($entreprise['email'] ?? MAIL_FROM) ?>
                </address>
            </div>
            <div class="document-meta">
                <h2><?= sanitize($typeLabel) ?></h2>
                <p>Brouillon #<?= (int)$document['document_id'] ?></p>
                <p><?= sanitize(formatDateFr($document['date_emission'] ?? null)) ?></p>
            </div>
        </header>

        <section class="document-parties">
            <div>
                <h3>Client</h3>
                <p>
                    <strong><?= sanitize($document['client_nom'] ?? '') ?></strong><br>
                    <?= sanitize($document['client_adresse'] ?? '') ?><br>
                    <?= sanitize(trim(($document['client_code_postal'] ?? '') . ' ' . ($document['client_ville'] ?? ''))) ?><br>
                    <?= sanitize($document['client_email'] ?? '') ?>
                </p>
            </div>
            <div>
                <h3>Commande</h3>
                <p>
                    <?= sanitize($commande['numero_commande'] ?? '') ?><br>
                    Prestation : <?= sanitize(formatDateFr($document['date_prestation'] ?? null)) ?><br>
                    Statut document : <?= sanitize($document['statut'] ?? 'brouillon') ?>
                </p>
            </div>
        </section>

        <div class="document-lines">
            <table>
                <thead>
                    <tr>
                        <th>Désignation</th>
                        <th class="text-end">Qté</th>
                        <th class="text-end">PU TTC</th>
                        <th class="text-end">TVA</th>
                        <th class="text-end">Total TTC</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($document['lignes'] ?? [] as $ligne): ?>
                        <tr>
                            <td><?= sanitize($ligne['designation'] ?? '') ?></td>
                            <td class="text-end"><?= sanitize(formatPriceInput($ligne['quantite'] ?? 0)) ?></td>
                            <td class="text-end"><?= sanitize(formatPrice($ligne['prix_unitaire_ttc'] ?? 0)) ?></td>
                            <td class="text-end"><?= sanitize(formatPriceInput($ligne['taux_tva'] ?? 0)) ?> %</td>
                            <td class="text-end"><?= sanitize(formatPrice($ligne['total_ttc'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <section class="document-totals">
            <div>
                <?php if (!empty($document['note_publique'])): ?>
                    <p><?= nl2br(sanitize($document['note_publique'])) ?></p>
                <?php endif; ?>
            </div>
            <dl>
                <div><dt>Total HT</dt><dd><?= sanitize(formatPrice($document['total_ht'] ?? 0)) ?></dd></div>
                <div><dt>TVA</dt><dd><?= sanitize(formatPrice($document['total_tva'] ?? 0)) ?></dd></div>
                <div class="document-total-main"><dt>Total TTC</dt><dd><?= sanitize(formatPrice($document['total_ttc'] ?? 0)) ?></dd></div>
            </dl>
        </section>

        <?php if (!empty($document['mention_legale'])): ?>
            <footer class="document-footer">
                <?= nl2br(sanitize($document['mention_legale'])) ?>
            </footer>
        <?php endif; ?>
    </article>

</div>
