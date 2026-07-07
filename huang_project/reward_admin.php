<?php
require __DIR__ . '/includes/gtss.php';
require __DIR__ . '/includes/layout.php';

require_role('admin');

$user = current_user();
$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Invalid form token. Please try again.';
    }

    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $itemId = isset($_POST['itemId']) ? $_POST['itemId'] : '';
    $adminNote = isset($_POST['adminNote']) ? trim($_POST['adminNote']) : '';

    $item = db_one(
        "SELECT ri.*, c.title AS courseTitle
         FROM reward_items ri
         INNER JOIN courses c ON c.courseId = ri.courseId
         WHERE ri.itemId = '" . db_escape($itemId) . "'
         LIMIT 1"
    );

    if (!$item) {
        $errors[] = 'Reward item was not found.';
    }

    if (!$errors && $action === 'delist_item' && $adminNote === '') {
        $errors[] = 'A reason is required when delisting an item.';
    }

    if (!$errors && $action === 'approve_item') {
        db_exec(
            "UPDATE reward_items
             SET status = 'approved',
                 adminId = '" . db_escape($user['userId']) . "',
                 adminNote = '" . db_escape($adminNote) . "',
                 reviewedAt = NOW(),
                 updatedAt = NOW()
             WHERE itemId = '" . db_escape($itemId) . "'"
        );

        create_notification($item['teacherId'], $item['courseId'], 'Reward item approved', '"' . $item['name'] . '" is now open for XP redemption in ' . $item['courseTitle'] . '.', 'store');
        flash('success', 'Reward item approved.');
        redirect_to('reward_admin.php');
    }

    if (!$errors && $action === 'delist_item') {
        db_exec(
            "UPDATE reward_items
             SET status = 'delisted',
                 adminId = '" . db_escape($user['userId']) . "',
                 adminNote = '" . db_escape($adminNote) . "',
                 reviewedAt = NOW(),
                 updatedAt = NOW()
             WHERE itemId = '" . db_escape($itemId) . "'"
        );

        create_notification($item['teacherId'], $item['courseId'], 'Reward item delisted', '"' . $item['name'] . '" was delisted. Reason: ' . $adminNote, 'store');
        flash('success', 'Reward item delisted.');
        redirect_to('reward_admin.php');
    }
}

$status = isset($_GET['status']) ? $_GET['status'] : 'pending';
$allowedStatuses = array('all', 'pending', 'approved', 'delisted', 'archived');
if (!in_array($status, $allowedStatuses, true)) {
    $status = 'pending';
}

$where = '';
if ($status !== 'all') {
    $where = "WHERE ri.status = '" . db_escape($status) . "'";
}

$items = db_all(
    "SELECT ri.*, c.title AS courseTitle, u.name AS teacherName, a.name AS adminName
     FROM reward_items ri
     INNER JOIN courses c ON c.courseId = ri.courseId
     INNER JOIN users u ON u.userId = ri.teacherId
     LEFT JOIN users a ON a.userId = ri.adminId
     " . $where . "
     ORDER BY FIELD(ri.status, 'pending', 'approved', 'delisted', 'archived'), ri.updatedAt DESC"
);

$statusCounts = array();
foreach ($allowedStatuses as $option) {
    if ($option === 'all') {
        $statusCounts[$option] = (int) db_value("SELECT COUNT(*) FROM reward_items", 0);
    } else {
        $statusCounts[$option] = (int) db_value("SELECT COUNT(*) FROM reward_items WHERE status = '" . db_escape($option) . "'", 0);
    }
}

page_header('Store Review');
?>
<section class="panel">
    <div class="section-head">
        <div>
            <p class="eyebrow">Administrator Review</p>
            <h1>XP Store Items</h1>
            <p class="muted">Approve reward items before they become redeemable, or delist items with a reason.</p>
        </div>
        <a class="button secondary" href="admin.php">Back to admin</a>
    </div>
    <?php render_errors($errors); ?>
</section>

<section class="panel mt">
    <div class="filter-row">
        <span>Status</span>
        <?php foreach ($allowedStatuses as $option): ?>
            <a class="<?php echo $status === $option ? 'active' : ''; ?>" href="reward_admin.php?status=<?php echo h($option); ?>">
                <?php echo h(ucfirst($option)); ?> <?php echo h($statusCounts[$option]); ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="panel mt">
    <h2>Reward items</h2>
    <?php if (!$items): ?>
        <p class="muted">No reward items found.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Course</th>
                        <th>Teacher</th>
                        <th>Cost / Stock</th>
                        <th>Status</th>
                        <th>Admin note</th>
                        <th>Review action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <div class="badge-card-head compact">
                                    <?php echo reward_item_image($item['imagePath'], $item['name'], 'reward-image small'); ?>
                                    <div>
                                        <strong><?php echo h($item['name']); ?></strong>
                                        <span class="muted"><?php echo h($item['description']); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo h($item['courseTitle']); ?></td>
                            <td><?php echo h($item['teacherName']); ?></td>
                            <td><?php echo h($item['xpCost']); ?> XP<br><span class="muted">Stock <?php echo h($item['stock']); ?></span></td>
                            <td><?php echo status_badge(reward_item_status_label($item['status'])); ?></td>
                            <td><?php echo h($item['adminNote']); ?></td>
                            <td>
                                <form method="post" action="reward_admin.php?status=<?php echo h($status); ?>" class="review-form">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="itemId" value="<?php echo h($item['itemId']); ?>">
                                    <textarea name="adminNote" rows="2" maxlength="500" placeholder="Review note or delist reason"><?php echo h($item['adminNote']); ?></textarea>
                                    <div class="actions compact-actions">
                                        <?php if ($item['status'] !== 'approved'): ?>
                                            <button type="submit" name="action" value="approve_item">Approve</button>
                                        <?php endif; ?>
                                        <?php if ($item['status'] !== 'delisted'): ?>
                                            <button type="submit" name="action" value="delist_item">Delist</button>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php page_footer(); ?>
