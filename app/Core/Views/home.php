<?php
declare(strict_types=1);
/** app/Core/Views/home.php */

/**
 * Variables available:
 *   $title   (string)
 *   $message (string)
 *   $logger  (Core\Logger instance)
 */
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <title><?= htmlentities($title, ENT_QUOTES, 'UTF-8') ?></title>
  </head>
  <body>
    <h1><?= htmlentities($title, ENT_QUOTES, 'UTF-8') ?></h1>
    <p><?= htmlentities($message, ENT_QUOTES, 'UTF-8') ?></p>
    <p>
      Current UTC time:
      <?= (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s') ?>
    </p>
  </body>
</html>
