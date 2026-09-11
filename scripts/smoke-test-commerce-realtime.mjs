#!/usr/bin/env node

// Run with Node 22+ and installed Composer dependencies. All Laravel state,
// credentials, caches and the SQLite queue live in a disposable temp directory.
import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { createHmac, randomBytes } from 'node:crypto';
import { once } from 'node:events';
import { mkdir, mkdtemp, rm, writeFile } from 'node:fs/promises';
import { createServer } from 'node:net';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { setTimeout as delay } from 'node:timers/promises';
import { fileURLToPath } from 'node:url';

assert.ok(Number(process.versions.node.split('.')[0]) >= 22, 'Node 22+ is required');

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const temporary = await mkdtemp(join(tmpdir(), 'commerce-reverb-smoke-'));
const clients = [];
let server;
let serverOutput = '';
const appKey = randomBytes(16).toString('hex');
const appSecret = randomBytes(32).toString('hex');
const channel = 'private-commerce.updates';

const phpBootstrap = String.raw`<?php
require getenv('REALTIME_SMOKE_ROOT').'/vendor/autoload.php';
$app = require getenv('REALTIME_SMOKE_ROOT').'/bootstrap/app.php';
$app->useEnvironmentPath(getenv('REALTIME_SMOKE_DIRECTORY'));
$app->useStoragePath(getenv('REALTIME_SMOKE_DIRECTORY').'/storage');

if (($argv[1] ?? '') !== 'publish') {
    exit($app->handleCommand(new Symfony\Component\Console\Input\ArgvInput));
}

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
Illuminate\Support\Facades\Schema::create('jobs', function (Illuminate\Database\Schema\Blueprint $table): void {
    $table->id();
    $table->string('queue')->index();
    $table->longText('payload');
    $table->unsignedTinyInteger('attempts');
    $table->unsignedInteger('reserved_at')->nullable();
    $table->unsignedInteger('available_at');
    $table->unsignedInteger('created_at');
});
$event = new App\Events\CommerceDataChanged(['sales', 'goods_stock']);
Illuminate\Support\Facades\DB::transaction(function () use ($event): void {
    Illuminate\Support\Facades\Event::dispatch($event);
    if (Illuminate\Support\Facades\DB::table('jobs')->count() !== 0) {
        throw new RuntimeException('Broadcast job was queued before commit.');
    }
});
if (Illuminate\Support\Facades\DB::table('jobs')->count() !== 1) {
    throw new RuntimeException('Committed event did not create one queue job.');
}
$status = $kernel->call('queue:work', [
    'connection' => 'database',
    '--queue' => 'realtime',
    '--once' => true,
    '--tries' => 5,
    '--timeout' => 10,
    '--no-interaction' => true,
]);
if ($status !== 0 || Illuminate\Support\Facades\DB::table('jobs')->count() !== 0) {
    throw new RuntimeException('Broadcast queue worker did not deliver the event.');
}
echo json_encode($event->broadcastWith(), JSON_THROW_ON_ERROR);
`;

function connect(url, origin = 'http://127.0.0.1') {
    const socket = new WebSocket(url, { headers: { Origin: origin } });
    const frames = [];
    const waiters = new Set();
    socket.addEventListener('message', ({ data }) => {
        const frame = JSON.parse(data);
        if (typeof frame.data === 'string') frame.data = JSON.parse(frame.data);
        frames.push(frame);
        for (const notify of [...waiters]) notify();
    });
    socket.addEventListener('error', () => {});
    const client = {
        socket,
        frames,
        waitFor(predicate) {
            return new Promise((resolveFrame, reject) => {
                const timeout = setTimeout(() => {
                    waiters.delete(check);
                    reject(new Error('Timed out waiting for expected WebSocket frame'));
                }, 5_000);
                const check = () => {
                    const frame = frames.find(predicate);
                    if (!frame) return;
                    clearTimeout(timeout);
                    waiters.delete(check);
                    resolveFrame(frame);
                };
                waiters.add(check);
                check();
            });
        },
        send(event, data) {
            socket.send(JSON.stringify({ event, data }));
        },
    };
    clients.push(client);
    return client;
}

async function runPhp(arguments_, environment) {
    const process_ = spawn(process.env.PHP_BINARY || 'php', [join(temporary, 'artisan.php'), ...arguments_], {
        cwd: root,
        env: environment,
        stdio: ['ignore', 'pipe', 'pipe'],
    });
    let output = '';
    let errors = '';
    process_.stdout.on('data', (chunk) => { output += chunk; });
    process_.stderr.on('data', (chunk) => { errors += chunk; });
    const timeout = setTimeout(() => process_.kill('SIGKILL'), 15_000);
    const [code] = await once(process_, 'exit');
    clearTimeout(timeout);
    assert.equal(code, 0, `Isolated PHP process failed: ${errors || output}`);
    return output;
}

try {
    const probe = createServer();
    probe.listen(0, '127.0.0.1');
    await once(probe, 'listening');
    const port = probe.address().port;
    await new Promise((resolveClose) => probe.close(resolveClose));

    for (const path of ['storage/framework/cache/data', 'storage/framework/views', 'storage/framework/sessions', 'storage/logs']) {
        await mkdir(join(temporary, path), { recursive: true });
    }
    await writeFile(join(temporary, 'artisan.php'), phpBootstrap, { mode: 0o600 });
    await writeFile(join(temporary, 'queue.sqlite'), '', { mode: 0o600 });
    await writeFile(join(temporary, '.env'), '', { mode: 0o600 });

    // Deliberately do not inherit application credentials or DB settings.
    const environment = {
        PATH: process.env.PATH,
        HOME: process.env.HOME,
        REALTIME_SMOKE_ROOT: root,
        REALTIME_SMOKE_DIRECTORY: temporary,
        APP_ENV: 'testing',
        APP_KEY: `base64:${randomBytes(32).toString('base64')}`,
        APP_URL: `http://127.0.0.1:${port}`,
        APP_DEBUG: 'false',
        APP_CONFIG_CACHE: join(temporary, 'config.php'),
        APP_ROUTES_CACHE: join(temporary, 'routes.php'),
        APP_SERVICES_CACHE: join(temporary, 'services.php'),
        APP_PACKAGES_CACHE: join(temporary, 'packages.php'),
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: join(temporary, 'queue.sqlite'),
        CACHE_STORE: 'array',
        QUEUE_CONNECTION: 'database',
        SESSION_DRIVER: 'array',
        LOG_CHANNEL: 'stderr',
        BROADCAST_CONNECTION: 'reverb',
        REALTIME_ENABLED: 'true',
        REALTIME_QUEUE_CONNECTION: 'database',
        REALTIME_QUEUE: 'realtime',
        REVERB_APP_ID: 'isolated-realtime-smoke',
        REVERB_APP_KEY: appKey,
        REVERB_APP_SECRET: appSecret,
        REVERB_SERVER_HOST: '127.0.0.1',
        REVERB_SERVER_PORT: String(port),
        REVERB_HOST: '127.0.0.1',
        REVERB_PORT: String(port),
        REVERB_SCHEME: 'http',
        REVERB_SCALING_ENABLED: 'false',
        PULSE_ENABLED: 'false',
        TELESCOPE_ENABLED: 'false',
    };

    server = spawn(process.env.PHP_BINARY || 'php', [join(temporary, 'artisan.php'), 'reverb:start', '--no-interaction'], {
        cwd: root,
        env: environment,
        stdio: ['ignore', 'pipe', 'pipe'],
    });
    server.stdout.on('data', (chunk) => { serverOutput += chunk; });
    server.stderr.on('data', (chunk) => { serverOutput += chunk; });
    for (let attempt = 0; !serverOutput.includes('Starting server on'); attempt++) {
        assert.ok(attempt < 100 && server.exitCode === null, `Reverb did not start: ${serverOutput}`);
        await delay(100);
    }

    const url = `ws://127.0.0.1:${port}/app/${appKey}?protocol=7&client=smoke&version=1.0`;
    const active = [connect(url), connect(url)];
    for (const client of active) {
        const connected = await client.waitFor((frame) => frame.event === 'pusher:connection_established');
        const signature = createHmac('sha256', appSecret)
            .update(`${connected.data.socket_id}:${channel}`).digest('hex');
        client.send('pusher:subscribe', { channel, auth: `${appKey}:${signature}` });
        await client.waitFor((frame) => frame.event === 'pusher_internal:subscription_succeeded');
    }

    const forbiddenOrigin = connect(url, 'https://untrusted.example');
    await forbiddenOrigin.waitFor((frame) => frame.event === 'pusher:error' && frame.data.code === 4009);

    const unsigned = connect(url);
    await unsigned.waitFor((frame) => frame.event === 'pusher:connection_established');
    unsigned.send('pusher:subscribe', { channel, auth: `${appKey}:invalid-signature` });
    await unsigned.waitFor((frame) => frame.event === 'pusher:error' && frame.data.code === 4009);

    const expected = JSON.parse(await runPhp(['publish'], environment));
    assert.deepEqual(Object.keys(expected).sort(), ['event_id', 'topics']);
    assert.deepEqual(expected.topics, ['sales', 'goods_stock']);
    for (const client of active) {
        const received = await client.waitFor((frame) => frame.event === 'commerce.changed');
        assert.equal(received.channel, channel);
        assert.deepEqual(received.data, expected);
    }

    active[0].socket.send(JSON.stringify({
        event: 'client-commerce.changed', channel, data: { topics: ['sales'], event_id: 'forged' },
    }));
    await active[0].waitFor((frame) => frame.event === 'pusher:error' && frame.data.code === 4301);
    await delay(250);

    for (const client of active) {
        assert.equal(client.frames.filter((frame) => frame.event === 'commerce.changed').length, 1);
        assert.equal(client.frames.filter((frame) => frame.event === 'client-commerce.changed').length, 0);
    }
    assert.equal(unsigned.frames.filter((frame) => frame.event === 'commerce.changed').length, 0);
    console.log('PASS: isolated database queue → Reverb → two private clients; one matching event each; invalid signature, foreign origin and client spoofing rejected.');
} finally {
    for (const client of clients) {
        try { client.socket.close(); } catch { /* Already disconnected. */ }
    }
    if (server && server.exitCode === null) {
        server.kill('SIGTERM');
        await Promise.race([once(server, 'exit'), delay(2_000)]);
        if (server.exitCode === null) {
            server.kill('SIGKILL');
            await once(server, 'exit');
        }
    }
    await rm(temporary, { recursive: true, force: true });
}
