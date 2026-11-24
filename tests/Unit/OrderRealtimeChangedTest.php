<?php

namespace Tests\Unit;

use App\Events\OrderRealtimeChanged;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;

class OrderRealtimeChangedTest extends TestCase
{
    public function test_order_event_is_private_and_contains_only_order_metadata(): void
    {
        $event = new OrderRealtimeChanged(17, 34, 'pending', 'created', '2025-01-01T10:00:00+00:00');
        $channels = $event->broadcastOn();

        $this->assertInstanceOf(ShouldDispatchAfterCommit::class, $event);
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-tenants.17.orders', $channels[0]->name);
        $this->assertSame([
            'event_id' => $event->eventId,
            'event_type' => 'created',
            'order_id' => 34,
            'status' => 'pending',
            'updated_at' => '2025-01-01T10:00:00+00:00',
        ], $event->broadcastWith());
    }

    public function test_channel_authorization_is_tenant_scoped_except_for_super_users(): void
    {
        $channels = Broadcast::getChannels();
        $authorize = $channels->get('tenants.{tenantId}.orders');
        $user = \Mockery::mock(User::class)->makePartial();
        $user->setAttribute('tenant_id', 17);
        $user->shouldReceive('hasRole')->with('Super')->andReturn(false);

        $this->assertIsCallable($authorize);
        $this->assertTrue($authorize($user, 17));
        $this->assertFalse($authorize($user, 18));

        $superUser = \Mockery::mock(User::class)->makePartial();
        $superUser->shouldReceive('hasRole')->with('Super')->andReturn(true);

        $this->assertTrue($authorize($superUser, 18));
    }
}
