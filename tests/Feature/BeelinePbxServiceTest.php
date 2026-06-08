<?php

namespace Tests\Feature;

use App\Models\PhoneCall;
use App\Services\Telephony\BeelinePbxService;
use ReflectionClass;
use Tests\TestCase;

class BeelinePbxServiceTest extends TestCase
{
    public function test_it_normalizes_outgoing_xsi_release_event(): void
    {
        $service = app(BeelinePbxService::class);
        $payload = [
            'cmd' => 'event',
            'type' => 'xsi:CallReleasedEvent',
            'callId' => 'abc-1',
            'direction' => 'originator',
            'calledParty' => ['address' => 'sip:+79991234567@beeline.ru'],
            'localParty' => ['address' => 'sip:9650160001@spb.ims.mnc099.mcc250.3gppnetwork.org'],
            'eventTime' => '2026-06-08T10:00:30+03:00',
            'startTime' => '2026-06-08T10:00:00+03:00',
        ];

        $normalized = $this->invokeServiceMethod($service, 'normalizeIncomingPayload', [$payload]);
        $data = $this->invokeServiceMethod($service, 'normalizeCallPayload', [$normalized]);
        $data = $this->invokeServiceMethod($service, 'completeTiming', [$data]);

        $this->assertSame('abc-1', $data['provider_call_id']);
        $this->assertSame(PhoneCall::DIRECTION_OUT, $data['direction']);
        $this->assertSame('completed', $data['status']);
        $this->assertSame('released', $data['event_type']);
        $this->assertSame('79991234567', $data['client_phone']);
        $this->assertSame('9650160001', $data['employee_user']);
        $this->assertSame('79650160001', $data['employee_phone']);
        $this->assertSame(30, $data['duration_seconds']);
    }

    public function test_it_uses_calling_party_as_client_for_incoming_call(): void
    {
        config(['services.beeline_pbx.own_numbers' => ['79650160001']]);

        $service = app(BeelinePbxService::class);
        $payload = [
            'cmd' => 'event',
            'type' => 'xsi:CallReceivedEvent',
            'callId' => 'abc-2',
            'direction' => 'terminator',
            'callingParty' => ['address' => 'sip:+79991234567@beeline.ru'],
            'calledParty' => ['address' => 'sip:+79650160001@beeline.ru'],
            'localParty' => ['address' => 'sip:9650160001@spb.ims.mnc099.mcc250.3gppnetwork.org'],
        ];

        $normalized = $this->invokeServiceMethod($service, 'normalizeIncomingPayload', [$payload]);
        $data = $this->invokeServiceMethod($service, 'normalizeCallPayload', [$normalized]);

        $this->assertSame(PhoneCall::DIRECTION_IN, $data['direction']);
        $this->assertSame('79991234567', $data['client_phone']);
    }

    protected function invokeServiceMethod(BeelinePbxService $service, string $method, array $arguments): mixed
    {
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($service, $arguments);
    }
}
