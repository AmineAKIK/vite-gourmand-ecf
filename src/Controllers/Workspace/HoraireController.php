<?php

namespace App\Controllers\Workspace;

use App\Models\HoraireModel;

class HoraireController
{
    public function index(): void
    {
        \App\Core\View::redirect('/admin/parametres?tab=horaires');
    }

    public function update(): void
    {
        verifyCsrf();

        HoraireModel::updateMany($_POST['horaires'] ?? []);

        flash('success', 'Horaires mis à jour.');
        redirect('/admin/parametres#horaires');
    }
}
