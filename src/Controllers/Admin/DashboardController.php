<?php

namespace App\Controllers\Admin;

use App\Domain\OrderStatus;
use App\Models\AvisModel;
use App\Models\CommandeModel;
use App\Models\MenuModel;
use App\Services\StatsService;

class DashboardController
{
    public function index(): void
    {
        $toutesCommandes    = CommandeModel::getAll();
        $commandesEnAttente = CommandeModel::getAll(['statut' => 'en_attente']);
        $avisEnAttente      = AvisModel::getPending();
        $menusActifs        = MenuModel::getAll();
        $statsParMenu       = StatsService::getCaParMenu();

        $today        = date('Y-m-d');
        $lundiSemaine = date('Y-m-d', strtotime('monday this week'));
        $commandesAujourdhui = array_filter($toutesCommandes, fn($c) => str_starts_with($c['date_commande'] ?? '', $today));
        $commandesSemaine    = array_filter($toutesCommandes, fn($c) => ($c['date_commande'] ?? '') >= $lundiSemaine);
        $caSemaine = array_sum(array_map(
            fn($c) => OrderStatus::countsTowardRevenue($c['statut'] ?? null) ? (float)($c['prix_total'] ?? 0) : 0,
            array_filter(
                $toutesCommandes,
                fn($c) => ($c['date_acceptation'] ?? $c['date_commande'] ?? '') >= $lundiSemaine
            )
        ));
        $activiteRecente = array_slice(CommandeModel::getAll(['tri' => 'date_prestation_desc']), 0, 5);

        view('pages/admin/dashboard', compact(
            'commandesEnAttente', 'avisEnAttente',
            'commandesAujourdhui', 'commandesSemaine', 'caSemaine',
            'activiteRecente', 'menusActifs', 'statsParMenu'
        ));
    }
}
