<?php

namespace Tests\Feature\Banking;

use App\Domain\Banking\Services\BankAuditLogger;
use LogicException;

class BankAuditTest extends BankingDatabaseTestCase
{
    public function test_audit_events_form_a_hash_chain_and_redact_secret_metadata(): void
    {
        $audit = app(BankAuditLogger::class);
        $first = $audit->record('bank.test.first', metadata: [
            'access_token' => 'must-not-be-stored',
            'safe' => 'value',
            'comment' => 'Bearer hidden-token account 40702810000000000001',
        ]);
        $second = $audit->record('bank.test.second');

        $this->assertSame('[REDACTED]', $first->metadata['access_token']);
        $this->assertSame('value', $first->metadata['safe']);
        $this->assertSame(
            'Bearer [REDACTED] account [REDACTED_NUMBER]',
            $first->metadata['comment'],
        );
        $this->assertSame($first->hash, $second->previous_hash);
        $this->assertNotSame($first->hash, $second->hash);
        $storedPayload = [
            'user_id' => $first->user_id,
            'action' => $first->action,
            'auditable_type' => $first->auditable_type,
            'auditable_id' => $first->auditable_id,
            'correlation_id' => $first->correlation_id,
            'metadata' => $first->metadata,
            'previous_hash' => $first->previous_hash,
            'created_at' => $first->created_at->toISOString(),
        ];
        $this->assertSame(
            $first->hash,
            hash('sha256', json_encode(
                $storedPayload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            )),
        );
        $this->assertDatabaseMissing('bank_audit_events', [
            'metadata' => 'must-not-be-stored',
        ]);
    }

    public function test_audit_event_cannot_be_updated_or_deleted_through_the_model(): void
    {
        $event = app(BankAuditLogger::class)->record('bank.test.append_only');

        try {
            $event->forceFill(['action' => 'tampered'])->save();
            $this->fail('Audit update must be rejected.');
        } catch (LogicException) {
            $this->assertSame('bank.test.append_only', $event->fresh()->action);
        }

        $this->expectException(LogicException::class);

        $event->delete();
    }

    public function test_imported_transaction_cannot_be_deleted_through_the_model(): void
    {
        $transaction = $this->createTransaction(
            $this->createAccount($this->createConnection())
        );

        $this->expectException(LogicException::class);

        $transaction->delete();
    }
}
