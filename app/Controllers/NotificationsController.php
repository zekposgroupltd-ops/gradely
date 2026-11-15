<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Auth, DB, View};

final class NotificationsController extends BaseController
{
    public function index(): void
    {
        $uid = (int) Auth::user()['id'];
        $rows = DB::all('SELECT id, title, body, read_at, created_at FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 100', [$uid]);
        View::render('account/notifications', ['title' => 'Notifications', 'rows' => $rows]);
    }

    public function markAllRead(): void
    {
        DB::run('UPDATE notifications SET read_at = ? WHERE user_id = ? AND read_at IS NULL', [date('Y-m-d H:i:s'), (int) Auth::user()['id']]);
        $this->ok('All notifications marked as read.', '/notifications');
    }
}
