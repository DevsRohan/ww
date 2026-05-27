<?php
require_once __DIR__ . '/config/bootstrap.php';
Auth::startSession();
if (Auth::check()) { header('Location: dashboard.php'); exit; }

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_email($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    if (!$email || !$password) {
        $error = 'Please enter email and password.';
    } else if (Auth::login($email, $password)) {
        header('Location: dashboard.php'); exit;
    } else {
        $error = 'Invalid credentials or too many attempts. Try again later.';
    }
}
$pageTitle = 'Sign in';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="min-h-screen flex items-center justify-center px-4">
  <div class="w-full max-w-[400px]">
    <div class="flex items-center gap-3 mb-8 justify-center">
      <div class="w-10 h-10 rounded-xl bg-brand-500 flex items-center justify-center shadow-soft">
        <svg viewBox="0 0 24 24" class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8z"/></svg>
      </div>
      <div>
        <div class="font-semibold tracking-tight"><?= h($GLOBALS['APP']['app_name']) ?></div>
        <div class="text-[12px] text-ink-500 -mt-0.5"><?= h($GLOBALS['APP']['app_tagline']) ?></div>
      </div>
    </div>

    <div class="bg-white border border-ink-200 rounded-xl2 shadow-card p-7">
      <h1 class="text-[22px] font-semibold tracking-tight mb-1">Welcome back</h1>
      <p class="text-sm text-ink-500 mb-6">Sign in to your operations workspace.</p>

      <?php if ($error): ?>
      <div class="mb-4 px-3 py-2 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700"><?= h($error) ?></div>
      <?php endif; ?>

      <form method="post" autocomplete="on">
        <label class="block text-xs font-medium text-ink-700 mb-1.5">Email</label>
        <input type="email" name="email" required autofocus class="input mb-4" placeholder="you@company.com" value="<?= h($_POST['email'] ?? '') ?>"/>

        <label class="block text-xs font-medium text-ink-700 mb-1.5">Password</label>
        <input type="password" name="password" required class="input mb-5" placeholder="••••••••"/>

        <button type="submit" class="btn-primary w-full justify-center">Sign in</button>
      </form>

      <p class="text-[11px] text-ink-500 mt-6 text-center">First-time setup? Use the credentials provided during installation.</p>
    </div>
  </div>
</div>

</body>
</html>
