<?php

declare(strict_types=1);

// Validate the same TLS endpoint used by browsers without printing credentials.
if ($argc !== 2) {
    fwrite(STDERR, "Expected TARGET_DIR.\n");
    exit(2);
}

try {
    require $argv[1].'/vendor/autoload.php';
    $app = require $argv[1].'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    $host = (string) parse_url(config('app.url'), PHP_URL_HOST);
    $key = (string) config('broadcasting.connections.reverb.key');
    if (! config('realtime.enabled') || $key === '' || $host === '') {
        throw new RuntimeException('Realtime is not configured.');
    }

    $context = stream_context_create(['ssl' => [
        'peer_name' => $host,
        'verify_peer' => true,
        'verify_peer_name' => true,
        'SNI_enabled' => true,
    ]]);
    // Connect to the local reverse proxy, retaining certificate verification
    // against APP_URL, rather than relying on external DNS/CDN routing.
    $socket = @stream_socket_client('tls://127.0.0.1:443', $errorCode, $errorMessage, 5, STREAM_CLIENT_CONNECT, $context);
    if (! is_resource($socket)) {
        throw new RuntimeException('The local TLS WebSocket proxy is unavailable.');
    }
    stream_set_timeout($socket, 5);
    $nonce = base64_encode(random_bytes(16));
    $request = 'GET /realtime/app/'.rawurlencode($key)."?protocol=7&client=js&version=8.4.0&flash=false HTTP/1.1\r\n"
        ."Host: {$host}\r\nOrigin: https://{$host}\r\nUpgrade: websocket\r\nConnection: Upgrade\r\n"
        ."Sec-WebSocket-Key: {$nonce}\r\nSec-WebSocket-Version: 13\r\n\r\n";
    fwrite($socket, $request);
    $response = '';
    while (($line = fgets($socket, 4096)) !== false) {
        $response .= $line;
        if ($line === "\r\n" || strlen($response) > 16384) {
            break;
        }
    }
    $accept = base64_encode(sha1($nonce.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
    if (! str_starts_with($response, 'HTTP/1.1 101 ')
        || preg_match('/^Sec-WebSocket-Accept:\s*'.preg_quote($accept, '/').'\s*$/mi', $response) !== 1) {
        throw new RuntimeException('The TLS WebSocket upgrade failed.');
    }

    $read = static function (int $length) use ($socket): string {
        $buffer = '';
        while (strlen($buffer) < $length) {
            $chunk = fread($socket, $length - strlen($buffer));
            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('The WebSocket acknowledgement was incomplete.');
            }
            $buffer .= $chunk;
        }

        return $buffer;
    };
    $frame = $read(2);
    if (ord($frame[0]) !== 0x81 || (ord($frame[1]) & 0x80) !== 0) {
        throw new RuntimeException('The WebSocket acknowledgement was invalid.');
    }
    $length = ord($frame[1]) & 0x7F;
    if ($length === 126) {
        $length = unpack('nlength', $read(2))['length'];
    } elseif ($length === 127) {
        throw new RuntimeException('The WebSocket acknowledgement was oversized.');
    }
    if ($length > 4096) {
        throw new RuntimeException('The WebSocket acknowledgement was oversized.');
    }
    $acknowledgement = json_decode($read($length), true, flags: JSON_THROW_ON_ERROR);
    if (($acknowledgement['event'] ?? null) !== 'pusher:connection_established') {
        throw new RuntimeException('Reverb refused the connection or origin.');
    }
    fclose($socket);

    // Also check the signed server-to-Reverb publishing API. The private health
    // channel has no subscribers and carries no application or user data.
    $app->make(Illuminate\Contracts\Broadcasting\Factory::class)->connection('reverb')->broadcast(
        [new Illuminate\Broadcasting\PrivateChannel('realtime.deploy-health')],
        'deployment.health',
        ['ok' => true]
    );
    $app->make(Illuminate\Contracts\Queue\Factory::class)->connection('database')->size('realtime');
    fwrite(STDOUT, "Realtime TLS WebSocket and internal publishing checks passed.\n");
} catch (Throwable) {
    fwrite(STDERR, "Realtime connection check failed; inspect Reverb and Nginx locally on the VPS.\n");
    exit(1);
}
