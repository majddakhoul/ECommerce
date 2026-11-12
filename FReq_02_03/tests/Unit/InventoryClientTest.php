<?php
namespace Tests\Unit;
use App\Inventory\NullInventoryClient;
use PHPUnit\Framework\TestCase;
class InventoryClientTest extends TestCase
{
    public function test_null_client_is_safe_for_local_tests(): void
    {
        $client = new NullInventoryClient();
        $client->ensureProduct(1, 'Book', 10);
        $client->setStock(1, 8);
        $client->reserve('r-1', [1 => 2]);
        $client->release('r-1');
        $this->assertTrue(true);
    }
}
