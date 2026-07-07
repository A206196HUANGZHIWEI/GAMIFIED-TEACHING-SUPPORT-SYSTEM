<?php
require __DIR__ . '/includes/gtss.php';
require __DIR__ . '/includes/layout.php';

require_login();
$user = current_user();

$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$type = isset($_GET['type']) ? $_GET['type'] : 'all';
$allowedStatuses = array('all', 'unread', 'read');
$typeOptions = notification_type_options();

if (!in_array($status, $allowedStatuses, true)) {
    $status = 'all';
}

if (!isset($typeOptions[$type])) {
    $type = 'all';
}

function notification_filter_url($status, $type)
{
    return 'notifications.php?status=' . urlencode($status) . '&type=' . urlencode($type);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!verify_csrf_token($csrfToken)) {
        flash('error', 'Invalid form token. Please try again.');
        redirect_to(notification_filter_url($status, $type));
    }

    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $redirectStatus = isset($_POST['status']) ? $_POST['status'] : $status;
    $redirectType = isset($_POST['type']) ? $_POST['type'] : $type;

    if (!in_array($redirectStatus, $allowedStatuses, true)) {
        $redirectStatus = 'all';
    }

    if (!isset($typeOptions[$redirectType])) {
        $redirectType = 'all';
    }

    if ($action === 'mark_all') {
        db_exec(
            "UPDATE notifications SET isRead = 1
             WHERE userId = '" . db_escape($user['userId']) . "'"
        );
        flash('success', 'Notifications marked as read.');
        redirect_to(notification_filter_url($redirectStatus, $redirectType));
    }

    if ($action === 'mark_one') {
        $notificationId = isset($_POST['notificationId']) ? $_POST['notificationId'] : '';
        db_exec(
            "UPDATE notifications SET isRead = 1
             WHERE userId = '" . db_escape($user['userId']) . "'
               AND notificationId = '" . db_escape($notificationId) . "'"
        );
        flash('success', 'Notification marked as read.');
        redirect_to(notification_filter_url($redirectStatus, $redirectType));
    }
}

$allNotifications = db_all(
    "SELECT n.*, c.title AS courseTitle
     FROM notifications n
     LEFT JOIN courses c ON c.courseId = n.courseId
     WHERE n.userId = '" . db_escape($user['userId']) . "'
     ORDER BY n.createdAt DESC"
);

$statusCounts = array(
    'all' => 0,
    'unread' => 0,
    'read' => 0,
);
$typeCounts = array();
foreach ($typeOptions as $key => $label) {
    $typeCounts[$key] = 0;
}

$notifications = array();
$grouped = array();

foreach ($allNotifications as $notice) {
    $noticeType = normalize_notification_type(
        isset($notice['notificationType']) ? $notice['notificationType'] : '',
        $notice['title'],
        $notice['message']
    );
    $notice['resolvedType'] = $noticeType;
    $notice['resolvedTypeLabel'] = notification_type_label($noticeType);
    $noticeStatus = (int) $notice['isRead'] === 0 ? 'unread' : 'read';

    $statusCounts['all']++;
    $statusCounts[$noticeStatus]++;
    $typeCounts['all']++;
    if (!isset($typeCounts[$noticeType])) {
        $typeCounts[$noticeType] = 0;
    }
    $typeCounts[$noticeType]++;

    if ($status !== 'all' && $noticeStatus !== $status) {
        continue;
    }

    if ($type !== 'all' && $noticeType !== $type) {
        continue;
    }

    $notifications[] = $notice;
    if (!isset($grouped[$noticeType])) {
        $grouped[$noticeType] = array();
    }
    $grouped[$noticeType][] = $notice;
}

page_header('Notifications');
?>
<section class="panel">
    <div class="section-head">
        <div>
            <p class="eyebrow">Notification Center</p>
            <h1>Notifications</h1>
            <p class="muted">Review personal course, quest, result, badge, announcement, and feedback updates.</p>
        </div>
        <form method="post" action="<?php echo h(notification_filter_url($status, $type)); ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="mark_all">
            <input type="hidden" name="status" value="<?php echo h($status); ?>">
            <input type="hidden" name="type" value="<?php echo h($type); ?>">
            <button type="submit">Mark all read</button>
        </form>
    </div>
</section>

<section class="panel mt">
    <div class="notification-filters">
        <div class="filter-row">
            <span>Status</span>
            <a class="<?php echo $status === 'all' ? 'active' : ''; ?>" href="<?php echo h(notification_filter_url('all', $type)); ?>">All <?php echo h($statusCounts['all']); ?></a>
            <a class="<?php echo $status === 'unread' ? 'active' : ''; ?>" href="<?php echo h(notification_filter_url('unread', $type)); ?>">Unread <?php echo h($statusCounts['unread']); ?></a>
            <a class="<?php echo $status === 'read' ? 'active' : ''; ?>" href="<?php echo h(notification_filter_url('read', $type)); ?>">Read <?php echo h($statusCounts['read']); ?></a>
        </div>
        <div class="filter-row">
            <span>Category</span>
            <?php foreach ($typeOptions as $optionType => $label): ?>
                <a class="<?php echo $type === $optionType ? 'active' : ''; ?>" href="<?php echo h(notification_filter_url($status, $optionType)); ?>">
                    <?php echo h($label); ?> <?php echo h(isset($typeCounts[$optionType]) ? $typeCounts[$optionType] : 0); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="panel mt">
    <?php if (!$notifications): ?>
        <p class="muted">No notifications match this filter.</p>
    <?php else: ?>
        <?php if ($type === 'all'): ?>
            <div class="notification-groups">
                <?php foreach ($typeOptions as $optionType => $label): ?>
                    <?php if ($optionType === 'all' || !isset($grouped[$optionType])) { continue; } ?>
                    <section class="notification-group">
                        <div class="notification-group-title">
                            <h2><?php echo h($label); ?></h2>
                            <span><?php echo h(count($grouped[$optionType])); ?></span>
                        </div>
                        <div class="list">
                            <?php foreach ($grouped[$optionType] as $notice): ?>
                                <?php include __DIR__ . '/includes/notification_item.php'; ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="list">
                <?php foreach ($notifications as $notice): ?>
                    <?php include __DIR__ . '/includes/notification_item.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php page_footer(); ?>
