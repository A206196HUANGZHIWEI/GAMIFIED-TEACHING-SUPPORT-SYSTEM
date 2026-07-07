<?php

function start_app_session()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function app_config($key, $default)
{
    static $config = null;

    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }

    if (!is_array($config)) {
        return $default;
    }

    return isset($config[$key]) ? $config[$key] : $default;
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect_to($path)
{
    header('Location: ' . $path);
    exit;
}

function current_user()
{
    start_app_session();
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

function require_guest()
{
    if (current_user()) {
        redirect_to('index.php');
    }
}

function flash($key, $message)
{
    start_app_session();
    $_SESSION['flash'][$key] = $message;
}

function get_flash($key)
{
    start_app_session();

    if (!isset($_SESSION['flash'][$key])) {
        return '';
    }

    $message = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);

    return $message;
}

function csrf_token()
{
    start_app_session();

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(secure_random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf_token($token)
{
    start_app_session();

    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }

    return safe_hash_equals($_SESSION['csrf_token'], (string) $token);
}

function safe_hash_equals($known, $user)
{
    if (function_exists('hash_equals')) {
        return hash_equals($known, $user);
    }

    if (strlen($known) !== strlen($user)) {
        return false;
    }

    $result = 0;
    for ($i = 0; $i < strlen($known); $i++) {
        $result |= ord($known[$i]) ^ ord($user[$i]);
    }

    return $result === 0;
}

function secure_random_bytes($length)
{
    if (function_exists('random_bytes')) {
        return random_bytes($length);
    }

    if (function_exists('openssl_random_pseudo_bytes')) {
        return openssl_random_pseudo_bytes($length);
    }

    $bytes = '';
    for ($i = 0; $i < $length; $i++) {
        $bytes .= chr(mt_rand(0, 255));
    }

    return $bytes;
}

function uuid_v4()
{
    $data = secure_random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function role_label($role)
{
    $labels = array(
        'student' => 'Student',
        'teacher' => 'Teacher',
        'admin' => 'Administrator',
    );

    return isset($labels[$role]) ? $labels[$role] : 'User';
}
