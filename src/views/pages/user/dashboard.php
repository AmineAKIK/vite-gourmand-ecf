<?php $pageTitle = 'Mon compte - Vite & Gourmand'; ?>
<div class="container py-5 account-page">
    <h1 class="mb-4">Bonjour, <?= sanitize($userFull['prenom']) ?> !</h1>

    <div class="row g-4">
        <!-- Mes commandes -->
        <div class="col-lg-8">
            <section class="account-panel p-4 h-100">
                <h2 class="h4 mb-3">Mes commandes</h2>
                <?php if (empty($commandes)): ?>
                    <div class="alert alert-info mb-0">
                        Vous n'avez pas encore de commande.
                        <a href="/menus" class="alert-link">Découvrir nos menus</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive account-orders-wrap">
                        <table class="table table-hover align-middle mb-0 account-orders-table" aria-label="Mes commandes">
                            <thead class="table-light">
                                <tr>
                                    <th class="d-none d-xl-table-cell">N°</th><th>Menu</th><th>Date</th><th>Total</th><th>Statut</th><th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($commandes as $cmd): ?>
                                <tr>
                                    <td class="d-none d-xl-table-cell" data-label="N°"><small class="text-muted" style="font-size:.7rem;"><?= sanitize($cmd['numero_commande']) ?></small></td>
                                    <td class="small account-order-title" data-label="Menu"><?= sanitize($cmd['menu_titre']) ?></td>
                                    <td class="text-nowrap small" data-label="Date"><?= sanitize(formatDateFr($cmd['date_prestation'] ?? null)) ?></td>
                                    <td class="text-nowrap" data-label="Total"><strong><?= sanitize(formatPrice($cmd['prix_total'] ?? 0)) ?></strong></td>
                                    <td data-label="Statut"><?= commandeStatusBadge($cmd['statut'] ?? null) ?></td>
                                    <td class="account-order-actions-cell" data-label="Actions">
                                        <?php if (commandeCanClientModify($cmd)): ?>
                                            <div class="account-order-actions">
                                            <button class="btn btn-sm btn-vg-outline" data-bs-toggle="modal" data-bs-target="#modifModal<?= (int)$cmd['commande_id'] ?>">
                                                <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Modifier
                                            </button>
                                            <form method="POST" action="/commande/annuler" data-confirm="Confirmer l'annulation ?">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="commande_id" value="<?= (int)$cmd['commande_id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Annuler
                                                </button>
                                            </form>
                                            </div>
                                        <?php elseif (commandeCanClientTrack($cmd['statut'] ?? null)): ?>
                                            <div class="account-order-actions">
                                                <a href="/commande/suivi?id=<?= (int)$cmd['commande_id'] ?>" class="btn btn-sm btn-vg-outline">
                                                    <i class="bi bi-truck me-1" aria-hidden="true"></i>Suivi
                                                </a>
                                            </div>
                                        <?php elseif (commandeCanReview($cmd['statut'] ?? null)): ?>
                                            <?php $avisCmd = $avisByCommande[(int)$cmd['commande_id']] ?? null; ?>
                                            <?php if ($avisCmd): ?>
                                                <?php if ($avisCmd['statut'] === 'valide'): ?>
                                                    <span class="badge bg-success"><i class="bi bi-star-fill me-1"></i>Avis publié</span>
                                                <?php elseif ($avisCmd['statut'] === 'refuse'): ?>
                                                    <span class="badge bg-danger">Avis refusé</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark"><i class="bi bi-hourglass me-1"></i>Avis en attente</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <div class="account-order-actions">
                                                    <button class="btn btn-sm btn-vg" data-bs-toggle="modal" data-bs-target="#avisModal<?= (int)$cmd['commande_id'] ?>">
                                                        <i class="bi bi-star me-1"></i>Donner un avis
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <!-- Mes infos -->
        <div class="col-lg-4">
            <div class="card account-panel p-4">
                <h2 class="h5 mb-3">Mes informations</h2>
                <form method="POST" action="/mon-compte/modifier">
                    <?= csrfField() ?>
                    <div class="mb-2">
                        <label for="profil_prenom" class="form-label small">Prénom</label>
                        <input type="text" class="form-control form-control-sm" id="profil_prenom" name="prenom" value="<?= sanitize($userFull['prenom']) ?>" required>
                    </div>
                    <div class="mb-2">
                        <label for="profil_nom" class="form-label small">Nom</label>
                        <input type="text" class="form-control form-control-sm" id="profil_nom" name="nom" value="<?= sanitize($userFull['nom']) ?>" required>
                    </div>
                    <div class="mb-2">
                        <label for="profil_telephone" class="form-label small">Téléphone</label>
                        <input type="tel" class="form-control form-control-sm" id="profil_telephone" name="telephone" value="<?= sanitize($userFull['telephone'] ?? '') ?>">
                    </div>
                    <div class="mb-2">
                        <label for="profil_adresse" class="form-label small">Adresse</label>
                        <input type="text" class="form-control form-control-sm" id="profil_adresse" name="adresse" value="<?= sanitize($userFull['adresse'] ?? '') ?>">
                    </div>
                    <div class="mb-2">
                        <label for="profil_ville" class="form-label small">Ville</label>
                        <input type="text" class="form-control form-control-sm" id="profil_ville" name="ville" value="<?= sanitize($userFull['ville'] ?? '') ?>">
                    </div>
                    <div class="mb-2">
                        <label for="profil_code_postal" class="form-label small">Code postal</label>
                        <input type="text" class="form-control form-control-sm" id="profil_code_postal" name="code_postal" value="<?= sanitize($userFull['code_postal'] ?? '') ?>">
                    </div>
                    <button type="submit" class="btn btn-vg btn-sm w-100 mt-2">Enregistrer</button>
                </form>
                <hr class="my-3">
                <button class="btn btn-outline-danger btn-sm w-100" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                    <i class="bi bi-trash me-1"></i>Supprimer mon compte
                </button>
            </div>
        </div>
    </div>

    <?php foreach ($commandes as $cmd): if (!commandeCanClientModify($cmd)) continue; ?>
    <div class="modal fade" id="modifModal<?= (int)$cmd['commande_id'] ?>" tabindex="-1"
         aria-labelledby="modifModalLabel<?= (int)$cmd['commande_id'] ?>" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modifModalLabel<?= (int)$cmd['commande_id'] ?>">
                        Modifier la commande <?= sanitize($cmd['numero_commande']) ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <form method="POST" action="/commande/modifier">
                    <?= csrfField() ?>
                    <input type="hidden" name="commande_id" value="<?= (int)$cmd['commande_id'] ?>">
                    <div class="modal-body">
                        <div id="modif_<?= (int)$cmd['commande_id'] ?>-error" class="alert alert-danger d-none" role="alert"></div>
                        <div class="mb-3">
                            <label for="adresse_<?= (int)$cmd['commande_id'] ?>" class="form-label">Adresse de livraison</label>
                            <input type="text" class="form-control"
                                   id="adresse_<?= (int)$cmd['commande_id'] ?>"
                                   name="adresse_livraison"
                                   autocomplete="street-address"
                                   value="<?= sanitize($cmd['adresse_livraison']) ?>" required>
                        </div>
                        <div class="row g-2">
                            <div class="col-8">
                                <label for="ville_<?= (int)$cmd['commande_id'] ?>" class="form-label">Ville</label>
                                <input type="text" class="form-control"
                                       id="ville_<?= (int)$cmd['commande_id'] ?>"
                                       name="ville_livraison"
                                       autocomplete="address-level2"
                                       value="<?= sanitize($cmd['ville_livraison']) ?>" required>
                            </div>
                            <div class="col-4">
                                <label for="cp_<?= (int)$cmd['commande_id'] ?>" class="form-label">Code postal</label>
                                <input type="text" class="form-control"
                                       id="cp_<?= (int)$cmd['commande_id'] ?>"
                                       name="code_postal_livraison"
                                       inputmode="numeric"
                                       pattern="[0-9]{5}"
                                       autocomplete="postal-code"
                                       value="<?= sanitize($cmd['code_postal_livraison']) ?>" required>
                            </div>
                        </div>
                        <div class="row g-2 mt-1">
                            <div class="col-6">
                                <label for="date_<?= (int)$cmd['commande_id'] ?>" class="form-label">Date de prestation</label>
                                <input type="date" class="form-control"
                                       id="date_<?= (int)$cmd['commande_id'] ?>"
                                       name="date_prestation"
                                       value="<?= sanitize($cmd['date_prestation']) ?>" required>
                            </div>
                            <div class="col-6">
                                <label for="heure_<?= (int)$cmd['commande_id'] ?>" class="form-label">Heure</label>
                                <input type="time" class="form-control"
                                       id="heure_<?= (int)$cmd['commande_id'] ?>"
                                       name="heure_livraison"
                                       value="<?= sanitize($cmd['heure_livraison']) ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-vg">Enregistrer les modifications</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Modal suppression compte -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-labelledby="deleteAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-danger">
                <h5 class="modal-title text-danger" id="deleteAccountModalLabel">Supprimer mon compte</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <p>Cette action est <strong>irréversible</strong>. Toutes vos données personnelles seront supprimées définitivement.</p>
                <p class="text-muted small">Vos commandes passées resteront dans notre système à des fins comptables.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="/mon-compte/supprimer" class="d-flex gap-2">
                    <?= csrfField() ?>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">Confirmer la suppression</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modals avis -->
<?php foreach ($commandes as $cmd): if (!commandeCanReview($cmd['statut'] ?? null)) continue; ?>
<div class="modal fade" id="avisModal<?= (int)$cmd['commande_id'] ?>" tabindex="-1" aria-labelledby="avisModalLabel<?= (int)$cmd['commande_id'] ?>" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="avisModalLabel<?= (int)$cmd['commande_id'] ?>">Votre avis - <?= sanitize($cmd['menu_titre']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form method="POST" action="/avis">
                <?= csrfField() ?>
                <input type="hidden" name="commande_id" value="<?= (int)$cmd['commande_id'] ?>">
                <div class="modal-body">
                    <div id="avis_<?= (int)$cmd['commande_id'] ?>-error" class="alert alert-danger d-none" role="alert"></div>
                    <fieldset class="mb-3">
                        <legend class="form-label fs-6">Note (1 à 5)</legend>
                        <div class="avis-stars-group">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <input class="avis-star-input" type="radio" name="note" id="note<?= (int)$cmd['commande_id'] ?>_<?= $i ?>" value="<?= $i ?>" required>
                                <label class="avis-star-label" for="note<?= (int)$cmd['commande_id'] ?>_<?= $i ?>" title="<?= $i ?> étoile<?= $i > 1 ? 's' : '' ?>">★</label>
                            <?php endfor; ?>
                        </div>
                    </fieldset>
                    <div class="mb-3">
                        <label for="commentaire<?= (int)$cmd['commande_id'] ?>" class="form-label">Commentaire</label>
                        <textarea class="form-control" id="commentaire<?= (int)$cmd['commande_id'] ?>" name="commentaire" rows="3" maxlength="300"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-vg">Envoyer mon avis</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>
