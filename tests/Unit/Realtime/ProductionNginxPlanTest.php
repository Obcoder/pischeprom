<?php

namespace Tests\Unit\Realtime;

use PHPUnit\Framework\TestCase;
use Pischeprom\Deployment\RealtimeNginx;
use RuntimeException;

require_once dirname(__DIR__, 3).'/scripts/lib/RealtimeNginx.php';

class ProductionNginxPlanTest extends TestCase
{
    private string $nginxRoot;

    private string $sitePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->nginxRoot = sys_get_temp_dir().'/realtime-nginx-'.bin2hex(random_bytes(8));
        mkdir($this->nginxRoot, 0700);
        $this->nginxRoot = realpath($this->nginxRoot);
        $this->sitePath = $this->nginxRoot.'/app.conf';
    }

    protected function tearDown(): void
    {
        if (is_file($this->sitePath)) {
            unlink($this->sitePath);
        }
        rmdir($this->nginxRoot);
        parent::tearDown();
    }

    public function test_it_updates_only_the_exact_tls_vhost_and_reuses_the_include_on_redeployment(): void
    {
        $original = <<<'NGINX'
# Forge-style vhost. Braces in comments { must not affect block selection }.
server {
    listen 80;
    server_name warehouse.example.test;
    return 301 https://$host$request_uri;
}
server {
    listen 443 ssl;
    listen [::]:443 ssl;
    server_name warehouse.example.test www.warehouse.example.test;
    root "/home/forge/application/public";
    include forge-conf/warehouse.example.test/server/*;
    location / { try_files $uri $uri/ /index.php?$query_string; }
}
server {
    listen 443 ssl;
    server_name unrelated.example.test;
    root /srv/unrelated;
}

NGINX;
        $plan = $this->plan($original);
        $this->assertSame($this->sitePath, $plan['path']);
        $include = '    include '.$this->nginxRoot."/snippets/pischeprom-realtime.conf;\n";
        $this->assertSame($original, str_replace($include, '', $plan['contents']));
        $this->assertSame(1, substr_count($plan['contents'], $include));
        $this->assertStringContainsString($include."}\nserver {\n    listen 443 ssl;\n    server_name unrelated", $plan['contents']);
        $this->assertSame($original, file_get_contents($this->sitePath));
        $this->assertSame($plan, $this->plan($plan['contents']));
    }

    public function test_it_refuses_a_matching_domain_with_a_different_document_root(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unexpected document root');
        $this->plan(str_replace('/home/forge/application/public', '/srv/some-other-app/public', $this->vhost()));
    }

    public function test_it_refuses_ambiguous_vhosts(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('exactly one');
        $this->plan($this->vhost().$this->vhost());
    }

    public function test_it_requires_the_exact_domain_and_https_listener(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('exactly one');
        $this->plan(str_replace('443 ssl', '80', $this->vhost()));
    }

    public function test_it_refuses_an_existing_unmanaged_realtime_location(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already defines a realtime location');
        $this->plan(str_replace("\n}", "\n    location /realtime/ { proxy_pass http://127.0.0.1:9090; }\n}", $this->vhost()));
    }

    public function test_it_does_not_overwrite_a_configuration_changed_after_inspection(): void
    {
        file_put_contents($this->sitePath, $this->vhost()."# Updated after nginx -T\n");
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('changed after inspection');
        RealtimeNginx::plan($this->dump($this->vhost()), '/home/forge/application', 'warehouse.example.test', $this->nginxRoot);
    }

    public function test_it_does_not_follow_vhost_paths_outside_nginx_configuration(): void
    {
        file_put_contents($this->sitePath, $this->vhost());
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('inside the Nginx configuration');
        RealtimeNginx::plan($this->dump($this->vhost()), '/home/forge/application', 'warehouse.example.test', $this->nginxRoot.'/other');
    }

    private function plan(string $contents): array
    {
        file_put_contents($this->sitePath, $contents);

        return RealtimeNginx::plan($this->dump($contents), '/home/forge/application', 'warehouse.example.test', $this->nginxRoot);
    }

    private function dump(string $contents): string
    {
        return '# configuration file '.$this->sitePath.":\n".$contents."\n";
    }

    private function vhost(): string
    {
        return "server {\n    listen 443 ssl;\n    server_name warehouse.example.test;\n    root /home/forge/application/public;\n}\n";
    }
}
