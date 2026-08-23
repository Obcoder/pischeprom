<?php

namespace App\Domain\AiSales\Probes;

use App\Domain\AiSales\Contracts\GitRepositoryStateInspectorInterface;
use App\Domain\AiSales\Search\SearchProviderException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Process\Process;

final class ExistingYandexSecretExposureScanner
{
    private const EXPECTED_BRANCH = 'feature/ai-sales-agents';

    private const STAGE_09_COMMIT = '4d2976cadc9f7f36f8d7c41c26ea322cf235c062';

    private const STAGE_09B_SUBJECT = 'test(ai-sales): add bounded existing Yandex live acceptance probe';

    public function __construct(
        private readonly GitRepositoryStateInspectorInterface $repositoryState,
    ) {}

    public function assertExpectedWorktree(): string
    {
        $state = $this->repositoryState->inspect(self::STAGE_09_COMMIT);
        if (! hash_equals(self::EXPECTED_BRANCH, $state->branch)) {
            throw new SearchProviderException('probe_policy', 'stage09b_branch_mismatch');
        }
        if (! $state->baseIsAncestor) {
            throw new SearchProviderException('probe_policy', 'stage09b_stage09_not_ancestor');
        }
        if ($state->stagedChanges !== 0) {
            throw new SearchProviderException('probe_policy', 'stage09b_staged_changes_blocked');
        }
        if ($state->modifiedChanges !== 0) {
            throw new SearchProviderException('probe_policy', 'stage09b_modified_changes_blocked');
        }
        if ($state->untrackedChanges !== 0) {
            throw new SearchProviderException('probe_policy', 'stage09b_untracked_changes_blocked');
        }
        if (count($state->commitsAfterBase) !== 1) {
            throw new SearchProviderException('probe_policy', 'stage09b_commit_count_invalid');
        }

        $stage09bCommit = $state->commitsAfterBase[0];
        if (! hash_equals(self::STAGE_09B_SUBJECT, $stage09bCommit['subject'])) {
            throw new SearchProviderException('probe_policy', 'stage09b_commit_subject_invalid');
        }
        if (! hash_equals($stage09bCommit['hash'], $state->head)) {
            throw new SearchProviderException('probe_policy', 'stage09b_head_not_stage09b_commit');
        }

        return 'clean_committed_stage09b';
    }

    /** @return array{tracked_files_scanned: int, artifact_files_scanned: int, secret_matches: int} */
    public function assertSecretAbsent(string $apiKey): array
    {
        if ($apiKey === '') {
            throw new SearchProviderException('configuration', 'stage09b_yandex_not_configured');
        }

        $trackedCount = 0;
        foreach (array_filter(explode("\0", $this->git([
            'ls-files', '-co', '--exclude-standard', '-z',
        ]))) as $relativePath) {
            if ($relativePath === '.env') {
                throw new SearchProviderException('security', 'stage09b_env_tracked');
            }
            $path = base_path($relativePath);
            if ($this->scanFile($path, $apiKey)) {
                throw new SearchProviderException('security', 'stage09b_yandex_key_exposed');
            }
            $trackedCount++;
        }

        $artifactCount = 0;
        foreach ([public_path('build'), base_path('bootstrap/ssr'), storage_path('logs')] as $directory) {
            if (! is_dir($directory) || is_link($directory)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            );
            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->isLink()) {
                    continue;
                }
                if ($this->scanFile($file->getPathname(), $apiKey)) {
                    throw new SearchProviderException('security', 'stage09b_yandex_key_exposed');
                }
                $artifactCount++;
            }
        }

        return [
            'tracked_files_scanned' => $trackedCount,
            'artifact_files_scanned' => $artifactCount,
            'secret_matches' => 0,
        ];
    }

    /** @return array{key_persisted: bool, raw_provider_body_persisted: bool, full_html_persisted: bool} */
    public function databaseFindings(string $apiKey): array
    {
        $payload = [];
        $columnsByTable = [
            'prospecting_search_executions' => [
                'provider_code', 'profile_code', 'error_category', 'error_code', 'safe_request_id',
            ],
            'prospecting_search_results' => [
                'title', 'snippet', 'url', 'canonical_url', 'registrable_domain',
            ],
            'prospecting_public_fetches' => [
                'final_url', 'page_title', 'meta_description', 'headings', 'text_excerpt',
                'same_domain_links', 'protected_channels', 'error_category', 'error_code',
            ],
            'prospecting_search_usage_records' => [
                'provider_code', 'profile_code', 'safe_request_id',
            ],
        ];

        foreach ($columnsByTable as $table => $columns) {
            if (Schema::hasTable($table)) {
                $payload[$table] = DB::table($table)->get($columns)->all();
            }
        }

        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $lower = mb_strtolower($encoded);

        return [
            'key_persisted' => $apiKey !== '' && str_contains($encoded, $apiKey),
            'raw_provider_body_persisted' => str_contains($lower, '"rawdata"')
                || str_contains($lower, '<yandexsearch'),
            'full_html_persisted' => str_contains($lower, '<html')
                || str_contains($lower, '<!doctype'),
        ];
    }

    private function scanFile(string $path, string $apiKey): bool
    {
        if (! is_file($path) || is_link($path)) {
            return false;
        }
        $size = filesize($path);
        if (! is_int($size) || $size < 1) {
            return false;
        }
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new SearchProviderException('security', 'stage09b_secret_scan_failed');
        }

        $overlap = '';
        $overlapLength = max(0, strlen($apiKey) - 1);
        try {
            while (! feof($stream)) {
                $chunk = fread($stream, 1_048_576);
                if ($chunk === false) {
                    throw new SearchProviderException('security', 'stage09b_secret_scan_failed');
                }
                $candidate = $overlap.$chunk;
                if (str_contains($candidate, $apiKey)) {
                    return true;
                }
                $overlap = $overlapLength > 0 ? substr($candidate, -$overlapLength) : '';
            }
        } finally {
            fclose($stream);
        }

        return false;
    }

    /** @param list<string> $arguments */
    private function git(array $arguments): string
    {
        $process = new Process(['git', ...$arguments], base_path());
        $process->setTimeout(15);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new SearchProviderException('probe_policy', 'stage09b_git_preflight_failed');
        }

        return $process->getOutput();
    }
}
