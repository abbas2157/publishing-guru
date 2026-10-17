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
                    <span class="input-wrap"><?= admin_icon('lock') ?><input type="password" name="password" id="admin-password" autocomplete="current-password" required><button type="button" class="pw-toggle" aria-controls="admin-password" aria-label="Show password" aria-pressed="false" data-password-toggle><span class="pw-show"><?= admin_icon('eye') ?></span><span class="pw-hide" hidden><?= admin_icon('eye-off') ?></span></button></span>
                </label>
                <button type="submit" class="btn btn-primary btn-block">Sign in <img src="<?= asset('assets/Arrow-dH2l6ufH.svg') ?>" width="37" height="9" alt="" class="btn-arrow"></button>
            </form>

            <a href="<?= url('/') ?>" class="back-link"><?= admin_icon('arrow-left') ?> Back to website</a>
            <script>
            // Show / hide the password. Hidden again before submitting, so browsers save it as a password.
            (function () {
                var btn = document.querySelector('[data-password-toggle]');
                var input = document.getElementById('admin-password');
                if (!btn || !input) return;
                var set = function (show) {
                    input.type = show ? 'text' : 'password';
                    btn.setAttribute('aria-pressed', show ? 'true' : 'false');
                    btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                    btn.querySelector('.pw-show').hidden = show;
                    btn.querySelector('.pw-hide').hidden = !show;
                };
                btn.addEventListener('click', function () {
                    var end = input.selectionEnd;
                    set(input.type === 'password');
                    input.focus();
                    try { input.setSelectionRange(end, end); } catch (e) {}
                });
                input.form.addEventListener('submit', function () { set(false); });
            })();
            </script>
        </div>
    </section>
</body>
</html>
