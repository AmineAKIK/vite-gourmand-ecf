<?php

namespace App\Controllers\Workspace;

use App\Models\HoraireModel;

class HoraireController
{
    public function index(): void
    {
        $horaires = HoraireModel::getAll();
        view('pages/employe/horaires', compact('horaires'));
    }

    public function update(): void
    {
        verifyCsrf();

        HoraireModel::updateMany($_POST['horaires'] ?? []);

        flash('success', 'Horaires mis à jour.');
        redirect('/employe/horaires');
    }
}
