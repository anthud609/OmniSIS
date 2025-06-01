<?php
declare(strict_types=1);
/** app/Core/Views/home.php */

if (isset($logger)) {
    $logger->debug('home.php: view file loaded', [
        'view' => 'home',
    ]);
} else {
    file_put_contents('php://stderr', "[View DEBUG] home.php: \$logger is not defined\n");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlentities($title, ENT_QUOTES, 'UTF-8') ?></title>
</head>
<body>
    <?php
    $logger->debug('home.php: rendering <h1> and <p>');
    ?>
    <h1><?= htmlentities($title, ENT_QUOTES, 'UTF-8') ?></h1>
    <p><?= htmlentities($message, ENT_QUOTES, 'UTF-8') ?></p>

    <p>
        <?php
        $currentTime = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $logger->debug('home.php: computed currentTime', ['currentTime' => $currentTime]);
        ?>
        Current UTC time: <?= $currentTime ?>
    </p>

    <?php
    $logger->debug('home.php: end of view');
    ?>
</body>
</html>
