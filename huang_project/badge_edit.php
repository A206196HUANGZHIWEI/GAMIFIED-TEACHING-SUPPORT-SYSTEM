<?php
require __DIR__ . '/includes/gtss.php';
require __DIR__ . '/includes/layout.php';

$courseId = isset($_GET['courseId']) ? $_GET['courseId'] : '';
$badgeId = isset($_GET['badgeId']) ? $_GET['badgeId'] : '';
$course = require_course_manager($courseId);
$errors = array();

$badge = db_one(
    "SELECT * FROM badges
     WHERE badgeId = '" . db_escape($badgeId) . "'
       AND courseId = '" . db_escape($courseId) . "'
     LIMIT 1"
);

if (!$badge) {
    http_response_code(404);
    exit('Badge not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Invalid form token. Please try again.';
    }

    $action = isset($_POST['action']) ? $_POST['action'] : 'update';

    if (!$errors && $action === 'delete') {
        $awarded = db_value("SELECT COUNT(*) FROM student_badges WHERE badgeId = '" . db_escape($badgeId) . "'", 0);
        if ((int) $awarded > 0) {
            $errors[] = 'This badge has already been awarded and cannot be deleted.';
        } else {
            db_exec("DELETE FROM badges WHERE badgeId = '" . db_escape($badgeId) . "' AND courseId = '" . db_escape($courseId) . "'");
            flash('success', 'Badge deleted.');
            redirect_to('badges.php?courseId=' . urlencode($courseId));
        }
    }

    if (!$errors && $action === 'update') {
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $iconPath = isset($_POST['iconPath']) ? trim($_POST['iconPath']) : '';
        $criteriaType = isset($_POST['criteriaType']) ? $_POST['criteriaType'] : 'xp';
        $threshold = isset($_POST['threshold']) ? (int) $_POST['threshold'] : 0;
        $removeBadgeImage = isset($_POST['removeBadgeImage']) ? true : false;

        if ($name === '' || strlen($name) > 100) {
            $errors[] = 'Badge name is required and must be 100 characters or fewer.';
        }

        if (!in_array($criteriaType, array('xp', 'manual'), true)) {
            $errors[] = 'Invalid badge criteria.';
        }

        if ($threshold < 0 || $threshold > 100000) {
            $errors[] = 'Threshold must be between 0 and 100000.';
        }

        if (!$errors) {
            if ($removeBadgeImage) {
                $iconPath = '';
            } elseif ($iconPath === '') {
                $iconPath = isset($badge['iconPath']) ? $badge['iconPath'] : '';
            }

            $uploadedIcon = save_uploaded_badge_icon('badgeImage', $courseId, $badgeId, $errors);
            if (!$errors && $uploadedIcon !== '') {
                $iconPath = $uploadedIcon;
            }
        }

        if (!$errors) {
            db_exec(
                "UPDATE badges
                 SET name = '" . db_escape($name) . "',
                     description = '" . db_escape($description) . "',
                     iconPath = '" . db_escape($iconPath) . "',
                     criteriaType = '" . db_escape($criteriaType) . "',
                     threshold = " . (int) $threshold . ",
                     updatedAt = NOW()
                 WHERE badgeId = '" . db_escape($badgeId) . "'
                   AND courseId = '" . db_escape($courseId) . "'"
            );
            flash('success', 'Badge updated.');
            redirect_to('badges.php?courseId=' . urlencode($courseId));
        }
    }
}

page_header('Edit Badge');
?>
<section class="panel">
    <div class="section-head">
        <div>
            <p class="eyebrow">Edit Badge</p>
            <h1><?php echo h($badge['name']); ?></h1>
            <p class="muted">Update badge criteria or delete unused badges.</p>
        </div>
        <a class="button secondary" href="badges.php?courseId=<?php echo h($courseId); ?>">Back to badges</a>
    </div>
    <?php render_errors($errors); ?>
</section>

<section class="grid two-col mt">
    <div class="panel">
        <h2>Edit badge</h2>
        <form method="post" action="badge_edit.php?courseId=<?php echo h($courseId); ?>&badgeId=<?php echo h($badgeId); ?>" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="update">

            <label for="name">Badge name</label>
            <input id="name" name="name" type="text" maxlength="100" value="<?php echo h($badge['name']); ?>" required>

            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4"><?php echo h($badge['description']); ?></textarea>

            <div class="grid two-col tight">
                <div>
                    <label for="criteriaType">Criteria</label>
                    <select id="criteriaType" name="criteriaType">
                        <option value="xp" <?php echo $badge['criteriaType'] === 'xp' ? 'selected' : ''; ?>>XP threshold</option>
                        <option value="manual" <?php echo $badge['criteriaType'] === 'manual' ? 'selected' : ''; ?>>Manual award</option>
                    </select>
                </div>
                <div>
                    <label for="threshold">XP threshold</label>
                    <input id="threshold" name="threshold" type="number" min="0" max="100000" value="<?php echo h($badge['threshold']); ?>">
                </div>
            </div>

            <label for="iconPath">Icon label or image URL</label>
            <input id="iconPath" name="iconPath" type="text" maxlength="255" value="<?php echo h($badge['iconPath']); ?>">

            <label for="badgeImage">Badge image</label>
            <input id="badgeImage" name="badgeImage" type="file" accept=".jpg,.jpeg,.png,.gif,image/jpeg,image/png,image/gif">
            <p class="muted">Optional JPG, PNG, or GIF. Maximum 2 MB. Uploaded image replaces the current icon.</p>

            <?php if ($badge['iconPath']): ?>
                <div class="badge-preview-block">
                    <?php echo layout_badge_icon($badge['iconPath'], $badge['name'], 'badge-image large'); ?>
                    <span>Current badge icon</span>
                </div>
                <label class="check">
                    <input type="checkbox" name="removeBadgeImage" value="1">
                    Remove current badge image or icon label
                </label>
            <?php endif; ?>

            <div class="form-footer">
                <button type="submit">Save badge</button>
            </div>
        </form>
    </div>

    <div class="panel">
        <h2>Delete badge</h2>
        <p class="muted">Badges that have already been awarded are kept for record integrity.</p>
        <form method="post" action="badge_edit.php?courseId=<?php echo h($courseId); ?>&badgeId=<?php echo h($badgeId); ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="delete">
            <div class="form-footer">
                <button type="submit">Delete unused badge</button>
            </div>
        </form>
    </div>
</section>
<?php page_footer(); ?>
