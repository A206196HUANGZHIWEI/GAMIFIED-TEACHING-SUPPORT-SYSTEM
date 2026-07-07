<?php
require __DIR__ . '/includes/gtss.php';
require __DIR__ . '/includes/layout.php';

$courseId = isset($_GET['courseId']) ? $_GET['courseId'] : '';
$itemId = isset($_GET['itemId']) ? $_GET['itemId'] : '';
$course = require_course_access($courseId);
$user = current_user();
$canTeacherManage = $user['role'] === 'teacher' && $course['teacherId'] === $user['userId'];

if (!$canTeacherManage) {
    http_response_code(403);
    exit('Access denied.');
}

$errors = array();
$item = db_one(
    "SELECT * FROM reward_items
     WHERE itemId = '" . db_escape($itemId) . "'
       AND courseId = '" . db_escape($courseId) . "'
       AND teacherId = '" . db_escape($user['userId']) . "'
     LIMIT 1"
);

if (!$item) {
    http_response_code(404);
    exit('Reward item not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Invalid form token. Please try again.';
    }

    $action = isset($_POST['action']) ? $_POST['action'] : 'update';

    if (!$errors && $action === 'archive') {
        db_exec(
            "UPDATE reward_items
             SET status = 'archived', updatedAt = NOW()
             WHERE itemId = '" . db_escape($itemId) . "'
               AND courseId = '" . db_escape($courseId) . "'"
        );
        flash('success', 'Reward item archived.');
        redirect_to('reward_store.php?courseId=' . urlencode($courseId));
    }

    if (!$errors && $action === 'update') {
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $xpCost = isset($_POST['xpCost']) ? (int) $_POST['xpCost'] : 0;
        $stock = isset($_POST['stock']) ? (int) $_POST['stock'] : 0;
        $imagePath = isset($item['imagePath']) ? $item['imagePath'] : '';

        if ($name === '' || strlen($name) > 100) {
            $errors[] = 'Item name is required and must be 100 characters or fewer.';
        }

        if ($xpCost <= 0 || $xpCost > 100000) {
            $errors[] = 'XP cost must be between 1 and 100000.';
        }

        if ($stock < 0 || $stock > 1000) {
            $errors[] = 'Stock must be between 0 and 1000.';
        }

        if (!$errors) {
            if (isset($_POST['removeImage'])) {
                $imagePath = '';
            }

            $uploadedImage = save_uploaded_reward_item_image('itemImage', $courseId, $itemId, $errors);
            if (!$errors && $uploadedImage !== '') {
                $imagePath = $uploadedImage;
            }
        }

        if (!$errors) {
            db_exec(
                "UPDATE reward_items
                 SET name = '" . db_escape($name) . "',
                     description = '" . db_escape($description) . "',
                     imagePath = '" . db_escape($imagePath) . "',
                     xpCost = " . (int) $xpCost . ",
                     stock = " . (int) $stock . ",
                     status = 'pending',
                     adminId = NULL,
                     adminNote = '',
                     reviewedAt = NULL,
                     updatedAt = NOW()
                 WHERE itemId = '" . db_escape($itemId) . "'
                   AND courseId = '" . db_escape($courseId) . "'"
            );

            $admins = db_all("SELECT userId FROM users WHERE role = 'admin'");
            foreach ($admins as $admin) {
                create_notification($admin['userId'], $courseId, 'Reward item needs review', $name . ' was updated and needs XP Store approval in ' . $course['title'] . '.', 'store');
            }

            flash('success', 'Reward item saved and submitted for administrator review.');
            redirect_to('reward_store.php?courseId=' . urlencode($courseId));
        }
    }
}

page_header('Edit Reward Item');
?>
<section class="panel">
    <div class="section-head">
        <div>
            <p class="eyebrow">XP Store</p>
            <h1>Edit Reward Item</h1>
            <p class="muted">Changes must be reviewed by an administrator before students can redeem the item.</p>
        </div>
        <a class="button secondary" href="reward_store.php?courseId=<?php echo h($courseId); ?>">Back to store</a>
    </div>
    <?php render_errors($errors); ?>
</section>

<section class="grid two-col mt">
    <div class="panel">
        <h2>Item details</h2>
        <form method="post" action="reward_item_edit.php?courseId=<?php echo h($courseId); ?>&itemId=<?php echo h($itemId); ?>" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="update">

            <label for="name">Item name</label>
            <input id="name" name="name" type="text" maxlength="100" value="<?php echo h($item['name']); ?>" required>

            <div class="grid two-col tight">
                <div>
                    <label for="xpCost">XP cost</label>
                    <input id="xpCost" name="xpCost" type="number" min="1" max="100000" value="<?php echo h($item['xpCost']); ?>" required>
                </div>
                <div>
                    <label for="stock">Stock</label>
                    <input id="stock" name="stock" type="number" min="0" max="1000" value="<?php echo h($item['stock']); ?>" required>
                </div>
            </div>

            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4"><?php echo h($item['description']); ?></textarea>

            <label for="itemImage">Item image</label>
            <input id="itemImage" name="itemImage" type="file" accept=".jpg,.jpeg,.png,.gif,image/jpeg,image/png,image/gif">

            <?php if ($item['imagePath']): ?>
                <div class="badge-preview-block">
                    <?php echo reward_item_image($item['imagePath'], $item['name'], 'reward-image large'); ?>
                    <span>Current item image</span>
                </div>
                <label class="check">
                    <input type="checkbox" name="removeImage" value="1">
                    Remove current image
                </label>
            <?php endif; ?>

            <div class="form-footer">
                <button type="submit">Save and resubmit</button>
            </div>
        </form>
    </div>

    <div class="panel">
        <h2>Review status</h2>
        <p><?php echo status_badge(reward_item_status_label($item['status'])); ?></p>
        <?php if ($item['adminNote']): ?>
            <p class="muted">Admin note: <?php echo h($item['adminNote']); ?></p>
        <?php endif; ?>
        <form method="post" action="reward_item_edit.php?courseId=<?php echo h($courseId); ?>&itemId=<?php echo h($itemId); ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="archive">
            <div class="form-footer">
                <button type="submit">Archive item</button>
            </div>
        </form>
    </div>
</section>
<?php page_footer(); ?>
