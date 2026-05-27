<?php
// src/views/pages/employe/document_preview.php
$type = $document['type_document'] ?? 'facture';
$isTicket = $type === 'ticket';
$typeLabel = $isTicket ? 'Ticket de caisse' : 'Facture';
$entreprise = $document['entreprise'] ?? [];
$isFinalise = ($document['statut'] ?? '') === 'finalise';
$documentRef = $document['numero_document'] ?: ('Brouillon #' . (int)$document['document_id']);
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
        <?php if ($isFinalise): ?>
            <a href="/employe/document/export?id=<?= (int)$document['document_id'] ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-braces me-1"></i>Export JSON
            </a>
            <form method="POST" action="/employe/document/archiver" class="d-inline">
                <?= csrfField() ?>
                <input type="hidden" name="document_id" value="<?= (int)$document['document_id'] ?>">
                <button type="submit" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-archive me-1"></i>Archiver
                </button>
            </form>
            <form method="POST" action="/employe/document/envoyer" class="d-inline" data-confirm="Envoyer ce document au client par email ?">
                <?= csrfField() ?>
                <input type="hidden" name="document_id" value="<?= (int)$document['document_id'] ?>">
                <button type="submit" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-envelope me-1"></i>Envoyer
                </button>
            </form>
            <?php if (!empty($document['archive_path'])): ?>
                <a href="/<?= sanitize($document['archive_path']) ?>" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Archive
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if ($isFinalise): ?>
        <div class="facturation-state-strip mb-4">
            <span><i class="bi bi-lock me-1"></i>Finalisé</span>
            <span><i class="bi bi-archive me-1"></i><?= !empty($document['archive_path']) ? 'Archivé' : 'Archive à générer' ?></span>
            <span><i class="bi bi-envelope me-1"></i><?= !empty($document['sent_at']) ? 'Envoyé le ' . sanitize(formatDateTimeFr($document['sent_at'])) : 'Non envoyé' ?></span>
        </div>
    <?php endif; ?>

    <article class="document-preview <?= $isTicket ? 'document-preview-ticket' : '' ?>">
        <header class="document-preview-header">
            <div>
                <p class="document-brand"><?= sanitize($entreprise['nom'] ?? siteName()) ?></p>
                <address>
                    <?= sanitize($entreprise['adresse'] ?? 'Bordeaux') ?><br>
                    <?= sanitize($entreprise['email'] ?? MAIL_FROM) ?>
                </address>
            </div>
            <div class="document-meta">
                <h2><?= sanitize($typeLabel) ?></h2>
                <p><?= sanitize($documentRef) ?></p>
                <p><?= sanitize(formatDateFr($document['date_emission'] ?? null)) ?></p>
                <?php if ($isFinalise && !empty($document['finalized_at'])): ?>
                    <p>Finalisé le <?= sanitize(formatDateTimeFr($document['finalized_at'])) ?></p>
                <?php endif; ?>
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
                    Statut document : <?= $isFinalise ? 'finalisé' : 'brouillon' ?>
                </p>
            </div>
        </section>

        <section class="document-electronic">
            <h3>Préparation facturation électronique</h3>
            <p>
                SIREN client : <?= sanitize($document['client_siren'] ?: 'non renseigné') ?><br>
                Adresse de livraison : <?= sanitize(trim(($document['adresse_livraison'] ?? '') . ', ' . ($document['code_postal_livraison'] ?? '') . ' ' . ($document['ville_livraison'] ?? ''))) ?><br>
                Catégorie : <?= sanitize(match ($document['categorie_operation'] ?? 'mixte') {
                    'biens' => 'Livraison de biens',
                    'services' => 'Prestation de services',
                    default => 'Livraison de biens et prestation de services',
                }) ?><br>
                Option TVA sur les débits : <?= !empty($document['option_tva_debits']) ? 'oui' : 'non' ?>
            </p>
        </section>

        <?php if ($isTicket): ?>
            <div class="document-ticket-lines">
                <?php foreach ($document['lignes'] ?? [] as $ligne): ?>
                    <div class="document-ticket-line">
                        <div class="document-ticket-line-main">
                            <strong><?= sanitize($ligne['designation'] ?? '') ?></strong>
                            <span><?= sanitize(formatPrice($ligne['total_ttc'] ?? 0)) ?></span>
                        </div>
                        <div class="document-ticket-line-meta">
                            <span>Qté <?= sanitize(formatPriceInput($ligne['quantite'] ?? 0)) ?></span>
                            <span>PU <?= sanitize(formatPrice($ligne['prix_unitaire_ttc'] ?? 0)) ?></span>
                            <span>TVA <?= sanitize(formatPriceInput($ligne['taux_tva'] ?? 0)) ?> %</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
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
                                <td data-label="Désignation"><?= sanitize($ligne['designation'] ?? '') ?></td>
                                <td data-label="Qté" class="text-end"><?= sanitize(formatPriceInput($ligne['quantite'] ?? 0)) ?></td>
                                <td data-label="PU TTC" class="text-end"><?= sanitize(formatPrice($ligne['prix_unitaire_ttc'] ?? 0)) ?></td>
                                <td data-label="TVA" class="text-end"><?= sanitize(formatPriceInput($ligne['taux_tva'] ?? 0)) ?> %</td>
                                <td data-label="Total TTC" class="text-end"><?= sanitize(formatPrice($ligne['total_ttc'] ?? 0)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

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
