<?php
namespace JanKavach\Controllers;

use JanKavach\Core\Audit;
use JanKavach\Core\Auth;
use JanKavach\Core\Controller;
use JanKavach\Core\Flash;
use JanKavach\Libraries\Notifier;

/**
 * In-app notifications (specification section 36).
 */
class NotificationController extends Controller
{
    public function index(): void
    {
        $unreadOnly = $this->request->bool('unread');

        $this->view('notifications/index', [
            'pageTitle'     => 'Notifications',
            'breadcrumbs'   => [['label' => 'Notifications']],
            'notifications' => Notifier::forUser(Auth::id(), 100, $unreadOnly),
            'unreadOnly'    => $unreadOnly,
            'unreadCount'   => Notifier::unreadCount(Auth::id()),
        ]);
    }

    /** Header dropdown feed. */
    public function dropdown(): void
    {
        $notifications = Notifier::forUser(Auth::id(), 10, false);

        $this->jsonSuccess([
            'unread' => Notifier::unreadCount(Auth::id()),
            'items'  => array_map(fn($n) => [
                'id'      => (int)$n['id'],
                'title'   => $n['title'],
                'message' => $n['message'],
                'icon'    => $n['icon'],
                'priority'=> $n['priority'],
                'link'    => $n['link'] ? url($n['link']) : null,
                'seen'    => (int)$n['seen'] === 1,
                'ago'     => time_ago($n['created_at']),
            ], $notifications),
        ]);
    }

    public function markRead(string $id): void
    {
        Notifier::markRead((int)$id, Auth::id());

        if ($this->request->isAjax()) {
            $this->jsonSuccess(['unread' => Notifier::unreadCount(Auth::id())], 'Marked as read.');
        }
        $this->back('/notifications');
    }

    public function markAllRead(): void
    {
        $count = Notifier::markAllRead(Auth::id());

        if ($this->request->isAjax()) {
            $this->jsonSuccess(['unread' => 0, 'marked' => $count], $count . ' notification(s) marked as read.');
        }

        Flash::success($count . ' notification(s) marked as read.');
        $this->back('/notifications');
    }

    /**
     * Sweep for follow-ups, pending documents and stalled applications and
     * raise notifications. In production this would be driven by a scheduled
     * task; the button exists so the behaviour can be triggered and reviewed.
     */
    public function generate(): void
    {
        $created = Notifier::generateSystemNotifications();

        Audit::log('Notifications', 'Created', [
            'entity_type' => 'Notification',
            'description' => $created . ' system notification(s) generated',
        ]);

        if ($this->request->isAjax()) {
            $this->jsonSuccess(['created' => $created], $created . ' notification(s) generated.');
        }

        Flash::success($created > 0
            ? $created . ' notification(s) generated from follow-ups, pending documents and stalled applications.'
            : 'Nothing new needed a notification - everything is already up to date for today.');
        $this->back('/notifications');
    }
}
