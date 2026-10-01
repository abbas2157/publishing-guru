<?php
/** Admin sign-in page. Expects $error, $username. */
$pageTitle = 'Sign in';
include __DIR__ . '/../head.php';
?>
<body class="login">
    <section class="login-art" style="background-image: url('<?= asset('hero-bg.webp') ?>');">
        <div class="login-art-inner">
            <img src="<?= asset('assets/publishing-guru-logo.webp') ?>" width="520" height="216" alt="Publishing Guru" class="login-logo">
            <img src="<?= asset('assets/hero-hand-writing-BtVw-6Hy.svg') ?>" width="489" height="378" alt="" class="login-illustration">
            <p class="login-quote">Every query is a book waiting to be published.</p>
        </div>
    </section>

    <section class="login-panel">
        <div class="login-card">
            <img src="<?= asset('assets/publishing-guru-logo.webp') ?>" width="520" height="216" alt="Publishing Guru" class="login-logo-mobile">
            <h1>Welcome back</h1>
            <p class="muted">Sign in to view and manage customer queries.</p>

<?php if ($error): ?>
            <div class="alert" role="alert"><?= e($error) ?></div>
<?php endif; ?>

            <form method="post" action="<?= url('/admin/login') ?>" class="login-form">
                <?= csrf_field() ?>
                <label class="field">
                    <span>Username</span>
                    <span class="input-wrap"><?= admin_icon('user') ?><input type="text" name="username" value="<?= e($username) ?>" autocomplete="username" required autofocus></span>
                </label>
                <label class="field">
                    <span>Password</span>
                    <span class="input-wrap"><?= admin_icon('lock') ?><input type="password" name="password" autocomplete="current-password" required></span>
                </label>
                <button type="submit" class="btn btn-primary btn-block">Sign in <img src="<?= asset('assets/Arrow-dH2l6ufH.svg') ?>" width="37" height="9" alt="" class="btn-arrow"></button>
            </form>

            <a href="<?= url('/') ?>" class="back-link"><?= admin_icon('arrow-left') ?> Back to website</a>
        </div>
    </section>
</body>
</html>
