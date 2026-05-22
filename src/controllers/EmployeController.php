<?php
// src/controllers/EmployeController.php

require_once __DIR__ . '/../models/CommandeModel.php';
require_once __DIR__ . '/../models/MenuModel.php';
require_once __DIR__ . '/../services/MailService.php';

class EmployeController
{
    public function dashboard(): void
    {
        if (hasRole('administrateur')) {
            redirect('/admin');
        }

        $commandes = CommandeModel::getAll();
        view('pages/employe/dashboard', compact('commandes'));
    }

    public function commandes(): void
    {
        $filters = [
            'statut' => $_GET['statut'] ?? null,
            'client' => $_GET['client'] ?? null,
        ];
        $commandes = CommandeModel::getAll($filters);
        $statuts   = [
            'en_attente', 'accepte', 'en_preparation',
            'en_cours_livraison', 'livre', 'en_attente_materiel',
            'terminee', 'annulee',
        ];
        view('pages/employe/commandes', compact('commandes', 'filters', 'statuts'));
    }

    public function updateStatut(): void
    {
        verifyCsrf();

        $user        = currentUser();
        $commandeId  = (int)($_POST['commande_id'] ?? 0);
        $statut      = sanitize($_POST['statut']      ?? '');
        $commentaire = sanitize($_POST['commentaire'] ?? '');
        $action      = sanitize($_POST['action']      ?? '');

        $commande = CommandeModel::getById($commandeId);
        if (!$commande) {
            flash('error', 'Commande introuvable.');
            redirect('/employe/commandes');
        }

        $statuts = [
            'en_attente', 'accepte', 'en_preparation',
            'en_cours_livraison', 'livre', 'en_attente_materiel',
            'terminee', 'annulee',
        ];
        if (!in_array($statut, $statuts, true)) {
            flash('error', 'Statut invalide.');
            redirect('/employe/commandes');
        }

        $transitions = [
            'en_attente'            => ['accepte', 'annulee'],
            'accepte'               => ['en_preparation', 'annulee'],
            'en_preparation'        => ['en_cours_livraison', 'annulee'],
            'en_cours_livraison'    => ['livre', 'annulee'],
            'livre'                 => ['en_attente_materiel', 'terminee'],
            'en_attente_materiel'   => ['terminee', 'annulee'],
            'terminee'              => [],
            'annulee'               => [],
        ];
        if ($statut !== $commande['statut'] && !in_array($statut, $transitions[$commande['statut']] ?? [], true)) {
            flash('error', 'Transition de statut non autorisée.');
            redirect('/employe/commandes');
        }

        if ($action === 'annuler' || $statut === 'annulee') {
            $motif       = sanitize($_POST['commentaire']  ?? '');
            $modeContact = sanitize($_POST['mode_contact'] ?? '');
            if (!$motif || !$modeContact) {
                flash('error', 'Le motif et le mode de contact sont obligatoires pour une annulation.');
                redirect('/employe/commandes');
            }
            CommandeModel::cancel($commandeId, $motif, $modeContact, $user['id']);
        } else {
            CommandeModel::updateStatut($commandeId, $statut, $commentaire ?: null, $user['id']);
        }

        $userCommande = \UserModel::findById($commande['utilisateur_id']);
        if ($statut === 'terminee' && $userCommande) {
            MailService::sendCommandeTerminee($userCommande['email'], $commandeId);
        }
        if ($statut === 'en_attente_materiel' && $userCommande) {
            MailService::sendMaterielRelance($userCommande['email'], $userCommande['prenom']);
        }

        flash('success', 'Statut mis à jour.');
        redirect('/employe/commandes');
    }

    public function menus(): void
    {
        $db         = \Database::getConnection();
        $menus      = MenuModel::getAll();
        $themes     = MenuModel::getThemes();
        $regimes    = MenuModel::getRegimes();
        $plats      = $db->query("
            SELECT p.*, cp.libelle AS categorie,
                   GROUP_CONCAT(pa.allergene_id) AS allergene_ids
            FROM plat p
            JOIN categorie_plat cp ON cp.categorie_id = p.categorie_id
            LEFT JOIN plat_allergene pa ON pa.plat_id = p.plat_id
            GROUP BY p.plat_id, p.titre, p.description, p.categorie_id, p.photo_chemin, cp.libelle
            ORDER BY cp.libelle, p.titre
        ")->fetchAll();
        $allergenes = $db->query("SELECT * FROM allergene ORDER BY libelle")->fetchAll();
        $categories = $db->query("SELECT * FROM categorie_plat ORDER BY libelle")->fetchAll();

        $platsByMenu = [];
        $rows = $db->query("SELECT menu_id, plat_id FROM menu_plat")->fetchAll();
        foreach ($rows as $row) {
            $platsByMenu[(int)$row['menu_id']][] = (int)$row['plat_id'];
        }

        view('pages/employe/menus', compact('menus', 'themes', 'regimes', 'plats', 'allergenes', 'categories', 'platsByMenu'));
    }

    public function createMenu(): void
    {
        verifyCsrf();

        $data = [
            'titre'                   => sanitize($_POST['titre']       ?? ''),
            'description'             => sanitize($_POST['description'] ?? ''),
            'nombre_personne_minimum' => (int)($_POST['nombre_personne_minimum'] ?? 2),
            'prix_par_personne'       => (float)($_POST['prix_par_personne']     ?? 0),
            'quantite_restante'       => (isset($_POST['quantite_restante']) && $_POST['quantite_restante'] !== '') ? (int)$_POST['quantite_restante'] : null,
            'conditions'              => sanitize($_POST['conditions'] ?? ''),
            'theme_id'                => $_POST['theme_id']  ?: null,
            'regime_id'               => $_POST['regime_id'] ?: null,
        ];
        if (!$data['titre'] || $data['nombre_personne_minimum'] < 1 || $data['prix_par_personne'] < 0) {
            flash('error', 'Titre, minimum de personnes et prix valides obligatoires.');
            redirect('/employe/menus');
        }

        $menuId = MenuModel::create($data);

        if (!empty($_POST['plats']) && is_array($_POST['plats'])) {
            $db   = \Database::getConnection();
            $stmt = $db->prepare("INSERT IGNORE INTO menu_plat (menu_id, plat_id) VALUES (?, ?)");
            foreach ($_POST['plats'] as $platId) {
                $stmt->execute([$menuId, (int)$platId]);
            }
        }

        if (!empty($_FILES['images']['name'][0])) {
            $db        = \Database::getConnection();
            $uploadDir = __DIR__ . '/../../public/uploads/';
            $ordre     = 1;
            foreach ($_FILES['images']['tmp_name'] as $i => $tmpName) {
                if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $ext = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','webp'])) continue;
                if ($_FILES['images']['size'][$i] > 5 * 1024 * 1024) continue;
                $filename = 'menu_' . $menuId . '_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($tmpName, $uploadDir . $filename)) {
                    $db->prepare("INSERT INTO menu_image (menu_id, chemin, ordre) VALUES (?,?,?)")
                       ->execute([$menuId, 'uploads/' . $filename, $ordre++]);
                }
            }
        }

        flash('success', 'Menu créé avec succès.');
        redirect('/employe/menus');
    }

    public function updateMenu(): void
    {
        verifyCsrf();

        $id   = (int)($_POST['menu_id'] ?? 0);
        $data = [
            'titre'                   => sanitize($_POST['titre']       ?? ''),
            'description'             => sanitize($_POST['description'] ?? ''),
            'nombre_personne_minimum' => (int)($_POST['nombre_personne_minimum'] ?? 2),
            'prix_par_personne'       => (float)($_POST['prix_par_personne']     ?? 0),
            'quantite_restante'       => (isset($_POST['quantite_restante']) && $_POST['quantite_restante'] !== '') ? (int)$_POST['quantite_restante'] : null,
            'conditions'              => sanitize($_POST['conditions'] ?? ''),
            'theme_id'                => $_POST['theme_id']  ?: null,
            'regime_id'               => $_POST['regime_id'] ?: null,
        ];
        if (!$id || !$data['titre'] || $data['nombre_personne_minimum'] < 1 || $data['prix_par_personne'] < 0) {
            flash('error', 'Titre, minimum de personnes et prix valides obligatoires.');
            redirect('/employe/menus');
        }

        MenuModel::update($id, $data);

        $db = \Database::getConnection();
        $db->prepare("DELETE FROM menu_plat WHERE menu_id = ?")->execute([$id]);

        if (!empty($_POST['plats']) && is_array($_POST['plats'])) {
            $stmt = $db->prepare("INSERT IGNORE INTO menu_plat (menu_id, plat_id) VALUES (?, ?)");
            foreach ($_POST['plats'] as $platId) {
                $stmt->execute([$id, (int)$platId]);
            }
        }

        if (!empty($_FILES['images']['name'][0])) {
            $uploadDir = __DIR__ . '/../../public/uploads/';
            $stmtOrdre = $db->prepare("SELECT COALESCE(MAX(ordre),0)+1 FROM menu_image WHERE menu_id=?");
            $stmtOrdre->execute([$id]);
            $ordre = (int)$stmtOrdre->fetchColumn();
            foreach ($_FILES['images']['tmp_name'] as $i => $tmpName) {
                if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $ext = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','webp'])) continue;
                if ($_FILES['images']['size'][$i] > 5 * 1024 * 1024) continue;
                $filename = 'menu_' . $id . '_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($tmpName, $uploadDir . $filename)) {
                    $db->prepare("INSERT INTO menu_image (menu_id, chemin, ordre) VALUES (?,?,?)")
                       ->execute([$id, 'uploads/' . $filename, $ordre++]);
                }
            }
        }

        flash('success', 'Menu modifié avec succès.');
        redirect('/employe/menus');
    }

    public function deleteMenu(): void
    {
        verifyCsrf();
        MenuModel::delete((int)($_POST['menu_id'] ?? 0));
        flash('success', 'Menu supprimé.');
        redirect('/employe/menus');
    }

    public function createPlat(): void
    {
        verifyCsrf();

        $titre       = sanitize($_POST['titre'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $categorieId = (int)($_POST['categorie_id'] ?? 0);
        if (!$titre || !$categorieId) {
            flash('error', 'Titre et catégorie obligatoires.');
            redirect('/employe/menus');
        }

        $db   = \Database::getConnection();
        $stmt = $db->prepare("INSERT INTO plat (titre, description, categorie_id) VALUES (?, ?, ?)");
        $stmt->execute([$titre, $description, $categorieId]);
        $platId = (int)$db->lastInsertId();

        if (!empty($_POST['allergenes']) && is_array($_POST['allergenes'])) {
            $stmtA = $db->prepare("INSERT IGNORE INTO plat_allergene (plat_id, allergene_id) VALUES (?, ?)");
            foreach ($_POST['allergenes'] as $allergeneId) {
                $stmtA->execute([$platId, (int)$allergeneId]);
            }
        }

        if (!empty($_FILES['photo']['tmp_name'])) {
            $uploadDir = __DIR__ . '/../../public/uploads/';
            $ext       = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp']) &&
                $_FILES['photo']['error'] === UPLOAD_ERR_OK &&
                $_FILES['photo']['size'] <= 5 * 1024 * 1024) {
                $filename = 'plat_' . $platId . '_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $filename)) {
                    \Database::getConnection()
                        ->prepare("UPDATE plat SET photo_chemin=? WHERE plat_id=?")
                        ->execute(['uploads/' . $filename, $platId]);
                }
            }
        }

        flash('success', 'Plat créé avec succès.');
        redirect('/employe/menus');
    }

    public function updatePlat(): void
    {
        verifyCsrf();

        $id          = (int)($_POST['plat_id'] ?? 0);
        $titre       = sanitize($_POST['titre'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $categorieId = (int)($_POST['categorie_id'] ?? 0);

        if (!$titre || !$categorieId) {
            flash('error', 'Titre et catégorie obligatoires.');
            redirect('/employe/menus');
        }

        \Database::getConnection()
            ->prepare("UPDATE plat SET titre=?, description=?, categorie_id=? WHERE plat_id=?")
            ->execute([$titre, $description, $categorieId, $id]);

        $db = \Database::getConnection();
        $db->prepare("DELETE FROM plat_allergene WHERE plat_id = ?")->execute([$id]);
        if (!empty($_POST['allergenes']) && is_array($_POST['allergenes'])) {
            $stmtA = $db->prepare("INSERT IGNORE INTO plat_allergene (plat_id, allergene_id) VALUES (?, ?)");
            foreach ($_POST['allergenes'] as $allergeneId) {
                $stmtA->execute([$id, (int)$allergeneId]);
            }
        }

        if (!empty($_FILES['photo']['tmp_name'])) {
            $uploadDir = __DIR__ . '/../../public/uploads/';
            $ext       = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp'], true) &&
                $_FILES['photo']['error'] === UPLOAD_ERR_OK &&
                $_FILES['photo']['size'] <= 5 * 1024 * 1024) {
                $filename = 'plat_' . $id . '_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $filename)) {
                    $db->prepare("UPDATE plat SET photo_chemin=? WHERE plat_id=?")
                       ->execute(['uploads/' . $filename, $id]);
                }
            }
        }

        flash('success', 'Plat modifié.');
        redirect('/employe/menus');
    }

    public function deletePlat(): void
    {
        verifyCsrf();
        $db = \Database::getConnection();
        $platId = (int)($_POST['plat_id'] ?? 0);
        $stmt = $db->prepare("SELECT COUNT(*) FROM menu_plat WHERE plat_id = ?");
        $stmt->execute([$platId]);
        if ((int)$stmt->fetchColumn() > 0) {
            flash('error', 'Impossible de supprimer un plat utilisé dans un menu. Retirez-le d\'abord des menus concernés.');
            redirect('/employe/menus');
        }

        $db
            ->prepare("DELETE FROM plat WHERE plat_id = ?")
            ->execute([$platId]);
        flash('success', 'Plat supprimé.');
        redirect('/employe/menus');
    }

    public function deleteMenuImage(): void
    {
        verifyCsrf();
        $imageId = (int)($_POST['image_id'] ?? 0);
        $db      = \Database::getConnection();
        $stmt    = $db->prepare("SELECT chemin FROM menu_image WHERE image_id=?");
        $stmt->execute([$imageId]);
        $row = $stmt->fetch();
        if ($row) {
            $path = __DIR__ . '/../../public/' . $row['chemin'];
            if (file_exists($path)) unlink($path);
            $db->prepare("DELETE FROM menu_image WHERE image_id=?")->execute([$imageId]);
        }
        flash('success', 'Image supprimée.');
        redirect('/employe/menus');
    }

    public function avis(): void
    {
        $db   = \Database::getConnection();
        $avis = $db->query("
            SELECT a.*, u.prenom, u.nom, m.titre AS menu_titre
            FROM avis a
            JOIN utilisateur u ON u.utilisateur_id = a.utilisateur_id
            JOIN commande c    ON c.commande_id    = a.commande_id
            JOIN menu m        ON m.menu_id        = c.menu_id
            WHERE a.statut = 'en_attente'
            ORDER BY a.created_at ASC
        ")->fetchAll();

        view('pages/employe/avis', compact('avis'));
    }

    public function validerAvis(): void
    {
        verifyCsrf();
        $commandeId = (int)($_POST['commande_id'] ?? 0);
        $action     = sanitize($_POST['action']   ?? '');
        $statut     = ($action === 'valider') ? 'valide' : 'refuse';

        \Database::getConnection()
            ->prepare("UPDATE avis SET statut = ? WHERE commande_id = ?")
            ->execute([$statut, $commandeId]);

        flash('success', 'Avis ' . ($statut === 'valide' ? 'validé' : 'refusé') . '.');
        redirect('/employe/avis');
    }

    public function horaires(): void
    {
        $db       = \Database::getConnection();
        $horaires = $db->query("SELECT * FROM horaire ORDER BY horaire_id")->fetchAll();
        view('pages/employe/horaires', compact('horaires'));
    }

    public function updateHoraires(): void
    {
        verifyCsrf();

        $db   = \Database::getConnection();
        $stmt = $db->prepare("
            UPDATE horaire SET heure_ouverture = ?, heure_fermeture = ?
            WHERE horaire_id = ?
        ");

        foreach (($_POST['horaires'] ?? []) as $id => $h) {
            $stmt->execute([
                sanitize($h['ouverture'] ?? ''),
                sanitize($h['fermeture'] ?? ''),
                (int)$id,
            ]);
        }

        flash('success', 'Horaires mis à jour.');
        redirect('/employe/horaires');
    }
}
