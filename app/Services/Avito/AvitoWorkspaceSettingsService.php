<?php

namespace App\Services\Avito;

use App\Domain\Avito\Exceptions\AvitoException;
use App\Models\AvitoAutoloadFeed;
use App\Models\AvitoConnection;
use App\Models\AvitoWorkspaceSetting;
use Illuminate\Support\Str;

class AvitoWorkspaceSettingsService
{
    public function __construct(
        private readonly AvitoListingService $listings,
        private readonly AvitoTokenManager $tokens,
    ) {}

    public function settings(bool $refreshServerAccount = false): array
    {
        $settings = AvitoWorkspaceSetting::current();

        if ($this->tokens->clientCredentialsConfigured()
            && ($refreshServerAccount || ! $settings->server_account_id)) {
            $settings = $this->detectServerAccount($settings);
        }

        $settings = $this->ensureValidSelection($settings);
        $this->syncFeedConnection($settings);

        return $this->payload($settings);
    }

    public function select(string $selection): array
    {
        $settings = AvitoWorkspaceSetting::current();

        if ($selection === 'server') {
            if (! $settings->server_account_id && $this->tokens->clientCredentialsConfigured()) {
                $settings = $this->detectServerAccount($settings);
            }
            if (! $settings->server_account_id) {
                throw new AvitoException(
                    'Не удалось определить собственный кабинет Avito. Проверьте серверные ключи.',
                    'avito_workspace_account',
                    422,
                );
            }

            $settings->update([
                'auth_mode' => 'server',
                'default_account_id' => $settings->server_account_id,
                'default_connection_id' => null,
                'last_error' => null,
            ]);
            $this->syncFeedConnection($settings->fresh());

            return $this->payload($settings->fresh());
        }

        if (! preg_match('/^connection:(\d+)$/', $selection, $matches)) {
            throw new AvitoException('Выберите допустимый кабинет Avito.', 'avito_workspace_selection', 422);
        }

        $connection = AvitoConnection::query()
            ->whereKey((int) $matches[1])
            ->where('is_active', true)
            ->first();
        $accountId = $this->connectionAccountId($connection);
        if (! $connection || ! $accountId) {
            throw new AvitoException(
                'У OAuth-подключения отсутствует числовой ID кабинета Avito.',
                'avito_workspace_connection',
                422,
            );
        }

        $settings->update([
            'auth_mode' => 'oauth',
            'default_account_id' => $accountId,
            'default_connection_id' => $connection->id,
            'last_error' => null,
        ]);
        $this->syncFeedConnection($settings->fresh());

        return $this->payload($settings->fresh());
    }

    private function detectServerAccount(AvitoWorkspaceSetting $settings): AvitoWorkspaceSetting
    {
        try {
            $context = $this->listings->context();
            $account = (array) ($context['account'] ?? []);
            $accountId = is_numeric($account['id'] ?? null) ? (int) $account['id'] : null;
            if (! $accountId || $accountId < 1) {
                throw new AvitoException(
                    'Avito не вернул ID собственного кабинета.',
                    'avito_workspace_account',
                    422,
                );
            }

            $values = [
                'server_account_id' => $accountId,
                'server_account_name' => Str::limit(trim((string) ($account['name'] ?? '')), 255, ''),
                'server_account_checked_at' => now(),
                'last_error' => null,
            ];
            if ($settings->auth_mode === 'server' || ! $settings->default_account_id) {
                $values += [
                    'auth_mode' => 'server',
                    'default_account_id' => $accountId,
                    'default_connection_id' => null,
                ];
            }
            $settings->update($values);
        } catch (AvitoException $exception) {
            $settings->update([
                'last_error' => $exception->getMessage(),
                'server_account_checked_at' => now(),
            ]);
        }

        return $settings->fresh();
    }

    private function ensureValidSelection(AvitoWorkspaceSetting $settings): AvitoWorkspaceSetting
    {
        if ($settings->auth_mode === 'oauth') {
            $connection = AvitoConnection::query()
                ->whereKey($settings->default_connection_id)
                ->where('is_active', true)
                ->first();
            $accountId = $this->connectionAccountId($connection);
            if ($connection && $accountId) {
                if ($settings->default_account_id !== $accountId) {
                    $settings->update(['default_account_id' => $accountId]);
                }

                return $settings->fresh();
            }
        }

        if ($settings->server_account_id) {
            $settings->update([
                'auth_mode' => 'server',
                'default_account_id' => $settings->server_account_id,
                'default_connection_id' => null,
            ]);

            return $settings->fresh();
        }

        $connection = AvitoConnection::query()
            ->where('is_active', true)
            ->whereNotNull('external_user_id')
            ->latest('id')
            ->get()
            ->first(fn (AvitoConnection $item): bool => $this->connectionAccountId($item) !== null);
        if ($connection) {
            $settings->update([
                'auth_mode' => 'oauth',
                'default_account_id' => $this->connectionAccountId($connection),
                'default_connection_id' => $connection->id,
            ]);

            return $settings->fresh();
        }

        if ($settings->default_account_id || $settings->default_connection_id) {
            $settings->update([
                'default_account_id' => null,
                'default_connection_id' => null,
            ]);
        }

        return $settings->fresh();
    }

    private function payload(AvitoWorkspaceSetting $settings): array
    {
        $connections = AvitoConnection::query()
            ->where('is_active', true)
            ->latest('id')
            ->get()
            ->filter(fn (AvitoConnection $connection): bool => $this->connectionAccountId($connection) !== null);
        $options = [];

        if ($this->tokens->clientCredentialsConfigured() || $settings->server_account_id) {
            $options[] = [
                'value' => 'server',
                'title' => $settings->server_account_name ?: 'Собственный кабинет Avito',
                'subtitle' => $settings->server_account_id
                    ? "ID {$settings->server_account_id} · серверные ключи"
                    : 'Серверные ключи · ID ещё не определён',
                'account_id' => $settings->server_account_id,
                'connection_id' => null,
                'available' => (bool) $settings->server_account_id,
            ];
        }
        foreach ($connections as $connection) {
            $accountId = $this->connectionAccountId($connection);
            $options[] = [
                'value' => "connection:{$connection->id}",
                'title' => $connection->name,
                'subtitle' => "ID {$accountId} · OAuth",
                'account_id' => $accountId,
                'connection_id' => $connection->id,
                'available' => true,
            ];
        }

        $selectedValue = $settings->auth_mode === 'oauth' && $settings->default_connection_id
            ? "connection:{$settings->default_connection_id}"
            : 'server';
        $selected = collect($options)->firstWhere('value', $selectedValue);
        $authorizationReady = $settings->auth_mode === 'oauth'
            ? $connections->contains('id', $settings->default_connection_id)
            : $this->tokens->clientCredentialsConfigured();

        return [
            'ready' => (bool) $settings->default_account_id,
            'authorization_ready' => $authorizationReady,
            'account_id' => $settings->default_account_id,
            'account_name' => $selected['title'] ?? null,
            'connection_id' => $settings->default_connection_id,
            'auth_mode' => $settings->auth_mode,
            'selected' => $selectedValue,
            'options' => array_values($options),
            'server_account_checked_at' => $settings->server_account_checked_at?->toIso8601String(),
            'last_error' => $settings->auth_mode === 'server' || ! $settings->default_account_id
                ? $settings->last_error
                : null,
        ];
    }

    private function syncFeedConnection(AvitoWorkspaceSetting $settings): void
    {
        if (! $settings->default_account_id) {
            return;
        }

        AvitoAutoloadFeed::query()
            ->where('avito_account_id', $settings->default_account_id)
            ->where(function ($query) use ($settings): void {
                if ($settings->default_connection_id === null) {
                    $query->whereNotNull('avito_connection_id');
                } else {
                    $query->where(function ($nested) use ($settings): void {
                        $nested->whereNull('avito_connection_id')
                            ->orWhere('avito_connection_id', '!=', $settings->default_connection_id);
                    });
                }
            })
            ->update(['avito_connection_id' => $settings->default_connection_id]);
    }

    private function connectionAccountId(?AvitoConnection $connection): ?int
    {
        $value = $connection?->external_user_id;

        return filled($value) && ctype_digit((string) $value) && (int) $value > 0
            ? (int) $value
            : null;
    }
}
