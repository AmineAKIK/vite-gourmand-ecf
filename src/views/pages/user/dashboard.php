<?php $pageTitle = 'Mon compte - Vite & Gourmand'; ?>
<div class="container py-5">
    <h1 class="mb-4">Bonjour, <?= sanitize($userFull['prenom']) ?> !</h1>

    <div class="row g-4">
        <!-- Mes commandes -->
        <div class="col-lg-8">
            <h2 class="h4 mb-3">Mes commandes</h2>
            <?php if (empty($commandes)): ?>
                <div class="alert alert-info">
                    Vous n'avez pas encore de commande.
                    <a href="/menus" class="alert-link">Découvrir nos menus</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle" aria-label="Mes commandes">
                        <thead class="table-light">
                            <tr>
                                <th>N°</th><th>Menu</th><th>Date prestation</th><th>Adresse</th><th>Total</th><th>Statut</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($commandes as $cmd): ?>
                            <tr>
                                <td><small><?= sanitize($cmd['numero_commande']) ?></small></td>
                                <td><?= sanitize($cmd['menu_titre']) ?></td>
                                <td><?= date('d/m/Y', strtotime($cmd['date_prestation'])) ?></td>
                                <td><?= sanitize($cmd['adresse_livraison'] . ', ' . $cmd['ville_livraison']) ?></td>
                                <td><strong><?= number_format($cmd['prix_total'], 2) ?> €</strong></td>
                                <td><span class="badge-statut statut-<?= sanitize($cmd['statut']) ?>"><?= sanitize(str_replace('_',' ',$cmd['statut'])) ?></span></td>
                                <td>
                                    <?php if ($cmd['statut'] === 'en_attente'): ?>
                                        <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#modifModal<?= (int)$cmd['commande_id'] ?>">Modifier</button>
                                        <form method="POST" action="/commande/annuler" class="d-inline" onsubmit="return confirm('Confirmer l\'annulation ?')">
                                            <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                                            <input type="hidden" name="commande_id" value="<?= (int)$cmd['commande_id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Annuler</button>
                                        </form>
                                    <?php elseif (in_array($cmd['statut'], ['accepte','en_preparation','en_cours_livraison','livre','en_attente_materiel'])): ?>
                                        <a href="/commande/suivi?id=<?= (int)$cmd['commande_id'] ?>" class="btn btn-sm btn-outline-info">Suivi</a>
                                    <?php elseif ($cmd['statut'] === 'terminee'): ?>
                                        <button class="btn btn-sm btn-or" data-bs-toggle="modal" data-bs-target="#avisModal<?= (int)$cmd['commande_id'] ?>">
                                            <i class="bi bi-star me-1"></i>Donner un avis
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Mes infos -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm p-4">
                <h2 class="h5 mb-3">Mes informations</h2>
                <form method="POST" action="/mon-compte/modifier">
                    <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
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
            </div>
        </div>
    </div>

    <?php foreach ($commandes as $cmd): if ($cmd['statut'] !== 'en_attente') continue; ?>
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
                    <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                    <input type="hidden" name="commande_id" value="<?= (int)$cmd['commande_id'] ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="adresse_<?= (int)$cmd['commande_id'] ?>" class="form-label">Adresse de livraison</label>
                            <input type="text" class="form-control"
                                   id="adresse_<?= (int)$cmd['commande_id'] ?>"
                                   name="adresse_livraison"
                                   value="<?= sanitize($cmd['adresse_livraison']) ?>" required>
                        </div>
                        <div class="row g-2">
                            <div class="col-8">
                                <label for="ville_<?= (int)$cmd['commande_id'] ?>" class="form-label">Ville</label>
                                <input type="text" class="form-control"
                                       id="ville_<?= (int)$cmd['commande_id'] ?>"
                                       name="ville_livraison"
                                       value="<?= sanitize($cmd['ville_livraison']) ?>" required>
                            </div>
                            <div class="col-4">
                                <label for="cp_<?= (int)$cmd['commande_id'] ?>" class="form-label">Code postal</label>
                                <input type="text" class="form-control"
                                       id="cp_<?= (int)$cmd['commande_id'] ?>"
                                       name="code_postal_livraison"
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
                        <div class="mt-2">
                            <label for="nbpers_<?= (int)$cmd['commande_id'] ?>" class="form-label">Nombre de personnes</label>
                            <input type="number" class="form-control"
                                   id="nbpers_<?= (int)$cmd['commande_id'] ?>"
                                   name="nombre_personne"
                                   value="<?= (int)$cmd['nombre_personne'] ?>" min="1" required>
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

<!-- Modals avis -->
<?php foreach ($commandes as $cmd): if ($cmd['statut'] !== 'terminee') continue; ?>
<div class="modal fade" id="avisModal<?= (int)$cmd['commande_id'] ?>" tabindex="-1" aria-labelledby="avisModalLabel<?= (int)$cmd['commande_id'] ?>" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="avisModalLabel<?= (int)$cmd['commande_id'] ?>">Votre avis - <?= sanitize($cmd['menu_titre']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form method="POST" action="/avis">
                <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                <input type="hidden" name="commande_id" value="<?= (int)$cmd['commande_id'] ?>">
                <div class="modal-body">
                    <fieldset class="mb-3">
                        <legend class="form-label fs-6">Note (1 à 5)</legend>
                        <div class="d-flex gap-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="note" id="note<?= (int)$cmd['commande_id'] ?>_<?= $i ?>" value="<?= $i ?>" required>
                                    <label class="form-check-label stars" for="note<?= (int)$cmd['commande_id'] ?>_<?= $i ?>"><?= str_repeat('★', $i) ?></label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </fieldset>
                    <div class="mb-3">
                        <label for="commentaire<?= (int)$cmd['commande_id'] ?>" class="form-label">Commentaire</label>
                        <textarea class="form-control" id="commentaire<?= (int)$cmd['commande_id'] ?>" name="commentaire" rows="3" maxlength="500"></textarea>
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
