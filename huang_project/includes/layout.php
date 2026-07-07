<?php

require_once __DIR__ . '/functions.php';

function layout_initials($name)
{
    $name = trim((string) $name);
    if ($name === '') {
        return 'U';
    }

    $parts = preg_split('/\s+/', $name);
    $initials = '';
    foreach ($parts as $part) {
        if ($part !== '') {
            $initials .= strtoupper(substr($part, 0, 1));
        }
        if (strlen($initials) >= 2) {
            break;
        }
    }

    return $initials !== '' ? $initials : 'U';
}

function layout_avatar($user, $class)
{
    $avatarPath = isset($user['avatarPath']) ? $user['avatarPath'] : '';
    $name = isset($user['name']) ? $user['name'] : '';

    if ($avatarPath !== '') {
        return '<span class="' . h($class) . '"><img src="' . h($avatarPath) . '" alt="' . h($name) . '"></span>';
    }

    return '<span class="' . h($class) . '">' . h(layout_initials($name)) . '</span>';
}

function layout_badge_icon($iconPath, $name, $class)
{
    $iconPath = trim((string) $iconPath);
    $name = trim((string) $name);

    if ($iconPath !== '' && preg_match('/\.(jpg|jpeg|png|gif)(\?.*)?$/i', $iconPath)) {
        return '<span class="' . h($class) . '"><img src="' . h($iconPath) . '" alt="' . h($name) . '"></span>';
    }

    $label = $iconPath !== '' ? $iconPath : $name;
    $label = strtoupper(substr($label, 0, 1));
    if ($label === '') {
        $label = 'B';
    }

    return '<span class="' . h($class) . '">' . h($label) . '</span>';
}

function layout_brand_mark($logoPath, $appName)
{
    $logoPath = trim((string) $logoPath);
    if ($logoPath !== '' && preg_match('/\.(jpg|jpeg|png|gif)(\?.*)?$/i', $logoPath)) {
        return '<span class="brand-logo-img"><img src="' . h($logoPath) . '" alt="' . h($appName) . '"></span>';
    }

    return '<span class="brand-gamepad"><span>G</span></span>';
}

function layout_current_page()
{
    $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    return basename($script);
}

function layout_active($pages)
{
    $current = layout_current_page();
    return in_array($current, $pages, true);
}

function layout_course_url($page, $courseId)
{
    if ($courseId === '') {
        return 'courses.php';
    }

    return $page . '?courseId=' . urlencode($courseId);
}

function layout_nav_link($href, $label, $icon, $active)
{
    $class = $active ? ' class="active"' : '';
    echo '<a' . $class . ' href="' . h($href) . '"><span class="nav-icon">' . h($icon) . '</span><span>' . h($label) . '</span></a>';
}

function layout_subnav_link($href, $label, $active)
{
    $class = $active ? ' class="active"' : '';
    echo '<a' . $class . ' href="' . h($href) . '">' . h($label) . '</a>';
}

function layout_course_context($courseId)
{
    if ($courseId === '' || !function_exists('get_course') || !function_exists('can_view_course')) {
        return null;
    }

    $course = get_course($courseId);
    if (!$course || !can_view_course($course)) {
        return null;
    }

    return $course;
}

function page_header($title)
{
    $appName = app_config('app_name', 'GTSS');
    if (function_exists('system_setting')) {
        $appName = system_setting('institution_name', $appName);
    }
    $logoPath = function_exists('system_setting') ? system_setting('logo_path', '') : '';

    $user = current_user();
    $displayUser = $user;
    if ($user && function_exists('user_profile')) {
        $profile = user_profile($user['userId']);
        if ($profile) {
            $displayUser = $profile;
        }
    }
    $themeColor = function_exists('system_setting') ? system_setting('theme_color', '#2b7de9') : '#2b7de9';
    $noticeCount = ($user && function_exists('unread_notification_count')) ? unread_notification_count($user['userId']) : 0;
    $chatCount = ($user && function_exists('chat_unread_count')) ? chat_unread_count($user['userId']) : 0;
    $courseId = isset($_GET['courseId']) ? $_GET['courseId'] : '';
    $courseContext = $user ? layout_course_context($courseId) : null;
    $canManageCourseContext = ($courseContext && function_exists('can_manage_course')) ? can_manage_course($courseContext) : false;
    $styleVersion = @filemtime(__DIR__ . '/../assets/style.css');
    if (!$styleVersion) {
        $styleVersion = '1';
    }

    $GLOBALS['gtss_layout_shell'] = $user ? true : false;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo h($title . ' - ' . $appName); ?></title>
    <link rel="stylesheet" href="assets/style.css?v=<?php echo h($styleVersion); ?>">
    <style>:root { --theme-accent: <?php echo h($themeColor); ?>; }</style>
</head>
<?php if ($user): ?>
<body class="app-body">
    <div class="app-shell">
        <aside class="sidebar">
            <a class="sidebar-brand" href="dashboard.php">
                <?php echo layout_brand_mark($logoPath, $appName); ?>
                <span>
                    <strong><?php echo h($appName); ?></strong>
                    <small>Gamified Teaching Support System</small>
                </span>
            </a>

            <nav class="side-nav">
                <?php layout_nav_link('dashboard.php', 'Dashboard', 'D', layout_active(array('dashboard.php'))); ?>
                <?php layout_nav_link('courses.php', in_array($user['role'], array('teacher', 'admin'), true) ? 'Manage Courses' : 'My Courses', 'C', layout_active(array('courses.php')) || $courseContext); ?>
                <?php if ($courseContext): ?>
                    <div class="course-subnav">
                        <div class="course-subnav-title">
                            <span>Selected course</span>
                            <strong title="<?php echo h($courseContext['title']); ?>"><?php echo h($courseContext['title']); ?></strong>
                        </div>
                        <?php layout_subnav_link(layout_course_url('course.php', $courseId), 'Course Home', layout_active(array('course.php'))); ?>
                        <?php layout_subnav_link(layout_course_url('quests.php', $courseId), in_array($user['role'], array('teacher', 'admin'), true) ? 'Manage Quests' : 'My Quests', layout_active(array('quests.php', 'quest_edit.php'))); ?>
                        <?php if ($canManageCourseContext): ?>
                            <?php layout_subnav_link(layout_course_url('results.php', $courseId), 'Record Results', layout_active(array('results.php'))); ?>
                        <?php endif; ?>
                        <?php layout_subnav_link(layout_course_url('progress.php', $courseId), $user['role'] === 'student' ? 'My Progress' : 'Student Progress', layout_active(array('progress.php', 'report.php'))); ?>
                        <?php layout_subnav_link(layout_course_url('badges.php', $courseId), $user['role'] === 'student' ? 'Achievements' : 'Badges', layout_active(array('badges.php', 'badge_edit.php'))); ?>
                        <?php layout_subnav_link(layout_course_url('reward_store.php', $courseId), 'XP Store', layout_active(array('reward_store.php', 'reward_item_edit.php'))); ?>
                        <?php if ($canManageCourseContext): ?>
                            <?php layout_subnav_link(layout_course_url('students.php', $courseId), 'Students', layout_active(array('students.php'))); ?>
                            <?php layout_subnav_link(layout_course_url('course_settings.php', $courseId), 'Course Settings', layout_active(array('course_settings.php'))); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php layout_nav_link('chat.php', $chatCount > 0 ? 'Messages (' . $chatCount . ')' : 'Messages', 'M', layout_active(array('chat.php'))); ?>
                <?php layout_nav_link('notifications.php', 'Notifications', 'N', layout_active(array('notifications.php', 'announcements.php', 'reflections.php'))); ?>
                <?php layout_nav_link('profile.php', 'Profile', 'U', layout_active(array('profile.php'))); ?>
                <?php if ($user['role'] === 'admin'): ?>
                    <?php layout_nav_link('admin.php', 'Administrator', 'A', layout_active(array('admin.php'))); ?>
                    <?php layout_nav_link('admin_analytics.php', 'Analytics', 'R', layout_active(array('admin_analytics.php'))); ?>
                    <?php layout_nav_link('reward_admin.php', 'Store Review', 'X', layout_active(array('reward_admin.php'))); ?>
                    <?php layout_nav_link('settings.php', 'Settings', 'S', layout_active(array('settings.php'))); ?>
                <?php endif; ?>
            </nav>

            <div class="sidebar-user">
                <a class="avatar-link" href="profile.php" aria-label="Profile">
                    <?php echo layout_avatar($displayUser, 'avatar'); ?>
                </a>
                <span>
                    <strong><?php echo h($displayUser['name']); ?></strong>
                    <?php if (isset($displayUser['profileTitle']) && $displayUser['profileTitle'] !== ''): ?>
                        <em><?php echo h($displayUser['profileTitle']); ?></em>
                    <?php elseif (isset($displayUser['equippedBadgeName']) && $displayUser['equippedBadgeName'] !== ''): ?>
                        <em><?php echo h($displayUser['equippedBadgeName']); ?></em>
                    <?php endif; ?>
                    <a href="profile.php">Profile</a>
                    <a href="logout.php">Logout</a>
                </span>
            </div>
        </aside>

        <div class="main-shell">
            <header class="app-topbar">
                <div class="topbar-tools" aria-hidden="true">
                    <span></span>
                    <span></span>
                </div>
                <form class="top-search" method="get" action="courses.php">
                    <input type="search" name="search" placeholder="Search..." aria-label="Search">
                </form>
                <a class="top-icon" href="notifications.php" aria-label="Notifications">
                    <span>N</span>
                    <?php if ($noticeCount > 0): ?>
                        <strong><?php echo h($noticeCount); ?></strong>
                    <?php endif; ?>
                </a>
                <a class="top-user" href="profile.php">
                    <span class="top-user-name"><?php echo h($displayUser['name']); ?></span>
                    <?php if ($displayUser['role'] === 'student' && isset($displayUser['equippedBadgeName']) && $displayUser['equippedBadgeName'] !== ''): ?>
                        <span class="top-equipped-badge">
                            <?php echo layout_badge_icon(isset($displayUser['equippedBadgeIcon']) ? $displayUser['equippedBadgeIcon'] : '', $displayUser['equippedBadgeName'], 'mini-badge-icon'); ?>
                            <span><?php echo h($displayUser['equippedBadgeName']); ?></span>
                        </span>
                    <?php endif; ?>
                    <?php echo layout_avatar($displayUser, 'avatar small'); ?>
                </a>
            </header>

            <main class="container">
                <?php render_messages(); ?>
<?php else: ?>
<body class="auth-body public-entry">
    <header class="auth-header">
        <a class="auth-logo" href="index.php">
            <?php echo layout_brand_mark($logoPath, $appName); ?>
            <span>
                <strong><?php echo h($appName); ?></strong>
                <small>Gamified Teaching Support System</small>
            </span>
        </a>
        <nav class="auth-nav">
            <a href="login.php">Login</a>
            <a href="register.php">Register</a>
        </nav>
    </header>

    <main class="container public-container">
        <?php render_messages(); ?>
<?php endif; ?>
<?php
}

function page_footer()
{
?>
    </main>
<?php if (!empty($GLOBALS['gtss_layout_shell'])): ?>
        </div>
    </div>
<?php endif; ?>
</body>
</html>
<?php
}

function render_messages()
{
    $success = get_flash('success');
    $error = get_flash('error');

    if ($success) {
        echo '<div class="alert success">' . h($success) . '</div>';
    }

    if ($error) {
        echo '<div class="alert error">' . h($error) . '</div>';
    }
}

function render_errors($errors)
{
    if (!$errors) {
        return;
    }

    echo '<div class="alert error">';
    foreach ($errors as $error) {
        echo '<div>' . h($error) . '</div>';
    }
    echo '</div>';
}

function status_badge($label)
{
    return '<span class="badge">' . h($label) . '</span>';
}
