<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(config('app.name')) ?></title>
</head>
<body>

    <?php require_once VIEWS_PATH . '/layouts/header.php'; ?>

    <main>
        <?= $content ?>
    </main>

    <?php require_once VIEWS_PATH . '/layouts/footer.php'; ?>

</body>
</html>