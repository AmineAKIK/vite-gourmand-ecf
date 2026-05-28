<?php

namespace App\Controllers\Workspace;

use App\Models\NotificationModel;

class NotificationController
{
    public function index(): void
    {
        $user          = currentUser();
        $notifications = NotificationModel::getAll((int)$user['id']);
        NotificationModel::markAllRead((int)$user['id']);

        $pageTitle = buildPageTitle('Notifications');
        view('pages/employe/notifications', compact('notifications', 'pageTitle'));
    }

    public function count(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $user  = currentUser();
        $count = NotificationModel::countUnread((int)$user['id']);
        echo json_encode(['count' => $count]);
        exit;
    }

    public function markRead(): void
    {
        verifyCsrf();
        $user           = currentUser();
        $notificationId = (int)($_POST['notification_id'] ?? 0);
        if ($notificationId) {
            NotificationModel::markRead($notificationId, (int)$user['id']);
        } else {
            NotificationModel::markAllRead((int)$user['id']);
        }
        if (!empty($_POST['redirect'])) {
            redirect(sanitize($_POST['redirect']));
        }
        redirect('/employe/notifications');
    }
}
