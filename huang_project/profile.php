<?php
require __DIR__ . '/includes/gtss.php';
require __DIR__ . '/includes/layout.php';

require_login();

$sessionUser = current_user();
$profile = user_profile($sessionUser['userId']);
if (!$profile) {
    http_response_code(404);
    exit('User not found.');
}

$errors = array();
$earnedBadges = user_earned_badges($sessionUser['userId']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Invalid form token. Please try again.';
    }

    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $profileTitle = isset($_POST['profileTitle']) ? trim($_POST['profileTitle']) : '';
    $equippedBadgeId = isset($_POST['equippedBadgeId']) ? trim($_POST['equippedBadgeId']) : '';
    $removeAvatar = isset($_POST['removeAvatar']) ? true : false;

    if ($name === '' || strlen($name) > 100) {
        $errors[] = 'Name is required and must be 100 characters or fewer.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (strlen($profileTitle) > 100) {
        $errors[] = 'Title must be 100 characters or fewer.';
    }

    if (!$errors) {
        $emailOwner = db_value(
            "SELECT userId FROM users
             WHERE email = '" . db_escape($email) . "'
               AND userId <> '" . db_escape($sessionUser['userId']) . "'
             LIMIT 1",
            ''
        );

        if ($emailOwner !== '') {
            $errors[] = 'This email address is already used by another account.';
        }
    }

    if (!$errors && !user_can_equip_badge($sessionUser['userId'], $equippedBadgeId)) {
        $errors[] = 'Please select one of your earned badges.';
    }

    $avatarPath = isset($profile['avatarPath']) ? $profile['avatarPath'] : '';
    if (!$errors) {
        if ($removeAvatar) {
            $avatarPath = '';
        }

        $uploadedAvatar = save_uploaded_avatar('avatarFile', $sessionUser['userId'], $errors);
        if (!$errors && $uploadedAvatar !== '') {
            $avatarPath = $uploadedAvatar;
        }
    }

    if (!$errors) {
        $badgeSql = $equippedBadgeId === '' ? 'NULL' : "'" . db_escape($equippedBadgeId) . "'";
        db_exec(
            "UPDATE users
             SET name = '" . db_escape($name) . "',
                 email = '" . db_escape($email) . "',
                 avatarPath = '" . db_escape($avatarPath) . "',
                 profileTitle = '" . db_escape($profileTitle) . "',
                 equippedBadgeId = " . $badgeSql . ",
                 updatedAt = NOW()
             WHERE userId = '" . db_escape($sessionUser['userId']) . "'"
        );

        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['email'] = $email;

        flash('success', 'Profile updated.');
        redirect_to('profile.php');
    }

    $profile['name'] = $name;
    $profile['email'] = $email;
    $profile['profileTitle'] = $profileTitle;
    $profile['equippedBadgeId'] = $equippedBadgeId;
    $profile['avatarPath'] = $avatarPath;
}

$equippedBadgeName = '';
$equippedBadgeIcon = '';
foreach ($earnedBadges as $badge) {
    if (isset($profile['equippedBadgeId']) && $profile['equippedBadgeId'] === $badge['badgeId']) {
        $equippedBadgeName = $badge['name'];
        $equippedBadgeIcon = $badge['iconPath'];
        break;
    }
}

page_header('Profile');
?>
<section class="panel">
    <div class="section-head">
        <div>
            <p class="eyebrow">Personal Profile</p>
            <h1>Profile</h1>
            <p class="muted">Update your account details, avatar, display title, and equipped badge.</p>
        </div>
        <a class="button secondary" href="dashboard.php">Back to dashboard</a>
    </div>
    <?php render_errors($errors); ?>
</section>

<section class="grid two-col mt">
    <div class="panel">
        <h2>Edit profile</h2>
        <form method="post" action="profile.php" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>

            <label for="name">Full name</label>
            <input id="name" name="name" type="text" maxlength="100" value="<?php echo h($profile['name']); ?>" required>

            <label for="email">Email address</label>
            <input id="email" name="email" type="email" maxlength="190" value="<?php echo h($profile['email']); ?>" required>

            <label for="profileTitle">Display title</label>
            <input id="profileTitle" name="profileTitle" type="text" maxlength="100" value="<?php echo h(isset($profile['profileTitle']) ? $profile['profileTitle'] : ''); ?>" placeholder="Example: Algorithm Explorer">

            <label for="equippedBadgeId">Equipped badge</label>
            <select id="equippedBadgeId" name="equippedBadgeId">
                <option value="">No badge equipped</option>
                <?php foreach ($earnedBadges as $badge): ?>
                    <option value="<?php echo h($badge['badgeId']); ?>" <?php echo isset($profile['equippedBadgeId']) && $profile['equippedBadgeId'] === $badge['badgeId'] ? 'selected' : ''; ?>>
                        <?php echo h($badge['name'] . ' - ' . $badge['courseTitle']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="avatarFile">Avatar image</label>
            <input id="avatarFile" name="avatarFile" type="file" accept=".jpg,.jpeg,.png,.gif,image/jpeg,image/png,image/gif">
            <p class="muted">JPG, PNG, or GIF. Maximum 2 MB.</p>

            <?php if (isset($profile['avatarPath']) && $profile['avatarPath'] !== ''): ?>
                <label class="check">
                    <input type="checkbox" name="removeAvatar" value="1">
                    Remove current avatar
                </label>
            <?php endif; ?>

            <div class="form-footer">
                <button type="submit">Save profile</button>
            </div>
        </form>
    </div>

    <div class="panel">
        <h2>Profile preview</h2>
        <div class="profile-preview">
            <?php if (isset($profile['avatarPath']) && $profile['avatarPath'] !== ''): ?>
                <span class="profile-avatar"><img src="<?php echo h($profile['avatarPath']); ?>" alt="<?php echo h($profile['name']); ?>"></span>
            <?php else: ?>
                <span class="profile-avatar"><?php echo h(layout_initials($profile['name'])); ?></span>
            <?php endif; ?>
            <div>
                <strong><?php echo h($profile['name']); ?></strong>
                <span><?php echo h(role_label($profile['role'])); ?></span>
                <?php if (isset($profile['profileTitle']) && $profile['profileTitle'] !== ''): ?>
                    <em><?php echo h($profile['profileTitle']); ?></em>
                <?php endif; ?>
                <?php if ($equippedBadgeName !== ''): ?>
                    <p class="equipped-badge-line">
                        <?php echo layout_badge_icon($equippedBadgeIcon, $equippedBadgeName, 'mini-badge-icon'); ?>
                        <span><?php echo h($equippedBadgeName); ?></span>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="mt">
            <h2>Earned badges</h2>
            <?php if (!$earnedBadges): ?>
                <p class="muted">No earned badges yet.</p>
            <?php else: ?>
                <div class="list">
                    <?php foreach ($earnedBadges as $badge): ?>
                        <div class="list-item">
                            <div class="badge-card-head compact">
                                <?php echo layout_badge_icon($badge['iconPath'], $badge['name'], 'badge-image small'); ?>
                                <div>
                                    <strong><?php echo h($badge['name']); ?></strong>
                                    <span><?php echo h($badge['courseTitle']); ?> - <?php echo h($badge['awardedAt']); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php page_footer(); ?>
