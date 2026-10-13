<?php
namespace JanKavach\Libraries;

use JanKavach\Core\Auth;
use JanKavach\Core\Config;
use JanKavach\Core\Database;
use JanKavach\Core\Logger;

/**
 * NOTIFICATIONS  (specification section 36)
 *
 * Notifications are written to one table and read back per user, filtered by
 * role and geography.  A notification may be addressed to a single user, or
 * broadcast to a role within a State/District.
 *
 * SMS, WhatsApp and email are not wired up - deliberately.  Each row carries
 * channel flags and a dispatch_status so a future queue worker can pick up
 * `dispatch_status = 'Pending'` rows without any schema change.  Enable the
 * channels under Settings once a gateway is available.
 */
class Notifier
{
    public const FOLLOWUP_DUE        = 'followup_due';
    public const FOLLOWUP_OVERDUE    = 'followup_overdue';
    public const DOCUMENT_PENDING    = 'document_pending';
    public const APPLICATION_PENDING = 'application_pending';
    public const APPLICATION_APPROVED = 'application_approved';
    public const APPLICATION_REJECTED = 'application_rejected';
    public const SCHEME_ADDED        = 'scheme_added';
    public const SCHEME_UPDATED      = 'scheme_updated';
    public const BENEFICIARY_MATCHED = 'beneficiary_matched';

    private const META = [
        self::FOLLOWUP_DUE         => ['icon' => 'bi-calendar-check',    'priority' => 'Normal'],
        self::FOLLOWUP_OVERDUE     => ['icon' => 'bi-calendar-x',        'priority' => 'High'],
        self::DOCUMENT_PENDING     => ['icon' => 'bi-file-earmark-x',    'priority' => 'Normal'],
        self::APPLICATION_PENDING  => ['icon' => 'bi-hourglass-split',   'priority' => 'Normal'],
        self::APPLICATION_APPROVED => ['icon' => 'bi-check-circle',      'priority' => 'Normal'],
        self::APPLICATION_REJECTED => ['icon' => 'bi-x-circle',          'priority' => 'High'],
        self::SCHEME_ADDED         => ['icon' => 'bi-plus-circle',       'priority' => 'Low'],
        self::SCHEME_UPDATED       => ['icon' => 'bi-pencil-square',     'priority' => 'Low'],
        self::BENEFICIARY_MATCHED  => ['icon' => 'bi-people',            'priority' => 'Normal'],
    ];

    // =================================================================
    // WRITING
    // =================================================================

    /**
     * Notifier::send(self::FOLLOWUP_DUE, 'Follow-up due today', [
     *     'message' => '...', 'link' => '/followups', 'user_id' => 12,
     * ]);
     */
    public static function send(string $type, string $title, array $options = []): ?int
    {
        try {
            $meta = self::META[$type] ?? ['icon' => 'bi-bell', 'priority' => 'Normal'];
            $channels = Config::get('notifications.channels', []);

            $email    = !empty($options['email'])    && !empty($channels['email']);
            $sms      = !empty($options['sms'])      && !empty($channels['sms']);
            $whatsapp = !empty($options['whatsapp']) && !empty($channels['whatsapp']);

            return Database::getInstance()->insert('notifications', [
                'user_id'     => $options['user_id']     ?? null,
                'role_id'     => $options['role_id']     ?? null,
                'state_id'    => $options['state_id']    ?? null,
                'district_id' => $options['district_id'] ?? null,
                'type'        => $type,
                'title'       => mb_substr($title, 0, 200),
                'message'     => isset($options['message']) ? mb_substr((string)$options['message'], 0, 500) : null,
                'link'        => $options['link'] ?? null,
                'icon'        => $options['icon'] ?? $meta['icon'],
                'priority'    => $options['priority'] ?? $meta['priority'],
                'entity_type' => $options['entity_type'] ?? null,
                'entity_id'   => isset($options['entity_id']) ? (int)$options['entity_id'] : null,
                'channel_inapp'    => 1,
                'channel_email'    => $email ? 1 : 0,
                'channel_sms'      => $sms ? 1 : 0,
                'channel_whatsapp' => $whatsapp ? 1 : 0,
                // Anything destined for an external channel waits in 'Pending'
                // for the (future) dispatch worker to collect.
                'dispatch_status'  => ($email || $sms || $whatsapp) ? 'Pending' : 'Not Required',
            ]);
        } catch (\Throwable $e) {
            Logger::error('Notification write failed: ' . $e->getMessage(), ['type' => $type]);
            return null;
        }
    }

    /**
     * Sweep the database for anything needing attention and raise
     * notifications for it.  Idempotent for the current day: a duplicate for
     * the same type + entity on the same date is skipped.
     */
    public static function generateSystemNotifications(): int
    {
        $db = Database::getInstance();
        $created = 0;
        $today = date('Y-m-d');
        $window = (int)(Config::setting('upcoming_window_days')
                   ?? Config::get('notifications.followup_due_days', 3));

        // ---- overdue follow-ups ---------------------------------------
        $rows = $db->select(
            "SELECT a.id, a.application_code, a.next_followup_date, a.assigned_to,
                    a.state_id, a.district_id, b.name AS beneficiary_name,
                    COALESCE(s.short_name, s.name) AS scheme_name,
                    DATEDIFF(:today, a.next_followup_date) AS days_overdue
               FROM beneficiary_scheme_applications a
               JOIN beneficiaries b ON b.id = a.beneficiary_id
               JOIN schemes s       ON s.id = a.scheme_id
              WHERE a.deleted_at IS NULL
                AND a.next_followup_date IS NOT NULL
                AND a.next_followup_date < :today2
                AND a.benefit_received = 0
              ORDER BY a.next_followup_date
              LIMIT 400",
            ['today' => $today, 'today2' => $today]
        );
        foreach ($rows as $row) {
            if (self::exists(self::FOLLOWUP_OVERDUE, 'Application', (int)$row['id'], $today)) {
                continue;
            }
            $created += self::send(self::FOLLOWUP_OVERDUE,
                sprintf('Follow-up overdue by %d day%s', (int)$row['days_overdue'], (int)$row['days_overdue'] === 1 ? '' : 's'),
                [
                    'message'     => sprintf('%s - %s. Scheduled for %s.',
                                       $row['beneficiary_name'], $row['scheme_name'],
                                       date('d-m-Y', strtotime($row['next_followup_date']))),
                    'link'        => '/applications/' . (int)$row['id'],
                    'user_id'     => $row['assigned_to'] ? (int)$row['assigned_to'] : null,
                    'state_id'    => $row['state_id'] ? (int)$row['state_id'] : null,
                    'district_id' => $row['district_id'] ? (int)$row['district_id'] : null,
                    'entity_type' => 'Application',
                    'entity_id'   => (int)$row['id'],
                ]) !== null ? 1 : 0;
        }

        // ---- follow-ups due inside the window -------------------------
        $rows = $db->select(
            "SELECT a.id, a.next_followup_date, a.assigned_to, a.state_id, a.district_id,
                    b.name AS beneficiary_name, COALESCE(s.short_name, s.name) AS scheme_name
               FROM beneficiary_scheme_applications a
               JOIN beneficiaries b ON b.id = a.beneficiary_id
               JOIN schemes s       ON s.id = a.scheme_id
              WHERE a.deleted_at IS NULL
                AND a.next_followup_date BETWEEN :today AND DATE_ADD(:today2, INTERVAL :w DAY)
                AND a.benefit_received = 0
              ORDER BY a.next_followup_date
              LIMIT 400",
            ['today' => $today, 'today2' => $today, 'w' => $window]
        );
        foreach ($rows as $row) {
            if (self::exists(self::FOLLOWUP_DUE, 'Application', (int)$row['id'], $today)) {
                continue;
            }
            $isToday = $row['next_followup_date'] === $today;
            $created += self::send(self::FOLLOWUP_DUE,
                $isToday ? 'Follow-up due today' : 'Follow-up due on ' . date('d-m-Y', strtotime($row['next_followup_date'])),
                [
                    'message'     => $row['beneficiary_name'] . ' - ' . $row['scheme_name'],
                    'link'        => '/applications/' . (int)$row['id'],
                    'user_id'     => $row['assigned_to'] ? (int)$row['assigned_to'] : null,
                    'state_id'    => $row['state_id'] ? (int)$row['state_id'] : null,
                    'district_id' => $row['district_id'] ? (int)$row['district_id'] : null,
                    'priority'    => $isToday ? 'High' : 'Normal',
                    'entity_type' => 'Application',
                    'entity_id'   => (int)$row['id'],
                ]) !== null ? 1 : 0;
        }

        // ---- beneficiaries with documents outstanding -----------------
        $rows = $db->select(
            "SELECT m.beneficiary_id, b.name, b.state_id, b.district_id, b.field_user_id,
                    SUM(m.missing_doc_count) AS missing_total,
                    COUNT(*) AS scheme_count
               FROM beneficiary_scheme_matches m
               JOIN beneficiaries b ON b.id = m.beneficiary_id
              WHERE m.missing_doc_count > 0
                AND m.eligibility_status IN ('Eligible','Potentially Eligible')
                AND b.deleted_at IS NULL
              GROUP BY m.beneficiary_id
              ORDER BY missing_total DESC
              LIMIT 250"
        );
        foreach ($rows as $row) {
            if (self::exists(self::DOCUMENT_PENDING, 'Beneficiary', (int)$row['beneficiary_id'], $today)) {
                continue;
            }
            $created += self::send(self::DOCUMENT_PENDING, 'Documents pending for ' . $row['name'],
                [
                    'message'     => sprintf('%d document requirement(s) outstanding across %d matched scheme(s).',
                                       (int)$row['missing_total'], (int)$row['scheme_count']),
                    'link'        => '/beneficiaries/' . (int)$row['beneficiary_id'] . '/documents',
                    'user_id'     => $row['field_user_id'] ? (int)$row['field_user_id'] : null,
                    'state_id'    => (int)$row['state_id'],
                    'district_id' => (int)$row['district_id'],
                    'entity_type' => 'Beneficiary',
                    'entity_id'   => (int)$row['beneficiary_id'],
                ]) !== null ? 1 : 0;
        }

        // ---- recently approved / rejected -----------------------------
        foreach ([['APPROVED', self::APPLICATION_APPROVED, 'Application approved'],
                  ['REJECTED', self::APPLICATION_REJECTED, 'Application rejected']] as [$code, $type, $title]) {
            $rows = $db->select(
                "SELECT a.id, a.state_id, a.district_id, a.assigned_to, a.rejection_reason,
                        b.name AS beneficiary_name, COALESCE(s.short_name, s.name) AS scheme_name
                   FROM beneficiary_scheme_applications a
                   JOIN application_statuses st ON st.id = a.status_id
                   JOIN beneficiaries b ON b.id = a.beneficiary_id
                   JOIN schemes s       ON s.id = a.scheme_id
                  WHERE a.deleted_at IS NULL AND st.code = :code
                    AND a.updated_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
                  ORDER BY a.updated_at DESC LIMIT 120",
                ['code' => $code]
            );
            foreach ($rows as $row) {
                if (self::exists($type, 'Application', (int)$row['id'], $today)) {
                    continue;
                }
                $created += self::send($type, $title . ': ' . $row['scheme_name'],
                    [
                        'message'     => $row['beneficiary_name']
                                         . ($code === 'REJECTED' && $row['rejection_reason']
                                            ? ' - ' . $row['rejection_reason'] : ''),
                        'link'        => '/applications/' . (int)$row['id'],
                        'user_id'     => $row['assigned_to'] ? (int)$row['assigned_to'] : null,
                        'state_id'    => $row['state_id'] ? (int)$row['state_id'] : null,
                        'district_id' => $row['district_id'] ? (int)$row['district_id'] : null,
                        'entity_type' => 'Application',
                        'entity_id'   => (int)$row['id'],
                    ]) !== null ? 1 : 0;
            }
        }

        // ---- applications stuck at submission -------------------------
        $rows = $db->select(
            "SELECT a.id, a.state_id, a.district_id, a.assigned_to, a.submission_date,
                    b.name AS beneficiary_name, COALESCE(s.short_name, s.name) AS scheme_name,
                    DATEDIFF(CURDATE(), a.submission_date) AS days_waiting
               FROM beneficiary_scheme_applications a
               JOIN application_statuses st ON st.id = a.status_id
               JOIN beneficiaries b ON b.id = a.beneficiary_id
               JOIN schemes s       ON s.id = a.scheme_id
              WHERE a.deleted_at IS NULL
                AND st.code IN ('SUBMITTED','UNDER_VERIFICATION')
                AND a.submission_date IS NOT NULL
                AND a.submission_date < DATE_SUB(CURDATE(), INTERVAL 45 DAY)
              ORDER BY a.submission_date LIMIT 150"
        );
        foreach ($rows as $row) {
            if (self::exists(self::APPLICATION_PENDING, 'Application', (int)$row['id'], $today)) {
                continue;
            }
            $created += self::send(self::APPLICATION_PENDING,
                sprintf('Application pending %d days with the department', (int)$row['days_waiting']),
                [
                    'message'     => $row['beneficiary_name'] . ' - ' . $row['scheme_name'],
                    'link'        => '/applications/' . (int)$row['id'],
                    'user_id'     => $row['assigned_to'] ? (int)$row['assigned_to'] : null,
                    'state_id'    => $row['state_id'] ? (int)$row['state_id'] : null,
                    'district_id' => $row['district_id'] ? (int)$row['district_id'] : null,
                    'priority'    => 'High',
                    'entity_type' => 'Application',
                    'entity_id'   => (int)$row['id'],
                ]) !== null ? 1 : 0;
        }

        return $created;
    }

    private static function exists(string $type, string $entityType, int $entityId, string $date): bool
    {
        return (int)Database::getInstance()->scalar(
            'SELECT COUNT(*) FROM notifications
              WHERE type = :t AND entity_type = :et AND entity_id = :eid
                AND DATE(created_at) = :d',
            ['t' => $type, 'et' => $entityType, 'eid' => $entityId, 'd' => $date]
        ) > 0;
    }

    // =================================================================
    // READING
    // =================================================================

    /**
     * Notifications visible to a user: addressed to them directly, or
     * broadcast to their role / geography.
     */
    public static function forUser(int $userId, int $limit = 15, bool $unreadOnly = false): array
    {
        $user = Database::getInstance()->selectOne(
            'SELECT id, role_id, state_id, district_id FROM users WHERE id = :id',
            ['id' => $userId]
        );
        if (!$user) {
            return [];
        }

        $conditions = ['n.user_id = :uid'];
        $bindings   = ['uid' => $userId];

        // Broadcasts: an unaddressed notification reaches a user when its
        // role/state/district either matches theirs or is left open.
        $conditions[] = '(n.user_id IS NULL'
                      . ' AND (n.role_id IS NULL OR n.role_id = :rid)'
                      . ' AND (n.state_id IS NULL OR n.state_id = :sid)'
                      . ' AND (n.district_id IS NULL OR n.district_id = :did))';
        $bindings['rid'] = (int)$user['role_id'];
        $bindings['sid'] = $user['state_id'] !== null ? (int)$user['state_id'] : 0;
        $bindings['did'] = $user['district_id'] !== null ? (int)$user['district_id'] : 0;

        $where = '(' . implode(' OR ', $conditions) . ')';

        /*
         * Read state lives in notification_reads for broadcasts, and on the
         * row itself for directly addressed notifications.
         *
         * The expression is needed twice - once in the SELECT list and once
         * in the WHERE - and PDO with emulated prepares turned off binds each
         * occurrence of a named placeholder separately, so each rendering
         * gets its own parameter names.
         */
        $readExpr = function (string $suffix) use (&$bindings, $userId): string {
            $bindings['ru' . $suffix] = $userId;
            $bindings['rn' . $suffix] = $userId;
            return "((n.is_read = 1 AND n.user_id = :ru{$suffix})"
                 . " OR EXISTS (SELECT 1 FROM notification_reads nr"
                 . "             WHERE nr.notification_id = n.id AND nr.user_id = :rn{$suffix}))";
        };

        $seenExpr = $readExpr('a');
        if ($unreadOnly) {
            $where .= ' AND NOT ' . $readExpr('b');
        }

        $sql = "SELECT n.*, CASE WHEN {$seenExpr} THEN 1 ELSE 0 END AS seen
                  FROM notifications n
                 WHERE {$where}
                 ORDER BY n.created_at DESC, n.id DESC
                 LIMIT :lim";
        $bindings['lim'] = max(1, $limit);

        return Database::getInstance()->select($sql, $bindings);
    }

    public static function unreadCount(int $userId): int
    {
        $rows = self::forUser($userId, 200, true);
        return count($rows);
    }

    public static function markRead(int $notificationId, int $userId): void
    {
        $db = Database::getInstance();
        $row = $db->selectOne('SELECT id, user_id FROM notifications WHERE id = :id', ['id' => $notificationId]);
        if (!$row) {
            return;
        }
        if ($row['user_id'] !== null && (int)$row['user_id'] === $userId) {
            $db->update('notifications', ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')], ['id' => $notificationId]);
            return;
        }
        // Broadcast: record the read per user.
        $db->upsert('notification_reads', [
            'notification_id' => $notificationId,
            'user_id'         => $userId,
            'read_at'         => date('Y-m-d H:i:s'),
        ], ['read_at']);
    }

    public static function markAllRead(int $userId): int
    {
        $rows = self::forUser($userId, 500, true);
        foreach ($rows as $row) {
            self::markRead((int)$row['id'], $userId);
        }
        return count($rows);
    }
}
