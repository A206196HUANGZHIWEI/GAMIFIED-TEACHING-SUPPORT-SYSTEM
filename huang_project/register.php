<?php
require __DIR__ . '/includes/gtss.php';
require __DIR__ . '/includes/layout.php';

start_app_session();
require_guest();

$appName = system_setting('institution_name', app_config('app_name', 'GTSS'));
$logoPath = system_setting('logo_path', '');
$styleVersion = @filemtime(__DIR__ . '/assets/style.css');
if (!$styleVersion) {
    $styleVersion = '1';
}
$errors = array();
$old = array(
    'name' => '',
    'email' => '',
    'role' => 'student',
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['name'] = isset($_POST['name']) ? trim($_POST['name']) : '';
    $old['email'] = isset($_POST['email']) ? trim($_POST['email']) : '';
    $old['role'] = isset($_POST['role']) ? trim($_POST['role']) : 'student';
    $password = isset($_POST['password']) ? (string) $_POST['password'] : '';
    $passwordConfirm = isset($_POST['password_confirm']) ? (string) $_POST['password_confirm'] : '';
    $csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Invalid form token. Please try again.';
    }

    if ($old['name'] === '' || strlen($old['name']) > 100) {
        $errors[] = 'Name is required and must be 100 characters or fewer.';
    }

    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (!in_array($old['role'], array('student', 'teacher'), true)) {
        $errors[] = 'Please select a valid role.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if ($password !== $passwordConfirm) {
        $errors[] = 'Password confirmation does not match.';
    }

    if (!$errors) {
        $connection = db();
        $stmt = $connection->prepare('SELECT userId FROM users WHERE email = ? LIMIT 1');

        if (!$stmt) {
            $errors[] = 'Database query failed.';
        } else {
            $email = $old['email'];
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->store_result();
        }

        if (!$errors && $stmt->num_rows > 0) {
            $errors[] = 'This email address is already registered.';
        } elseif (!$errors) {
            $stmt->close();
            $stmt = $connection->prepare(
                'INSERT INTO users (userId, name, email, passwordHash, role, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, NOW(), NOW())'
            );

            if (!$stmt) {
                $errors[] = 'Database query failed.';
            } else {
                $userId = uuid_v4();
                $name = $old['name'];
                $email = $old['email'];
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $role = $old['role'];
                $stmt->bind_param('sssss', $userId, $name, $email, $passwordHash, $role);

                if ($stmt->execute()) {
                    flash('success', 'Account created. Please log in.');
                    redirect_to('login.php');
                }

                $errors[] = 'Account could not be created. Please try again.';
            }
        }

        if ($stmt) {
            $stmt->close();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register - <?php echo h($appName); ?></title>
    <link rel="stylesheet" href="assets/style.css?v=<?php echo h($styleVersion); ?>">
</head>
<body class="auth-body">
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
        </nav>
    </header>

    <main class="auth-main">
        <section class="auth-card">
            <h1>Register for <?php echo h($appName); ?></h1>

            <?php if ($errors): ?>
                <div class="alert error">
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo h($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form class="auth-form" method="post" action="register.php">
                <?php echo csrf_field(); ?>

                <label class="sr-only" for="name">Name</label>
                <div class="input-with-icon">
                    <span>U</span>
                    <input id="name" name="name" type="text" maxlength="100" value="<?php echo h($old['name']); ?>" placeholder="Full Name" required>
                </div>

                <label class="sr-only" for="email">Email</label>
                <div class="input-with-icon">
                    <span>M</span>
                    <input id="email" name="email" type="email" maxlength="190" value="<?php echo h($old['email']); ?>" placeholder="Email Address" required>
                </div>

                <label class="sr-only" for="role">Role</label>
                <div class="input-with-icon">
                    <span>R</span>
                    <select id="role" name="role" required>
                        <option value="student" <?php echo $old['role'] === 'student' ? 'selected' : ''; ?>>Student</option>
                        <option value="teacher" <?php echo $old['role'] === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                    </select>
                </div>

                <label class="sr-only" for="password">Password</label>
                <div class="input-with-icon">
                    <span>P</span>
                    <input id="password" name="password" type="password" minlength="8" placeholder="Password" required>
                </div>

                <label class="sr-only" for="password_confirm">Confirm Password</label>
                <div class="input-with-icon">
                    <span>P</span>
                    <input id="password_confirm" name="password_confirm" type="password" minlength="8" placeholder="Confirm Password" required>
                </div>

                <button class="auth-button" type="submit">Register Account</button>
                <p class="auth-switch">Already have an account? <a href="login.php">Login here</a></p>
            </form>
        </section>
    </main>
</body>
</html>
