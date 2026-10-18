<?php
declare(strict_types=1);
namespace Giftcheta\Modules\Customer;

use Giftcheta\Http\Request;
use Giftcheta\Http\Response;

final class PasswordResetPage
{
    public function show(Request $request): Response
    {
        $nonce = base64_encode(random_bytes(18));
        $html = <<<HTML
<!doctype html>
<html lang="bg">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="referrer" content="no-referrer">
  <title>Нова парола · Giftcheta</title>
  <style nonce="{$nonce}">
    *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#f6f7fb;color:#182230;font:16px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif}.card{width:min(440px,100%);padding:34px;background:#fff;border:1px solid #e2e7ef;border-radius:16px;box-shadow:0 18px 48px #17253a12}.brand{margin:0 0 24px;color:#2563eb;font-size:13px;font-weight:750;letter-spacing:.08em}.brand span{color:#182230}.card h1{margin:0 0 9px;font-size:25px;letter-spacing:-.03em}.intro{margin:0 0 24px;color:#667085}.field{display:grid;gap:7px;margin:16px 0}.field label{font-weight:600;font-size:14px}.field input{width:100%;height:46px;padding:0 12px;border:1px solid #cfd7e3;border-radius:8px;font:inherit}.field input:focus{outline:2px solid #2563eb55;border-color:#2563eb}button{width:100%;height:46px;margin-top:8px;border:0;border-radius:8px;background:#2563eb;color:white;font:600 15px system-ui,-apple-system,"Segoe UI",sans-serif;cursor:pointer}button:disabled{opacity:.65;cursor:wait}.message{margin:16px 0 0;padding:12px;border-radius:8px;background:#f2f4f7;color:#344054}.message.error{background:#fff0ef;color:#a12f27}.message.success{background:#edf9f1;color:#23633c}.hint{margin:12px 0 0;color:#777f8c;font-size:13px}
  </style>
</head>
<body>
  <main class="card">
    <p class="brand">GIFTCHETA <span>· КЛИЕНТСКИ АКАУНТ</span></p>
    <h1>Задайте нова парола</h1>
    <p class="intro">Изберете нова парола за клиентския си акаунт.</p>
    <form id="reset-form" autocomplete="on">
      <div class="field"><label for="password">Нова парола</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="72" required></div>
      <div class="field"><label for="password-confirmation">Повторете паролата</label><input id="password-confirmation" name="password-confirmation" type="password" autocomplete="new-password" minlength="12" maxlength="72" required></div>
      <p class="hint">Използвайте поне 12 знака. Не използвайте парола, която ползвате другаде.</p>
      <button id="submit-button" type="submit">Запази новата парола</button>
    </form>
    <div id="message" class="message" role="status" aria-live="polite" hidden></div>
  </main>
  <script nonce="{$nonce}">
    const token = new URLSearchParams(window.location.search).get('token') || '';
    window.history.replaceState(null, '', window.location.pathname);
    const form = document.getElementById('reset-form');
    const message = document.getElementById('message');
    const button = document.getElementById('submit-button');
    if (!/^[a-f0-9]{64}$/.test(token)) {
      form.hidden = true;
      message.hidden = false;
      message.className = 'message error';
      message.textContent = 'Линкът е невалиден или е изтекъл. Свържете се с магазина, за да получите нов.';
    }
    form.addEventListener('submit', async event => {
      event.preventDefault();
      if (!token) return;
      const password = document.getElementById('password').value;
      const confirmation = document.getElementById('password-confirmation').value;
      button.disabled = true;
      button.textContent = 'Запазване…';
      message.hidden = true;
      try {
        const response = await fetch('/api/v1/customer/password-reset', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          credentials: 'same-origin',
          body: JSON.stringify({token, password, password_confirmation: confirmation})
        });
        const result = await response.json();
        message.hidden = false;
        message.className = response.ok ? 'message success' : 'message error';
        message.textContent = response.ok ? 'Паролата е променена. Можете да затворите тази страница.' : (result.error?.message || 'Паролата не можа да бъде променена.');
        if (response.ok) { form.hidden = true; return; }
      } catch {
        message.hidden = false;
        message.className = 'message error';
        message.textContent = 'Възникна проблем с връзката. Опитайте отново.';
      } finally {
        if (!form.hidden) { button.disabled = false; button.textContent = 'Запази новата парола'; }
      }
    });
  </script>
</body>
</html>
HTML;
        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'no-store, max-age=0',
            'Pragma' => 'no-cache',
            'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Content-Security-Policy' => "default-src 'none'; style-src 'nonce-{$nonce}'; script-src 'nonce-{$nonce}'; connect-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'",
        ]);
    }
}
