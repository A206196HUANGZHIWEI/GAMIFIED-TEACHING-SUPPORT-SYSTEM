<?php
require __DIR__ . '/includes/gtss.php';
require __DIR__ . '/includes/layout.php';

$courseId = isset($_GET['courseId']) ? $_GET['courseId'] : '';
$course = require_course_access($courseId);
$user = current_user();
$canTeacherManage = $user['role'] === 'teacher' && $course['teacherId'] === $user['userId'];
$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Invalid form token. Please try again.';
    }

    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if (!$errors && $action === 'create_item') {
        if (!$canTeacherManage) {
            $errors[] = 'Only the course teacher can create reward items.';
        }

        $itemId = uuid_v4();
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $xpCost = isset($_POST['xpCost']) ? (int) $_POST['xpCost'] : 0;
        $stock = isset($_POST['stock']) ? (int) $_POST['stock'] : 0;
        $imagePath = '';

        if ($name === '' || strlen($name) > 100) {
            $errors[] = 'Item name is required and must be 100 characters or fewer.';
        }

        if ($xpCost <= 0 || $xpCost > 100000) {
            $errors[] = 'XP cost must be between 1 and 100000.';
        }

        if ($stock <= 0 || $stock > 1000) {
            $errors[] = 'Stock must be between 1 and 1000.';
        }

        if (!$errors) {
            $imagePath = save_uploaded_reward_item_image('itemImage', $courseId, $itemId, $errors);
        }

        if (!$errors) {
            db_exec(
                "INSERT INTO reward_items (itemId, courseId, teacherId, name, description, imagePath, xpCost, stock, status, adminId, adminNote, reviewedAt, createdAt, updatedAt)
                 VALUES ('" . db_escape($itemId) . "', '" . db_escape($courseId) . "', '" . db_escape($user['userId']) . "', '" . db_escape($name) . "', '" . db_escape($description) . "', '" . db_escape($imagePath) . "', " . (int) $xpCost . ", " . (int) $stock . ", 'pending', NULL, '', NULL, NOW(), NOW())"
            );

            $admins = db_all("SELECT userId FROM users WHERE role = 'admin'");
            foreach ($admins as $admin) {
                create_notification($admin['userId'], $courseId, 'Reward item needs review', $name . ' was submitted for XP Store approval in ' . $course['title'] . '.', 'store');
            }

            flash('success', 'Reward item submitted for administrator review.');
            redirect_to('reward_store.php?courseId=' . urlencode($courseId));
        }
    }

    if (!$errors && $action === 'redeem_item') {
        if ($user['role'] !== 'student') {
            $errors[] = 'Only students can redeem reward items.';
        }

        $itemId = isset($_POST['itemId']) ? $_POST['itemId'] : '';
        $item = db_one(
            "SELECT * FROM reward_items
             WHERE itemId = '" . db_escape($itemId) . "'
               AND courseId = '" . db_escape($courseId) . "'
               AND status = 'approved'
             LIMIT 1"
        );

        if (!$item) {
            $errors[] = 'This item is not available for redemption.';
        }

        if (!$errors && (int) $item['stock'] <= 0) {
            $errors[] = 'This item is out of stock.';
        }

        if (!$errors && student_available_xp($courseId, $user['userId']) < (int) $item['xpCost']) {
            $errors[] = 'You do not have enough available XP for this item.';
        }

        if (!$errors) {
            db_exec(
                "UPDATE reward_items
                 SET stock = stock - 1, updatedAt = NOW()
                 WHERE itemId = '" . db_escape($itemId) . "'
                   AND courseId = '" . db_escape($courseId) . "'
                   AND status = 'approved'
                   AND stock > 0"
            );

            if (db()->affected_rows <= 0) {
                $errors[] = 'This item is no longer available.';
            }
        }

        if (!$errors) {
            $redemptionId = uuid_v4();
            db_exec(
                "INSERT INTO reward_redemptions (redemptionId, itemId, courseId, studentId, xpCost, status, teacherNote, createdAt, updatedAt, fulfilledAt)
                 VALUES ('" . db_escape($redemptionId) . "', '" . db_escape($itemId) . "', '" . db_escape($courseId) . "', '" . db_escape($user['userId']) . "', " . (int) $item['xpCost'] . ", 'pending', '', NOW(), NOW(), NULL)"
            );

            create_notification($item['teacherId'], $courseId, 'Reward item redeemed', $user['name'] . ' redeemed "' . $item['name'] . '". Please hand it over offline.', 'store');
            create_notification($user['userId'], $courseId, 'Redemption submitted', 'Your redemption for "' . $item['name'] . '" is waiting for teacher handover.', 'store');

            flash('success', 'Item redeemed. Please wait for your teacher to hand it over offline.');
            redirect_to('reward_store.php?courseId=' . urlencode($courseId));
        }
    }

    if (!$errors && $action === 'fulfill_redemption') {
        if (!$canTeacherManage) {
            $errors[] = 'Only the course teacher can mark redemptions as fulfilled.';
        }

        $redemptionId = isset($_POST['redemptionId']) ? $_POST['redemptionId'] : '';
        $teacherNote = isset($_POST['teacherNote']) ? trim($_POST['teacherNote']) : '';
        $redemption = db_one(
            "SELECT rr.*, ri.name AS itemName, ri.teacherId
             FROM reward_redemptions rr
             INNER JOIN reward_items ri ON ri.itemId = rr.itemId
             WHERE rr.redemptionId = '" . db_escape($redemptionId) . "'
               AND rr.courseId = '" . db_escape($courseId) . "'
               AND rr.status = 'pending'
             LIMIT 1"
        );

        if (!$redemption || $redemption['teacherId'] !== $user['userId']) {
            $errors[] = 'Redemption was not found.';
        }

        if (!$errors) {
            db_exec(
                "UPDATE reward_redemptions
                 SET status = 'fulfilled',
                     teacherNote = '" . db_escape($teacherNote) . "',
                     updatedAt = NOW(),
                     fulfilledAt = NOW()
                 WHERE redemptionId = '" . db_escape($redemptionId) . "'"
            );

            create_notification($redemption['studentId'], $courseId, 'Reward item fulfilled', 'Your teacher marked "' . $redemption['itemName'] . '" as handed over.', 'store');
            flash('success', 'Redemption marked as fulfilled.');
            redirect_to('reward_store.php?courseId=' . urlencode($courseId));
        }
    }
}

$approvedItems = db_all(
    "SELECT ri.*, u.name AS teacherName
     FROM reward_items ri
     INNER JOIN users u ON u.userId = ri.teacherId
     WHERE ri.courseId = '" . db_escape($courseId) . "'
       AND ri.status = 'approved'
     ORDER BY ri.xpCost ASC, ri.createdAt DESC"
);

$teacherItems = $canTeacherManage ? db_all(
    "SELECT ri.*, u.name AS adminName
     FROM reward_items ri
     LEFT JOIN users u ON u.userId = ri.adminId
     WHERE ri.courseId = '" . db_escape($courseId) . "'
       AND ri.teacherId = '" . db_escape($user['userId']) . "'
     ORDER BY ri.updatedAt DESC"
) : array();

$pendingRedemptions = $canTeacherManage ? db_all(
    "SELECT rr.*, ri.name AS itemName, ri.imagePath, u.name AS studentName, u.email AS studentEmail
     FROM reward_redemptions rr
     INNER JOIN reward_items ri ON ri.itemId = rr.itemId
     INNER JOIN users u ON u.userId = rr.studentId
     WHERE rr.courseId = '" . db_escape($courseId) . "'
       AND ri.teacherId = '" . db_escape($user['userId']) . "'
       AND rr.status = 'pending'
     ORDER BY rr.createdAt ASC"
) : array();

$myRedemptions = $user['role'] === 'student' ? db_all(
    "SELECT rr.*, ri.name AS itemName, ri.imagePath
     FROM reward_redemptions rr
     INNER JOIN reward_items ri ON ri.itemId = rr.itemId
     WHERE rr.courseId = '" . db_escape($courseId) . "'
       AND rr.studentId = '" . db_escape($user['userId']) . "'
     ORDER BY rr.createdAt DESC"
) : array();

$availableXP = $user['role'] === 'student' ? student_available_xp($courseId, $user['userId']) : 0;

page_header('XP Store');
?>
<section class="panel">
    <div class="section-head">
        <div>
            <p class="eyebrow">XP Store</p>
            <h1><?php echo h($course['title']); ?> Rewards</h1>
            <p class="muted">Students redeem approved items with available XP. Teachers hand over redeemed items offline.</p>
        </div>
        <a class="button secondary" href="course.php?courseId=<?php echo h($courseId); ?>">Back to course</a>
    </div>
    <?php render_errors($errors); ?>
</section>

<?php if ($user['role'] === 'student'): ?>
<section class="panel mt">
    <p class="eyebrow">Available Balance</p>
    <h2><?php echo h($availableXP); ?> XP available</h2>
    <p class="muted">Total XP remains on your progress record; redeemed item costs are deducted from your store balance.</p>
</section>
<?php endif; ?>

<?php if ($canTeacherManage): ?>
<section class="panel mt">
    <h2>Create reward item</h2>
    <form method="post" action="reward_store.php?courseId=<?php echo h($courseId); ?>" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="create_item">
        <div class="grid three-col tight">
            <div>
                <label for="name">Item name</label>
                <input id="name" name="name" type="text" maxlength="100" required>
            </div>
            <div>
                <label for="xpCost">XP cost</label>
                <input id="xpCost" name="xpCost" type="number" min="1" max="100000" value="100" required>
            </div>
            <div>
                <label for="stock">Stock</label>
                <input id="stock" name="stock" type="number" min="1" max="1000" value="1" required>
            </div>
        </div>
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="3"></textarea>
        <label for="itemImage">Item image</label>
        <input id="itemImage" name="itemImage" type="file" accept=".jpg,.jpeg,.png,.gif,image/jpeg,image/png,image/gif">
        <p class="muted">New items stay unavailable until an administrator approves them.</p>
        <div class="form-footer">
            <button type="submit">Submit for review</button>
        </div>
    </form>
</section>

<section class="panel mt">
    <h2>Pending handovers</h2>
    <?php if (!$pendingRedemptions): ?>
        <p class="muted">No pending redemptions.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Student</th>
                        <th>XP</th>
                        <th>Redeemed</th>
                        <th>Handover note</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingRedemptions as $redemption): ?>
                        <tr>
                            <td><?php echo h($redemption['itemName']); ?></td>
                            <td><?php echo h($redemption['studentName']); ?><br><span class="muted"><?php echo h($redemption['studentEmail']); ?></span></td>
                            <td><?php echo h($redemption['xpCost']); ?></td>
                            <td><?php echo h($redemption['createdAt']); ?></td>
                            <td colspan="2">
                                <form method="post" action="reward_store.php?courseId=<?php echo h($courseId); ?>" class="inline-form">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="fulfill_redemption">
                                    <input type="hidden" name="redemptionId" value="<?php echo h($redemption['redemptionId']); ?>">
                                    <input name="teacherNote" type="text" maxlength="255" placeholder="Optional handover note">
                                    <button type="submit">Mark fulfilled</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="panel mt">
    <h2>Available items</h2>
    <?php if (!$approvedItems): ?>
        <p class="muted">No approved reward items yet.</p>
    <?php else: ?>
        <div class="reward-grid">
            <?php foreach ($approvedItems as $item): ?>
                <article class="item-card reward-card">
                    <div class="reward-card-head">
                        <?php echo reward_item_image($item['imagePath'], $item['name'], 'reward-image'); ?>
                        <div>
                            <h3><?php echo h($item['name']); ?></h3>
                            <p class="muted"><?php echo h($item['description']); ?></p>
                        </div>
                    </div>
                    <p class="meta">Cost: <?php echo h($item['xpCost']); ?> XP | Stock: <?php echo h($item['stock']); ?> | Teacher: <?php echo h($item['teacherName']); ?></p>
                    <?php if ($user['role'] === 'student'): ?>
                        <form method="post" action="reward_store.php?courseId=<?php echo h($courseId); ?>">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="redeem_item">
                            <input type="hidden" name="itemId" value="<?php echo h($item['itemId']); ?>">
                            <button type="submit" <?php echo ((int) $item['stock'] <= 0 || $availableXP < (int) $item['xpCost']) ? 'disabled' : ''; ?>>Redeem</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php if ($canTeacherManage): ?>
<section class="panel mt">
    <h2>My reward items</h2>
    <?php if (!$teacherItems): ?>
        <p class="muted">No reward items created yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Cost</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Admin note</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teacherItems as $item): ?>
                        <tr>
                            <td><?php echo h($item['name']); ?></td>
                            <td><?php echo h($item['xpCost']); ?> XP</td>
                            <td><?php echo h($item['stock']); ?></td>
                            <td><?php echo status_badge(reward_item_status_label($item['status'])); ?></td>
                            <td><?php echo h($item['adminNote']); ?></td>
                            <td><a href="reward_item_edit.php?courseId=<?php echo h($courseId); ?>&itemId=<?php echo h($item['itemId']); ?>">Edit</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($user['role'] === 'student'): ?>
<section class="panel mt">
    <h2>My redemptions</h2>
    <?php if (!$myRedemptions): ?>
        <p class="muted">No redemptions yet.</p>
    <?php else: ?>
        <div class="list">
            <?php foreach ($myRedemptions as $redemption): ?>
                <div class="list-item">
                    <strong><?php echo h($redemption['itemName']); ?></strong>
                    <span><?php echo h($redemption['xpCost']); ?> XP - <?php echo h(reward_redemption_status_label($redemption['status'])); ?> - <?php echo h($redemption['createdAt']); ?></span>
                    <?php if ($redemption['teacherNote']): ?>
                        <span><?php echo h($redemption['teacherNote']); ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php page_footer(); ?>
