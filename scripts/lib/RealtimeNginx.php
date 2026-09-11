<?php

declare(strict_types=1);

namespace Pischeprom\Deployment;

final class RealtimeNginx
{
    /**
     * Plan one insertion into the exact application's TLS vhost. A complete
     * nginx -T dump is used so disabled sites and unrelated domains are ignored.
     *
     * @return array{path: string, contents: string}
     */
    public static function plan(string $dump, string $targetDir, string $host, string $nginxRoot = '/etc/nginx'): array
    {
        $sections = preg_split('/^# configuration file (.+):\r?\n/m', $dump, -1, PREG_SPLIT_DELIM_CAPTURE);
        $matches = [];
        $includePath = $nginxRoot.'/snippets/pischeprom-realtime.conf';

        for ($i = 1; $i < count($sections); $i += 2) {
            $source = $sections[$i];
            $contents = $sections[$i + 1] ?? '';
            if (! str_contains($contents, 'server_name')) {
                continue;
            }

            $tokens = self::tokenize($contents);
            $position = 0;
            $nodes = self::parse($tokens, $position);
            foreach (self::servers($nodes) as $server) {
                $names = self::arguments($server, 'server_name');
                if (! in_array($host, $names, true)) {
                    continue;
                }

                $https = false;
                foreach ($server['children'] as $directive) {
                    if ($directive['name'] === 'listen'
                        && preg_match('/(?:^|:)443$/D', $directive['args'][0] ?? '') === 1
                        && in_array('ssl', $directive['args'], true)) {
                        $https = true;
                    }
                }
                if (! $https) {
                    continue;
                }
                if (self::arguments($server, 'root') !== [$targetDir.'/public']) {
                    throw new \RuntimeException('The HTTPS application vhost has an unexpected document root.');
                }

                $path = realpath($source);
                if ($path === false || ! str_starts_with($path, $nginxRoot.'/') || ! is_file($path)) {
                    throw new \RuntimeException('The application vhost must resolve to a file inside the Nginx configuration directory.');
                }
                $original = file_get_contents($path);
                // nginx -T appends a newline between files. Any other difference
                // means the configuration changed while preparing the rollout.
                if (! is_string($original) || rtrim($original, "\n") !== rtrim($contents, "\n")) {
                    throw new \RuntimeException('The Nginx configuration changed after inspection; retry deployment.');
                }

                $includes = self::arguments($server, 'include');
                $managedIncludes = array_filter($includes, static fn ($value) => $value === $includePath);
                if (count($managedIncludes) > 1) {
                    throw new \RuntimeException('The realtime Nginx include is duplicated.');
                }
                foreach ($server['children'] as $directive) {
                    if ($directive['name'] === 'location'
                        && str_contains(implode(' ', $directive['args']), '/realtime')) {
                        throw new \RuntimeException('The application vhost already defines a realtime location; review it before deployment.');
                    }
                }

                $updated = $original;
                if ($managedIncludes === []) {
                    $updated = substr($original, 0, $server['close'])
                        ."    include {$includePath};\n"
                        .substr($original, $server['close']);
                }
                $matches[] = ['path' => $path, 'contents' => $updated];
            }
        }

        if (count($matches) !== 1) {
            throw new \RuntimeException('Expected exactly one active HTTPS APP_URL vhost with the application document root.');
        }

        return $matches[0];
    }

    private static function arguments(array $node, string $name): array
    {
        $arguments = [];
        foreach ($node['children'] as $child) {
            if ($child['name'] === $name) {
                array_push($arguments, ...$child['args']);
            }
        }

        return $arguments;
    }

    private static function servers(array $nodes): array
    {
        $servers = [];
        foreach ($nodes as $node) {
            if ($node['name'] === 'server' && $node['args'] === [] && isset($node['close'])) {
                $servers[] = $node;
            } elseif ($node['name'] === 'http') {
                array_push($servers, ...self::servers($node['children']));
            }
        }

        return $servers;
    }

    private static function parse(array $tokens, int &$position, bool $nested = false): array
    {
        $nodes = [];
        while ($position < count($tokens)) {
            if ($tokens[$position]['value'] === '}') {
                if (! $nested) {
                    throw new \RuntimeException('Unexpected Nginx closing brace.');
                }

                return $nodes;
            }

            $node = ['name' => $tokens[$position++]['value'], 'args' => [], 'children' => []];
            while (isset($tokens[$position]) && ! in_array($tokens[$position]['value'], [';', '{', '}'], true)) {
                $node['args'][] = $tokens[$position++]['value'];
            }
            $terminator = $tokens[$position++]['value'] ?? null;
            if ($terminator === '{') {
                $node['children'] = self::parse($tokens, $position, true);
                $node['close'] = $tokens[$position++]['offset'];
            } elseif ($terminator !== ';') {
                throw new \RuntimeException('Unsupported or incomplete Nginx directive.');
            }
            $nodes[] = $node;
        }
        if ($nested) {
            throw new \RuntimeException('Unclosed Nginx block.');
        }

        return $nodes;
    }

    private static function tokenize(string $contents): array
    {
        $tokens = [];
        $length = strlen($contents);
        for ($offset = 0; $offset < $length;) {
            $character = $contents[$offset];
            if (ctype_space($character)) {
                $offset++;

                continue;
            }
            if ($character === '#') {
                $offset = strpos($contents, "\n", $offset) ?: $length;

                continue;
            }
            if (str_contains('{};', $character)) {
                $tokens[] = ['value' => $character, 'offset' => $offset++];

                continue;
            }

            $start = $offset;
            $value = '';
            $quote = null;
            while ($offset < $length) {
                $character = $contents[$offset];
                if ($character === '\\' && $offset + 1 < $length) {
                    $value .= $contents[$offset + 1];
                    $offset += 2;
                } elseif ($quote !== null) {
                    if ($character === $quote) {
                        $quote = null;
                    } else {
                        $value .= $character;
                    }
                    $offset++;
                } elseif ($character === '"' || $character === "'") {
                    $quote = $character;
                    $offset++;
                } elseif (ctype_space($character) || str_contains('{};#', $character)) {
                    break;
                } else {
                    $value .= $character;
                    $offset++;
                }
            }
            if ($quote !== null || $start === $offset) {
                throw new \RuntimeException('Unsupported Nginx quoting.');
            }
            $tokens[] = ['value' => $value, 'offset' => $start];
        }

        return $tokens;
    }
}
