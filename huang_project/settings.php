<?php
require __DIR__ . '/includes/gtss.php';
require __DIR__ . '/includes/layout.php';

require_role('admin');
$errors = array();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    $action = isset($_POST['action']) ? $_POST['action'] : 'save_settings';

    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Invalid form token. Please try again.';
    }

    if (!$errors && $action === 'test_email') {
        if (system_setting('email_notifications_enabled', '0') !== '1') {
            flash('error', 'Email notifications are disabled. Enable and save them first.');
        } elseif (send_email_notification($user['userId'], 'Test email notification', 'This message confirms that the server mail function accepted a GTSS test notification.')) {
            flash('success', 'Test email was accepted by the server mail function.');
        } else {
            flash('error', 'Test email could not be sent. Check the sender email and server mail configuration.');
        }

        redirect_to('settings.php');
    }

    if (!$errors && $action === 'save_settings') {
        $institutionName = isset($_POST['institution_name']) ? trim($_POST['institution_name']) : '';
        $themeColor = isset($_POST['theme_color']) ? trim($_POST['theme_color']) : '#1f7a5c';
        $defaultXpPolicy = isset($_POST['default_xp_policy']) ? trim($_POST['default_xp_policy']) : '';
        $privacyNote = isset($_POST['privacy_note']) ? trim($_POST['privacy_note']) : '';
        $emailNotificationsEnabled = isset($_POST['email_notifications_enabled']) ? '1' : '0';
        $notificationEmailFrom = isset($_POST['notification_email_from']) ? trim($_POST['notification_email_from']) : '';
        $removeLogo = isset($_POST['remove_logo']);
        $newLogoPath = '';

        if ($institutionName === '' || strlen($institutionName) > 100) {
            $errors[] = 'Institution name is required and must be 100 characters or fewer.';
        }

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $themeColor)) {
            $errors[] = 'Theme color must be a hex value like #1f7a5c.';
        }

        if ($notificationEmailFrom !== '' && !filter_var($notificationEmailFrom, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Notification email sender must be a valid email address.';
        }

        if (!$errors) {
            $newLogoPath = save_uploaded_system_logo('logo_image', $errors);
        }

        if (!$errors) {
            save_system_setting('institution_name', $institutionName);
            save_system_setting('theme_color', $themeColor);
            save_system_setting('default_xp_policy', $defaultXpPolicy);
            save_system_setting('privacy_note', $privacyNote);
            save_system_setting('email_notifications_enabled', $emailNotificationsEnabled);
            save_system_setting('notification_email_from', $notificationEmailFrom);

            if ($removeLogo) {
                save_system_setting('logo_path', '');
            } elseif ($newLogoPath !== '') {
                save_system_setting('logo_path', $newLogoPath);
            }

            flash('success', 'System settings saved.');
            redirect_to('settings.php');
        }
    }
}

$institutionName = system_setting('institution_name', 'GTSS');
$themeColor = system_setting('theme_color', '#1f7a5c');
$defaultXpPolicy = system_setting('default_xp_policy', 'Completed quest earns its configured XP value. Bonus XP may be added by the teacher.');
$privacyNote = system_setting('privacy_note', 'XP and badges are formative indicators and are not official examination grades.');
$emailNotificationsEnabled = system_setting('email_notifications_enabled', '0');
$notificationEmailFrom = system_setting('notification_email_from', '');
$logoPath = system_setting('logo_path', '');

page_header('Settings');
?>
<section class="panel">
    <p class="eyebrow">System Configuration</p>
    <h1>Settings</h1>
    <p class="muted">Configure institutional branding and default gamification notes.</p>
    <?php render_errors($errors); ?>
</section>

<section class="panel mt">
    <form method="post" action="settings.php" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="save_settings">

        <label for="institution_name">Institution / system name</label>
        <input id="institution_name" name="institution_name" type="text" maxlength="100" value="<?php echo h($institutionName); ?>" required>

        <label for="theme_color">Theme color</label>
        <input id="theme_color" name="theme_color" type="text" maxlength="7" value="<?php echo h($themeColor); ?>">

        <label for="logo_image">System logo</label>
        <?php if ($logoPath !== ''): ?>
            <div class="brand-preview">
                <?php echo layout_brand_mark($logoPath, $institutionName); ?>
                <span><?php echo h($logoPath); ?></span>
            </div>
        <?php endif; ?>
        <input id="logo_image" name="logo_image" type="file" accept="image/*">
        <?php if ($logoPath !== ''): ?>
            <label class="check">
                <input type="checkbox" name="remove_logo" value="1">
                Remove current logo
            </label>
        <?php endif; ?>

        <label for="default_xp_policy">Default XP policy</label>
        <textarea id="default_xp_policy" name="default_xp_policy" rows="4"><?php echo h($defaultXpPolicy); ?></textarea>

        <label for="privacy_note">Privacy / assessment note</label>
        <textarea id="privacy_note" name="privacy_note" rows="4"><?php echo h($privacyNote); ?></textarea>

        <label class="check">
            <input type="checkbox" name="email_notifications_enabled" value="1" <?php echo $emailNotificationsEnabled === '1' ? 'checked' : ''; ?>>
            Send email notifications using server mail()
        </label>

        <label for="notification_email_from">Notification sender email</label>
        <input id="notification_email_from" name="notification_email_from" type="email" maxlength="190" value="<?php echo h($notificationEmailFrom); ?>" placeholder="noreply@example.com">

        <div class="form-footer">
            <button type="submit">Save settings</button>
        </div>
    </form>
</section>

<section class="panel mt">
    <div class="section-head">
        <div>
            <h2>Email test</h2>
            <p class="muted">Sends a test email to your administrator account using the server mail() function.</p>
        </div>
        <form method="post" action="settings.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="test_email">
            <button type="submit">Send test email</button>
        </form>
    </div>
</section>
<?php page_footer(); ?>
