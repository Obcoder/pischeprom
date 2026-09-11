<?php

declare(strict_types=1);

require __DIR__.'/lib/RealtimeNginx.php';

if ($argc !== 5) {
    fwrite(STDERR, "Expected TARGET_DIR, APP_HOST, NGINX_DUMP and STAGING_DIR.\n");
    exit(2);
}

try {
    $plan = Pischeprom\Deployment\RealtimeNginx::plan(
        (string) file_get_contents($argv[3]), $argv[1], $argv[2]
    );
    foreach (['site.path' => $plan['path'], 'site.next.conf' => $plan['contents']] as $name => $contents) {
        if (file_put_contents($argv[4].'/'.$name, $contents, LOCK_EX) !== strlen($contents)) {
            throw new RuntimeException('Nginx rollout plan could not be saved.');
        }
    }
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(1);
}
