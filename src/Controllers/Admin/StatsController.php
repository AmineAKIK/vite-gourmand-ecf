<?php

namespace App\Controllers\Admin;

use App\Models\MenuModel;
use App\Services\PricingService;
use App\Services\StatsService;

class StatsController
{
    public function stats(): void
    {
        $menuFilter = (int)($_GET['menu_id']    ?? 0);
        $dateDebut  = sanitize($_GET['date_debut'] ?? '');
        $dateFin    = sanitize($_GET['date_fin']   ?? '');

        $caStats   = StatsService::getCaParMenu($menuFilter, $dateDebut, $dateFin);
        $synthese  = StatsService::getSynthese($dateDebut, $dateFin);
        $caMensuel = StatsService::getCaMensuel(24, $dateDebut, $dateFin);
        $menus     = MenuModel::getAll();
        $regimeTva = PricingService::regimeTva();

        view('pages/admin/stats', compact(
            'caStats', 'synthese', 'caMensuel', 'menus', 'regimeTva',
            'menuFilter', 'dateDebut', 'dateFin'
        ));
    }

    public function exportStats(): void
    {
        $dateDebut = sanitize($_GET['date_debut'] ?? '');
        $dateFin   = sanitize($_GET['date_fin']   ?? '');
        $rows      = StatsService::getExportRows($dateDebut, $dateFin);

        $filename = 'ca_vite_gourmand_' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [
            'N° commande', 'Date comptabilisation', 'Date prestation',
            'Ville', 'Client', 'Email client', 'Nb personnes',
            'Total HT', 'TVA', 'Total TTC',
            'Encaissé', 'Solde restant', 'Statut paiement', 'Statut commande',
        ], ';');
        foreach ($rows as $row) {
            fputcsv($out, [
                $row['numero_commande'],
                $row['date_comptabilisation'],
                $row['date_prestation'],
                $row['ville_livraison'],
                $row['client'],
                $row['client_email'],
                $row['nb_personnes'],
                number_format((float)$row['total_ht'],         2, ',', ''),
                number_format((float)$row['total_tva'],        2, ',', ''),
                number_format((float)$row['total_ttc'],        2, ',', ''),
                number_format((float)$row['montant_encaisse'], 2, ',', ''),
                number_format((float)$row['solde_restant'],    2, ',', ''),
                $row['statut_paiement'],
                $row['statut'],
            ], ';');
        }
        fclose($out);
        exit;
    }

    public function comptabilite(): void
    {
        $config    = \App\Models\SiteConfigModel::getAll();
        $regimeTva = PricingService::regimeTva();
        $synthese  = StatsService::getSynthese();
        $pageTitle = buildPageTitle('Comptabilité');
        view('pages/admin/comptabilite', compact('config', 'regimeTva', 'synthese', 'pageTitle'));
    }

    public function exportComptabilite(): void
    {
        $format    = sanitize($_GET['format']     ?? 'commandes');
        $dateDebut = sanitize($_GET['date_debut'] ?? '');
        $dateFin   = sanitize($_GET['date_fin']   ?? '');
        $regimeTva   = PricingService::regimeTva();
        $isAssujetti = $regimeTva === 'assujetti';

        $periodSuffix = '';
        if ($dateDebut && $dateFin)   { $periodSuffix = '_' . $dateDebut . '_' . $dateFin; }
        elseif ($dateDebut)           { $periodSuffix = '_depuis_' . $dateDebut; }
        elseif ($dateFin)             { $periodSuffix = '_jusqu_' . $dateFin; }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");

        switch ($format) {

            case 'lignes':
                header('Content-Disposition: attachment; filename="journal_lignes_vg' . $periodSuffix . '.csv"');
                $headers = [
                    'N° commande', 'Date comptabilisation', 'Date prestation',
                    'Ville', 'Client', 'Email', 'Menu', 'Personnes',
                    'Prix brut', 'Remise', 'Net menu', 'Livraison', 'Total TTC',
                ];
                if ($isAssujetti) { $headers[] = 'TVA %'; $headers[] = 'Total HT'; $headers[] = 'TVA'; }
                fputcsv($out, $headers, ';');
                foreach (StatsService::getExportLignes($dateDebut, $dateFin) as $r) {
                    $row = [
                        $r['numero_commande'], $r['date_comptabilisation'], $r['date_prestation'],
                        $r['ville_livraison'], $r['client'], $r['client_email'],
                        $r['menu_titre'], $r['nombre_personne'],
                        number_format((float)$r['prix_brut_menu'],  2, ',', ''),
                        number_format((float)$r['remise'],          2, ',', ''),
                        number_format((float)$r['prix_net_menu'],   2, ',', ''),
                        number_format((float)$r['frais_livraison'], 2, ',', ''),
                        number_format((float)$r['total_ligne_ttc'], 2, ',', ''),
                    ];
                    if ($isAssujetti) {
                        $row[] = number_format((float)$r['taux_tva'],       2, ',', '');
                        $row[] = number_format((float)$r['total_ligne_ht'], 2, ',', '');
                        $row[] = number_format((float)$r['tva_ligne'],      2, ',', '');
                    }
                    fputcsv($out, $row, ';');
                }
                break;

            case 'mensuel':
                header('Content-Disposition: attachment; filename="ca_mensuel_vg' . $periodSuffix . '.csv"');
                $headers = ['Mois', 'Commandes', 'Personnes', 'Panier moyen TTC', 'CA TTC'];
                if ($isAssujetti) { $headers[] = 'CA HT'; $headers[] = 'TVA collectée'; }
                fputcsv($out, $headers, ';');
                foreach (StatsService::getExportMensuel($dateDebut, $dateFin) as $r) {
                    $row = [
                        $r['annee_mois'], $r['nb_commandes'], $r['nb_personnes'],
                        number_format((float)$r['panier_moyen_ttc'], 2, ',', ''),
                        number_format((float)$r['ca_ttc'],           2, ',', ''),
                    ];
                    if ($isAssujetti) {
                        $row[] = number_format((float)$r['ca_ht'],         2, ',', '');
                        $row[] = number_format((float)$r['tva_collectee'], 2, ',', '');
                    }
                    fputcsv($out, $row, ';');
                }
                break;

            default: // 'commandes'
                header('Content-Disposition: attachment; filename="journal_commandes_vg' . $periodSuffix . '.csv"');
                $headers = [
                    'N° commande', 'Date comptabilisation', 'Date prestation',
                    'Ville', 'Client', 'Email', 'Personnes', 'Total TTC',
                ];
                if ($isAssujetti) { $headers[] = 'Total HT'; $headers[] = 'TVA'; }
                array_push($headers, 'Encaissé', 'Solde restant', 'Statut paiement', 'Statut commande');
                fputcsv($out, $headers, ';');
                foreach (StatsService::getExportRows($dateDebut, $dateFin) as $r) {
                    $row = [
                        $r['numero_commande'], $r['date_comptabilisation'], $r['date_prestation'],
                        $r['ville_livraison'], $r['client'], $r['client_email'],
                        $r['nb_personnes'],
                        number_format((float)$r['total_ttc'], 2, ',', ''),
                    ];
                    if ($isAssujetti) {
                        $row[] = number_format((float)$r['total_ht'],  2, ',', '');
                        $row[] = number_format((float)$r['total_tva'], 2, ',', '');
                    }
                    array_push($row,
                        number_format((float)$r['montant_encaisse'], 2, ',', ''),
                        number_format((float)$r['solde_restant'],    2, ',', ''),
                        $r['statut_paiement'],
                        $r['statut']
                    );
                    fputcsv($out, $row, ';');
                }
                break;
        }

        fclose($out);
        exit;
    }
}
