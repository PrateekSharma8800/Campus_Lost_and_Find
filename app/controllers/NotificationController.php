<?php
declare(strict_types=1);

/** Notification centre: list, open (mark read + go to link), mark all read. */
final class NotificationController extends Controller
{
    /** GET /notifications?filter=unread */
    public function index(): void
    {
        $onlyUnread = $this->query('filter') === 'unread';
        $this->view('notifications/index', [
            'title'         => 'Notifications',
            'notifications' => Notification::forUser(Auth::id(), $onlyUnread),
            'onlyUnread'    => $onlyUnread,
            'unreadCount'   => Notification::unreadCount(Auth::id()),
        ]);
    }

    /** POST /notifications/{id}/open */
    public function open(string $id): void
    {
        $notification = ctype_digit($id) ? Notification::findForUser((int) $id, Auth::id()) : null;
        if ($notification === null) {
            abort(404);
        }
        Notification::markRead((int) $notification['id'], Auth::id());
        // safe_redirect_path() only allows links inside CampusFind.
        redirect(safe_redirect_path($notification['link'], '/notifications'));
    }

    /** POST /notifications/read-all */
    public function readAll(): void
    {
        Notification::markAllRead(Auth::id());
        Session::flash('success', 'All notifications marked as read.');
        redirect('/notifications');
    }
}
