<?php

declare(strict_types=1);

$pageTitle = 'Gerador VOGA PHP';

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
    <title><?= $escape($pageTitle) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen p-4 md:p-8">
    <div class="max-w-5xl mx-auto">
        <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between mb-8">
            <div class="text-center md:text-left">
                <h1 class="text-3xl font-bold text-slate-900">Gerador de Contratos VOGA</h1>
                <p class="text-slate-600">Versao PHP em MVC para TXT</p>
            </div>
            <form action="" method="post" class="flex items-center justify-center gap-3">
                <span class="text-sm text-slate-500">Logado como <strong><?= $escape($user['username'] ?? '') ?></strong></span>
                <button type="submit" name="action" value="logout" class="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-700">Sair</button>
            </form>
        </header>

        <?php if (!$extractedData): ?>
            <?php require __DIR__ . '/partials/upload.php'; ?>
        <?php else: ?>
            <?php require __DIR__ . '/partials/editor.php'; ?>
        <?php endif; ?>
    </div>
</body>
</html>
