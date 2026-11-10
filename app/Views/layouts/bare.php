<!doctype html>
<html lang="en">
<head>
<?php require BASE_PATH . '/app/Views/partials/head.php'; ?>
<title><?= e($title ?? config('name')) ?></title>
</head>
<body class="bare">
<?= $content ?>
<?php require BASE_PATH . '/app/Views/partials/scripts.php'; ?>
</body>
</html>
