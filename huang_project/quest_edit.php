<?php
require __DIR__ . '/includes/gtss.php';
require __DIR__ . '/includes/layout.php';

$courseId = isset($_GET['courseId']) ? $_GET['courseId'] : '';
$questId = isset($_GET['questId']) ? $_GET['questId'] : '';
$course = require_course_manager($courseId);
$errors = array();

$quest = db_one(
    "SELECT * FROM quests
     WHERE questId = '" . db_escape($questId) . "'
       AND courseId = '" . db_escape($courseId) . "'
     LIMIT 1"
);

if (!$quest) {
    http_response_code(404);
    exit('Quest not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Invalid form token. Please try again.';
    }

    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $link = isset($_POST['link']) ? trim($_POST['link']) : '';
    $xpValue = isset($_POST['XPValue']) ? (int) $_POST['XPValue'] : 0;
    $deadline = isset($_POST['deadline']) ? trim($_POST['deadline']) : '';
    $questType = isset($_POST['questType']) ? $_POST['questType'] : 'individual';
    $isCompulsory = isset($_POST['isCompulsory']) ? 1 : 0;
    $status = isset($_POST['status']) ? $_POST['status'] : 'active';

    if ($title === '' || strlen($title) > 150) {
        $errors[] = 'Quest title is required and must be 150 characters or fewer.';
    }

    if ($xpValue < 0 || $xpValue > 10000) {
        $errors[] = 'XP value must be between 0 and 10000.';
    }

    if (!in_array($questType, array('individual', 'group', 'external', 'offline'), true)) {
        $errors[] = 'Invalid quest type.';
    }

    if (!in_array($status, array('active', 'archived'), true)) {
        $errors[] = 'Invalid quest status.';
    }

    $deadlineSql = 'NULL';
    if ($deadline !== '') {
        $deadlineSql = "'" . db_escape($deadline . ' 23:59:59') . "'";
    }

    if (!$errors) {
        db_exec(
            "UPDATE quests
             SET title = '" . db_escape($title) . "',
                 description = '" . db_escape($description) . "',
                 link = '" . db_escape($link) . "',
                 XPValue = " . (int) $xpValue . ",
                 deadline = " . $deadlineSql . ",
                 questType = '" . db_escape($questType) . "',
                 isCompulsory = " . (int) $isCompulsory . ",
                 status = '" . db_escape($status) . "',
                 updatedAt = NOW()
             WHERE questId = '" . db_escape($questId) . "'
               AND courseId = '" . db_escape($courseId) . "'"
        );

        flash('success', 'Quest updated.');
        redirect_to('quests.php?courseId=' . urlencode($courseId));
    }
}

$dateValue = '';
if ($quest['deadline']) {
    $dateValue = substr($quest['deadline'], 0, 10);
}

page_header('Edit Quest');
?>
<section class="panel">
    <div class="section-head">
        <div>
            <p class="eyebrow">Edit Quest</p>
            <h1><?php echo h($quest['title']); ?></h1>
            <p class="muted">Update quest details or archive this activity.</p>
        </div>
        <a class="button secondary" href="quests.php?courseId=<?php echo h($courseId); ?>">Back to quests</a>
    </div>
    <?php render_errors($errors); ?>
</section>

<section class="panel mt">
    <form method="post" action="quest_edit.php?courseId=<?php echo h($courseId); ?>&questId=<?php echo h($questId); ?>">
        <?php echo csrf_field(); ?>

        <div class="grid two-col tight">
            <div>
                <label for="title">Quest title</label>
                <input id="title" name="title" type="text" maxlength="150" value="<?php echo h($quest['title']); ?>" required>
            </div>
            <div>
                <label for="XPValue">XP value</label>
                <input id="XPValue" name="XPValue" type="number" min="0" max="10000" value="<?php echo h($quest['XPValue']); ?>" required>
            </div>
        </div>

        <label for="description">Description</label>
        <textarea id="description" name="description" rows="4"><?php echo h($quest['description']); ?></textarea>

        <div class="grid three-col tight">
            <div>
                <label for="link">External link</label>
                <input id="link" name="link" type="text" maxlength="255" value="<?php echo h($quest['link']); ?>">
            </div>
            <div>
                <label for="deadline">Deadline</label>
                <input id="deadline" name="deadline" type="date" value="<?php echo h($dateValue); ?>">
            </div>
            <div>
                <label for="questType">Type</label>
                <select id="questType" name="questType">
                    <option value="individual" <?php echo $quest['questType'] === 'individual' ? 'selected' : ''; ?>>Individual</option>
                    <option value="group" <?php echo $quest['questType'] === 'group' ? 'selected' : ''; ?>>Group</option>
                    <option value="external" <?php echo $quest['questType'] === 'external' ? 'selected' : ''; ?>>External tool</option>
                    <option value="offline" <?php echo $quest['questType'] === 'offline' ? 'selected' : ''; ?>>Offline activity</option>
                </select>
            </div>
        </div>

        <div class="grid two-col tight">
            <label class="check">
                <input type="checkbox" name="isCompulsory" value="1" <?php echo (int) $quest['isCompulsory'] === 1 ? 'checked' : ''; ?>>
                Compulsory quest
            </label>
            <div>
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="active" <?php echo $quest['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="archived" <?php echo $quest['status'] === 'archived' ? 'selected' : ''; ?>>Archived</option>
                </select>
            </div>
        </div>

        <div class="form-footer">
            <button type="submit">Save quest</button>
        </div>
    </form>
</section>
<?php page_footer(); ?>

