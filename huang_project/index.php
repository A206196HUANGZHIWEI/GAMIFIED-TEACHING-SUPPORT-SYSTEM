<?php
require __DIR__ . '/includes/gtss.php';

start_app_session();

if (current_user()) {
    redirect_to('dashboard.php');
}

require __DIR__ . '/includes/layout.php';

$appName = system_setting('institution_name', app_config('app_name', 'GTSS'));
page_header('Home');
?>
<section class="hero">
    <div class="panel">
        <p class="eyebrow">Gamified Teaching Support System</p>
        <h1>Manage gamified teaching from one web dashboard.</h1>
        <p class="muted"><?php echo h($appName); ?> helps teachers organise courses, quests, XP, badges, leaderboards, announcements, and progress analytics for secondary and tertiary learning contexts.</p>
        <div class="actions">
            <a class="button" href="login.php">Login</a>
            <a class="button secondary" href="register.php">Create account</a>
        </div>
    </div>
    <div class="panel">
        <h2>Core modules</h2>
        <ul class="plain-list">
            <li>Teacher, student, and administrator roles</li>
            <li>Course enrolment through class codes</li>
            <li>Quest-based activities with XP rewards</li>
            <li>Badges, leaderboards, and progress tracking</li>
        </ul>
    </div>
</section>
<?php page_footer(); ?>
