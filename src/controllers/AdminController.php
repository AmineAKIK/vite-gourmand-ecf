<?php
// src/controllers/AdminController.php

require_once __DIR__ . '/../models/CommandeModel.php';
require_once __DIR__ . '/../services/MailService.php';

class AdminController {

    public function dashboard(): void {
        $commandes = CommandeModel::getAll();
        $stats     = CommandeModel::getStatsByMenu();
        $mongoStats = $this->getMongoStatsByMenu();
        view('pages/admin/dashboard', compact('commandes', 'stats', 'mongoStats'));
    }

    public function employes(): void {
        $employes = \UserModel::getAllEmployes();
        view('pages/admin/employes', compact('employes'));
    }

    public function createEmploye(): void {
        verifyCsrf();
        $email    = sanitize($_POST['email'] ?? '');
        $prenom   = sanitize($_POST['prenom'] ?? '');
        $nom      = sanitize($_POST['nom'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Email invalide.'); redirect('/admin/employes');
        }
        if (!$prenom || !$nom) {
            flash('error', 'Prénom et nom obligatoires.'); redirect('/admin/employes');
        }
        if (!validatePassword($password)) {
            flash('error', 'Mot de passe trop faible (10 car. min, 1 maj, 1 min, 1 chiffre, 1 spécial).');
            redirect('/admin/employes');
        }
        if (\UserModel::findByEmail($email)) {
            flash('error', 'Email déjà utilisé.'); redirect('/admin/employes');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        \UserModel::createEmploye($email, $hash, $prenom, $nom);
        MailService::sendEmployeCreation($email);

        flash('success', 'Compte employé créé. Le mot de passe doit être communiqué manuellement.');
        redirect('/admin/employes');
    }

    public function disableEmploye(): void {
        verifyCsrf();
        $id    = (int)($_POST['employe_id'] ?? 0);
        $actif = (int)($_POST['actif'] ?? 0);
        \UserModel::setActif($id, (bool)$actif);
        flash('success', 'Compte ' . ($actif ? 'réactivé' : 'désactivé') . '.');
        redirect('/admin/employes');
    }

    public function stats(): void {
        // Stats CA depuis MySQL
        $db          = \Database::getConnection();

        // Filtres CA
        $menuFilter = (int)($_GET['menu_id'] ?? 0);
        $dateDebut  = sanitize($_GET['date_debut'] ?? '');
        $dateFin    = sanitize($_GET['date_fin'] ?? '');

        $sql    = "SELECT m.menu_id, m.titre, SUM(c.prix_total) AS ca, COUNT(c.commande_id) AS nb
                   FROM commande c JOIN menu m ON m.menu_id = c.menu_id
                   WHERE c.statut != 'annulee'";
        $params = [];
        if ($menuFilter) { $sql .= " AND c.menu_id = ?";       $params[] = $menuFilter; }
        if ($dateDebut)  { $sql .= " AND c.date_commande >= ?"; $params[] = $dateDebut; }
        if ($dateFin)    { $sql .= " AND c.date_commande <= ?"; $params[] = $dateFin . ' 23:59:59'; }
        $sql .= " GROUP BY c.menu_id ORDER BY ca DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $caStats = $stmt->fetchAll();

        // Stats MongoDB pour le graphique
        $mongoStats = $this->getMongoStatsByMenu();

        $menus = \MenuModel::getAll();
        view('pages/admin/stats', compact(
            'caStats', 'menus',
            'menuFilter', 'dateDebut', 'dateFin', 'mongoStats'
        ));
    }

    private function getMongoStatsByMenu(): array {
        $mongoStats = [];
        try {
            $mongoUri = defined('MONGO_URI') ? MONGO_URI : ($_ENV['MONGO_URI'] ?? null);
            if ($mongoUri && $mongoUri !== 'mongodb+srv://user:pass@cluster.mongodb.net' && class_exists(\MongoDB\Client::class)) {
                $client     = new \MongoDB\Client($mongoUri);
                $collection = $client->selectCollection(MONGO_DB, 'commandes_stats');
                $this->syncMongoStatsFromMysql($collection);
                $cursor     = $collection->aggregate([
                    ['$group' => ['_id' => '$menu_titre', 'total' => ['$sum' => 1]]],
                    ['$sort' => ['total' => -1]],
                ]);
                foreach ($cursor as $doc) {
                    $mongoStats[] = [
                        'titre'        => (string)$doc['_id'],
                        'nb_commandes' => (int)$doc['total'],
                    ];
                }
            }
        } catch (\Throwable $e) {
            error_log('MongoDB stats failed: ' . $e->getMessage());
        }
        return $mongoStats;
    }

    private function syncMongoStatsFromMysql($collection): void {
        $rows = \Database::getConnection()->query("
            SELECT c.commande_id, c.menu_id, m.titre AS menu_titre, c.prix_total,
                   c.nombre_personne, c.date_commande
            FROM commande c
            JOIN menu m ON m.menu_id = c.menu_id
            WHERE c.statut != 'annulee'
        ")->fetchAll();

        foreach ($rows as $row) {
            $createdAt = !empty($row['date_commande']) ? strtotime($row['date_commande']) : time();
            $collection->updateOne(
                ['commande_id' => (int)$row['commande_id']],
                ['$setOnInsert' => [
                    'commande_id'  => (int)$row['commande_id'],
                    'menu_id'      => (int)$row['menu_id'],
                    'menu_titre'   => $row['menu_titre'],
                    'prix_total'   => (float)$row['prix_total'],
                    'nb_personnes' => (int)$row['nombre_personne'],
                    'created_at'   => new \MongoDB\BSON\UTCDateTime($createdAt * 1000),
                ]],
                ['upsert' => true]
            );
        }
    }
}
