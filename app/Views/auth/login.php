<?php

declare(strict_types=1);

/**
 * @param mixed $value
 */
$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Gerador VOGA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="min-h-screen bg-slate-100 flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-3xl shadow-xl border border-slate-200 p-8">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-slate-900">Acesso ao Gerador</h1>
            <p class="text-slate-600 mt-2">Entre com seu usuario para usar a aplicacao.</p>
        </div>

        <?php if (!empty($flash['message'])): ?>
            <?php
            $toastType = $flash['type'] ?? 'error';
            $toastClasses = match ($toastType) {
                'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
                'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
                default => 'border-red-200 bg-red-50 text-red-700',
            };
            ?>
            <div id="app-toast" class="mb-4 rounded-xl border px-4 py-3 text-sm <?= $toastClasses ?>" role="alert">
                <?= $escape($flash['message']) ?>
            </div>
        <?php endif; ?>

        <form action="" method="post" class="space-y-4">
            <input type="hidden" name="action" value="login">
            <input type="hidden" name="_csrf" value="<?= $escape($csrfToken ?? '') ?>">
            <div>
                <label for="username" class="block text-sm font-medium text-slate-700 mb-1">Usuario</label>
                <input id="username" name="username" type="text" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-500 focus:outline-none" placeholder="Seu usuario">
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Senha</label>
                <input id="password" name="password" type="password" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-500 focus:outline-none" placeholder="Sua senha">
            </div>
            <button type="submit" class="w-full rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-700">
                Entrar
            </button>
        </form>
    </div>

    <script>
        window.addEventListener('load', () => {
            const toast = document.getElementById('app-toast');
            if (!toast) {
                return;
            }

            window.setTimeout(() => {
                toast.classList.add('opacity-0');
            }, 4500);

            window.setTimeout(() => {
                toast.remove();
            }, 5000);
        });
    </script>
</body>
</html>
