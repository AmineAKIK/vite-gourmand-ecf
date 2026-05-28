<?php
$pageTitle = buildPageTitle('Recherche');
$totalResultats = count($commandes) + count($clients) + count($documents);
?>
<div class="workspace-section">
    <div class="d-flex align-items-center gap-3 mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-search me-2 text-vg"></i>Recherche</h1>
    </div>

    <!-- Barre de recherche -->
    <form method="GET" action="/employe/recherche" class="mb-4" role="search">
        <div class="input-group" style="max-width:480px;">
            <input type="search" class="form-control" name="q"
                   value="<?= sanitize($q) ?>"
                   placeholder="Commande, client, document…"
                   aria-label="Terme de recherche"
                   minlength="2"
                   autofocus>
            <button class="btn btn-vg" type="submit">
                <i class="bi bi-search me-1"></i>Rechercher
            </button>
        </div>
    </form>

    <?php if ($q !== '' && strlen($q) < 2): ?>
        <div class="alert alert-warning">Saisissez au moins 2 caractères.</div>
    <?php elseif ($q !== '' && $totalResultats === 0): ?>
        <div class="alert alert-info">Aucun résultat pour <strong><?= sanitize($q) ?></strong>.</div>
    <?php elseif ($q !== ''): ?>
        <p class="text-muted small mb-4"><?= $totalResultats ?> résultat<?= $totalResultats > 1 ? 's' : '' ?> pour <strong><?= sanitize($q) ?></strong></p>

        <!-- Commandes -->
        <?php if (!empty($commandes)): ?>
        <section class="mb-4">
            <h2 class="h5 mb-3"><i class="bi bi-bag me-2 text-vg"></i>Commandes (<?= count($commandes) ?>)</h2>
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>N°</th>
                                <th>Client</th>
                                <th>Date prestation</th>
                                <th>Total</th>
                                <th>Statut</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($commandes as $cmd): ?>
                            <tr>
                                <td><small class="text-muted"><?= sanitize($cmd['numero_commande'] ?? '') ?></small></td>
                                <td><?= sanitize(trim(($cmd['prenom'] ?? '') . ' ' . ($cmd['nom'] ?? ''))) ?></td>
                                <td><?= sanitize(formatDateFr($cmd['date_prestation'] ?? null)) ?></td>
                                <td><?= sanitize(formatPrice($cmd['prix_total'] ?? 0)) ?></td>
                                <td><?= commandeStatusBadge($cmd['statut'] ?? null) ?></td>
                                <td>
                                    <a href="/employe/commandes?q=<?= urlencode($cmd['numero_commande'] ?? '') ?>"
                                       class="btn btn-sm btn-vg-outline">Voir</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Clients -->
        <?php if (!empty($clients)): ?>
        <section class="mb-4">
            <h2 class="h5 mb-3"><i class="bi bi-people me-2 text-vg"></i>Clients (<?= count($clients) ?>)</h2>
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Téléphone</th>
                                <th>Ville</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($clients as $client): ?>
                            <tr>
                                <td><?= sanitize($client['prenom'] . ' ' . $client['nom']) ?></td>
                                <td><?= sanitize($client['email']) ?></td>
                                <td><?= sanitize($client['telephone'] ?? '—') ?></td>
                                <td><?= sanitize($client['ville'] ?? '—') ?></td>
                                <td>
                                    <a href="/employe/commandes?q=<?= urlencode($client['email']) ?>"
                                       class="btn btn-sm btn-vg-outline">Commandes</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Documents -->
        <?php if (!empty($documents)): ?>
        <section class="mb-4">
            <h2 class="h5 mb-3"><i class="bi bi-file-text me-2 text-vg"></i>Documents (<?= count($documents) ?>)</h2>
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>N° document</th>
                                <th>Type</th>
                                <th>Client</th>
                                <th>Date</th>
                                <th>Total TTC</th>
                                <th>Statut</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($documents as $doc): ?>
                            <tr>
                                <td><small class="text-muted"><?= sanitize($doc['numero_document'] ?? '—') ?></small></td>
                                <td><?= sanitize(ucfirst($doc['type_document'] ?? '')) ?></td>
                                <td><?= sanitize($doc['client_nom'] ?? '') ?></td>
                                <td><?= sanitize(formatDateFr($doc['date_emission'] ?? null)) ?></td>
                                <td><?= sanitize(formatPrice($doc['total_ttc'] ?? 0)) ?></td>
                                <td><span class="badge bg-secondary"><?= sanitize($doc['statut'] ?? '') ?></span></td>
                                <td>
                                    <a href="/employe/commandes?q=<?= urlencode($doc['commande_id'] ?? '') ?>"
                                       class="btn btn-sm btn-vg-outline">Commande</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        <?php endif; ?>

    <?php else: ?>
        <p class="text-muted">Saisissez un terme pour rechercher parmi les commandes, clients et documents.</p>
    <?php endif; ?>
</div>
