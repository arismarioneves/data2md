<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

/** @var \Data2Md\Config\Config $config */
$config = require BASE_PATH . '/src/bootstrap.php';
require BASE_PATH . '/src/I18n/bootstrap.php';
require BASE_PATH . '/src/Auth/bootstrap.php';

use Data2Md\Auth\LoginTokenStore;
use Data2Md\Auth\Session;
use Data2Md\Auth\UserStore;
use Data2Md\Http\ClientIp;
use Data2Md\Log\Logger;
use Data2Md\Mail\Mailer;
use Data2Md\Mail\SignInMessage;
use Data2Md\Security\RateLimiter;
use Data2Md\Storage\Database;

/** @var Session|null $session */
if ($session === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not found.\n";
    exit;
}

if ($session->isAuthenticated() && !isset($_GET['token']) && !isset($_POST['token'])) {
    header('Location: /dashboard', true, 303);
    exit;
}

$dataDir    = $config->string('APP_DATA_DIR');
$logger     = new Logger($dataDir . '/logs');
$database   = new Database($dataDir . '/db/mappings.sqlite');
$users      = new UserStore($database);
$tokens     = new LoginTokenStore($database, $config->int('LOGIN_TOKEN_TTL_SECONDS'));
$clientIp   = (new ClientIp($config->list('TRUSTED_PROXIES')))->resolve($_SERVER);
$loggedIp   = Logger::truncateIp($clientIp);
$ttlMinutes = (int) max(1, round($config->int('LOGIN_TOKEN_TTL_SECONDS') / 60));

// One state per render: the form, the "check your inbox" notice, the
// confirmation for a link that was clicked, or a dead link.
$state = 'form';
$error = null;
$linkEmail = null;

$rawToken = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$isPost   = $_SERVER['REQUEST_METHOD'] === 'POST';

if ($rawToken !== '') {
    $linkEmail = $tokens->peekEmail($rawToken);

    if ($linkEmail === null) {
        $state = 'dead';
    } elseif ($session->isAuthenticated() && $session->email() !== $linkEmail) {
        // Signing this visitor in as someone else would hand their later
        // conversions to whoever sent the link.
        $state = 'other_user';
    } elseif (!$isPost) {
        // A GET must never consume the token: mail scanners, link previewers
        // and corporate proxies all fetch it. The visitor clicks to confirm,
        // which is also the no-JS path.
        $state = 'confirm';
    } elseif ($tokens->consume($rawToken) === null) {
        $state = 'dead';
    } else {
        $tokens->invalidateOthersFor($linkEmail, LoginTokenStore::hash($rawToken));

        $userId = $users->ensure($linkEmail);
        $users->touchLastLogin($userId);
        $session->start($userId);

        $logger->app('auth.login.consume', ['user' => $userId, 'ip' => $loggedIp]);

        header('Location: /dashboard', true, 303);
        exit;
    }
} elseif ($isPost) {
    $email = trim((string) ($_POST['email'] ?? ''));

    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $error = t('auth.login.bad_email');
    } else {
        $email  = UserStore::normalizeEmail($email);
        $window = $config->int('LOGIN_WINDOW_SECONDS');

        $perEmail = new RateLimiter($dataDir . '/ratelimit', $config->int('LOGIN_MAX_PER_EMAIL'), $window);
        $perIp    = new RateLimiter($dataDir . '/ratelimit', $config->int('LOGIN_MAX_PER_IP'), $window);

        // Counted before the outcome is known, so a blocked attempt still
        // counts: otherwise the limit is a way to probe for addresses.
        $allowed = $perEmail->isAllowed('login:email:' . hash('sha256', $email))
                && $perIp->isAllowed('login:ip:' . $clientIp);

        if (!$allowed) {
            $logger->app('auth.login.reject', ['reason' => 'throttled', 'ip' => $loggedIp], 'warning');
            $error = t('auth.login.throttled');
        } else {
            $raw = $tokens->issue($email);
            $url = rtrim(APP_URL, '/') . '/login?token=' . urlencode($raw);

            $message = SignInMessage::build(APP_NAME, $url, $ttlMinutes);

            $sent = Mailer::fromConfig($config, $logger)->send(
                $email,
                $message->subject,
                $message->text,
                $message->html
            );

            $logger->app('auth.login.request', ['sent' => $sent, 'ip' => $loggedIp]);

            // Reported the same way whether or not the address is known, and
            // whether or not the relay accepted it.
            $state = 'sent';
        }
    }
}

// Authenticated or not, this page must not be cached by anything in front.
header('Cache-Control: private, no-store');
header('Vary: Cookie');
header('Content-Type: text/html; charset=utf-8');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data:");

$metaTitle = t('auth.login.title', APP_NAME);
$metaDesc  = t('auth.login.meta.desc', APP_NAME);
?>
<!DOCTYPE html>
<html lang="<?= \Data2Md\I18n\I18n::current() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <?php require BASE_PATH . '/src/View/head-meta.php'; ?>
    <link rel="stylesheet" href="/assets/css/landing.css">
    <link rel="stylesheet" href="/assets/css/auth.css">
</head>
<body>

<?php
$navLinks = [
    ['href' => '/',    'label' => t('nav.about'), 'hideSm' => true],
    ['href' => '/api', 'label' => t('nav.api')],
];
$navCta = ['href' => '/app', 'label' => t('nav.app'), 'icon' => 'arrow'];
require BASE_PATH . '/src/View/header.php';
?>

<main class="l-main auth-main">
    <div class="l-container">
        <div class="auth-card">
            <?php if ($state === 'form'): ?>
                <span class="l-eyebrow"><?= t('auth.login.eyebrow') ?></span>
                <h1 class="auth-h1"><?= t('auth.login.heading') ?></h1>
                <p class="auth-lead"><?= t('auth.login.lead') ?></p>

                <?php if ($error !== null): ?>
                <p class="auth-error" role="alert"><?= e($error) ?></p>
                <?php endif; ?>

                <form method="post" action="/login" class="auth-form">
                    <label class="auth-label" for="email"><?= t('auth.login.email') ?></label>
                    <input class="auth-input" type="email" id="email" name="email" required autofocus
                           autocomplete="email" inputmode="email" placeholder="you@example.com"
                           value="<?= e((string) ($_POST['email'] ?? '')) ?>">
                    <button type="submit" class="l-btn l-btn--primary auth-submit"><?= t('auth.login.submit') ?></button>
                </form>

            <?php elseif ($state === 'sent'): ?>
                <div class="auth-icon" aria-hidden="true">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4z"/><path d="m4 7 8 6 8-6"/></svg>
                </div>
                <h1 class="auth-h1"><?= t('auth.login.sent.head') ?></h1>
                <p class="auth-lead"><?= t('auth.login.sent.body', $ttlMinutes) ?></p>
                <a class="l-btn l-btn--ghost auth-submit" href="/login"><?= t('auth.login.sent.again') ?></a>

            <?php elseif ($state === 'confirm'): ?>
                <h1 class="auth-h1"><?= t('auth.confirm.head') ?></h1>
                <p class="auth-lead"><?= t('auth.confirm.body', e((string) $linkEmail)) ?></p>

                <form method="post" action="/login" class="auth-form">
                    <input type="hidden" name="token" value="<?= e($rawToken) ?>">
                    <button type="submit" class="l-btn l-btn--primary auth-submit">
                        <?= t('auth.confirm.btn', e((string) $linkEmail)) ?>
                    </button>
                </form>

            <?php elseif ($state === 'other_user'): ?>
                <h1 class="auth-h1"><?= t('auth.confirm.head') ?></h1>
                <p class="auth-lead"><?= t('auth.confirm.other', e((string) $session->email())) ?></p>

                <form method="post" action="/logout" class="auth-form">
                    <input type="hidden" name="csrf" value="<?= e((string) $session->csrfToken()) ?>">
                    <button type="submit" class="l-btn l-btn--ghost auth-submit"><?= t('nav.signout') ?></button>
                </form>

            <?php else: ?>
                <h1 class="auth-h1"><?= t('auth.dead.head') ?></h1>
                <p class="auth-lead"><?= t('auth.dead.body', $ttlMinutes) ?></p>
                <a class="l-btn l-btn--primary auth-submit" href="/login"><?= t('auth.dead.btn') ?></a>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php
$footerText  = t('footer.text', e(APP_NAME));
$footerLinks = [
    ['href' => '/terms',   'label' => t('nav.terms')],
    ['href' => '/privacy', 'label' => t('nav.privacy')],
];
require BASE_PATH . '/src/View/footer.php';
?>

</body>
</html>
