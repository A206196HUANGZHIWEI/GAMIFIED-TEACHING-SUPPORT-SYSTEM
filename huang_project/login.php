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
$email = '';
$success = get_flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? (string) $_POST['password'] : '';
    $csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Invalid form token. Please try again.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    }

    if (!$errors) {
        $connection = db();
        $stmt = $connection->prepare('SELECT userId, name, email, passwordHash, role FROM users WHERE email = ? LIMIT 1');

        if (!$stmt) {
            $errors[] = 'Database query failed.';
        } else {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->store_result();

            $userId = '';
            $name = '';
            $storedEmail = '';
            $passwordHash = '';
            $role = '';
            $stmt->bind_result($userId, $name, $storedEmail, $passwordHash, $role);
            $userFound = $stmt->fetch();
            $stmt->close();
        }

        if (!$errors && $userFound && password_verify($password, $passwordHash)) {
            session_regenerate_id(true);
            $_SESSION['user'] = array(
                'userId' => $userId,
                'name' => $name,
                'email' => $storedEmail,
                'role' => $role,
            );
            redirect_to('index.php');
        }

        if (!$errors) {
            $errors[] = 'Email or password is incorrect.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - <?php echo h($appName); ?></title>
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
            <a href="register.php">Register</a>
        </nav>
    </header>

    <main class="auth-main">
        <section class="auth-card">
            <h1>Login to <?php echo h($appName); ?></h1>

            <?php if ($success): ?>
                <div class="alert success"><?php echo h($success); ?></div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="alert error">
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo h($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form class="auth-form" method="post" action="login.php">
                <?php echo csrf_field(); ?>

                <label class="sr-only" for="email">Email</label>
                <div class="input-with-icon">
                    <span>U</span>
                    <input id="email" name="email" type="email" maxlength="190" value="<?php echo h($email); ?>" placeholder="Username or Email" required>
                </div>

                <label class="sr-only" for="password">Password</label>
                <div class="input-with-icon">
                    <span>P</span>
                    <input id="password" name="password" type="password" placeholder="Password" required>
                </div>

                <button class="auth-button" type="submit">Login</button>
                <p class="auth-switch">Do not have an account? <a href="register.php">Register here</a></p>
            </form>
        </section>
    </main>
</body>
</html>
