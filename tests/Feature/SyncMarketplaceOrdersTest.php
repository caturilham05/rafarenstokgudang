<?php

namespace Tests\Feature;

use App\Jobs\SyncMarketplaceOrders;
use App\Jobs\SyncOrderWaybill;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncMarketplaceOrdersTest extends TestCase
{
    public function test_login_and_date_validation(): void
    {
        $this->get('/marketplace/orders/sync')->assertRedirect('/login');
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('can')->andReturn(true);
        $this->actingAs($user)->getJson('/marketplace/orders/sync?date_start=2026-09-07&date_end=2026-09-01')
            ->assertUnprocessable()->assertJsonValidationErrors('date_end');
        $this->getJson('/marketplace/orders/sync?date_start=2026-09-01&date_end=2026-09-01')
            ->assertStatus(503);
        $this->getJson('/marketplace/orders/sync?date_start=2026-09-01&date_end=2026-09-07')
            ->assertStatus(503);
        $this->getJson('/marketplace/orders/sync?date_start=2026-02-30&date_end=2026-09-07')
            ->assertUnprocessable()->assertJsonValidationErrors('date_start');
        $this->getJson('/marketplace/orders/sync?date_start=2026-09-01&date_end=invalid')
            ->assertUnprocessable()->assertJsonValidationErrors('date_end');
    }

    public function test_local_orders_are_selected_by_date_store_and_null_waybill(): void
    {
        Bus::fake();
        $this->withDatabase(function ($connection) {
            $connection->shouldReceive('select')->once()->withArgs(function ($sql, $bindings) {
                $this->assertStringContainsString('"waybill" is null', $sql);
                $this->assertStringContainsString('"order_time" >= ?', $sql);
                $this->assertStringContainsString('"order_time" < ?', $sql);
                $this->assertSame([1, '2026-09-01 00:00:00', '2026-09-08 00:00:00'], $bindings);
                return true;
            })->andReturn([['id' => 42]]);
            (new SyncMarketplaceOrders(1, 'shopee',
                \Illuminate\Support\Carbon::parse('2026-09-01')->timestamp,
                \Illuminate\Support\Carbon::parse('2026-09-08')->timestamp,
            ))->handle();
            Bus::assertDispatched(SyncOrderWaybill::class,
                fn ($job) => $job->orderId === 42 && $job->queue === 'shopee');
        });
    }

    public function test_waybill_and_status_update_only_matching_order_for_both_marketplaces(): void
    {
        foreach (['Shopee', 'Tiktok'] as $marketplace) {
            $this->withDatabase(function ($connection) use ($marketplace) {
                $connection->shouldReceive('select')->twice()->andReturn(
                    [['id' => 42, 'store_id' => 1, 'invoice' => 'INV123', 'waybill' => null]],
                    [['id' => 1, 'marketplace_name' => $marketplace, 'shop_id' => 123,
                        'access_token' => 'token', 'app_key' => 'key', 'app_secret' => 'secret', 'chiper' => 'cipher']],
                );
                Http::swap(new \Illuminate\Http\Client\Factory);
                Http::fake([
                    '*get_tracking_number*' => Http::response(['response' => ['tracking_number' => 'RESI123']]),
                    '*get_order_detail*' => Http::response(['response' => ['order_list' => [
                        ['order_sn' => 'INV123', 'order_status' => 'SHIPPED'],
                    ]]]),
                    '*' => Http::response(['code' => 0, 'data' => ['orders' => [
                        ['id' => 'INV123', 'tracking_number' => 'RESI123', 'status' => 'IN_TRANSIT'],
                    ]]]),
                ]);
                $connection->shouldReceive('update')->once()->withArgs(function ($sql, $bindings) use ($marketplace) {
                    $this->assertStringContainsString('set "status" = ?, "waybill" = ?', $sql);
                    $this->assertStringContainsString('"waybill" is null', $sql);
                    $this->assertSame([$marketplace === 'Shopee' ? 'SHIPPED' : 'IN_TRANSIT', 'RESI123', 42, 1, 'INV123'], $bindings);
                    return true;
                })->andReturn(1);
                (new SyncOrderWaybill(42))->handle();
                Http::assertSentCount($marketplace === 'Shopee' ? 2 : 1);
            });
        }
    }

    public function test_already_filled_waybill_skips_api(): void
    {
        Http::fake();
        $this->withDatabase(function ($connection) {
            $connection->shouldReceive('select')->once()->andReturn([]);
            $connection->shouldNotReceive('update');
            (new SyncOrderWaybill(42))->handle();
        });
        Http::assertNothingSent();
    }

    private function withDatabase(callable $check): void
    {
        $resolver = Store::getConnectionResolver();
        $connection = \Mockery::mock(\Illuminate\Database\SQLiteConnection::class)->makePartial();
        $connection->setQueryGrammar(new \Illuminate\Database\Query\Grammars\SQLiteGrammar);
        $connection->setPostProcessor(new \Illuminate\Database\Query\Processors\SQLiteProcessor);
        $mockResolver = \Mockery::mock(\Illuminate\Database\ConnectionResolverInterface::class);
        $mockResolver->shouldReceive('connection')->andReturn($connection);
        Store::setConnectionResolver($mockResolver);
        try {
            $check($connection);
        } finally {
            Store::setConnectionResolver($resolver);
        }
    }
}
