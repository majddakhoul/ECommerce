<?php
namespace App\Inventory;
use App\Exceptions\ApiException;
use Grpc\ChannelCredentials;
use Inventory\V1\EnsureProductRequest;
use Inventory\V1\InventoryServiceClient;
use Inventory\V1\ReleaseRequest;
use Inventory\V1\ReservationItem;
use Inventory\V1\ReserveRequest;
use Inventory\V1\SetStockRequest;
use Throwable;
class GrpcInventoryClient implements InventoryClientInterface
{
    private InventoryServiceClient $client;
    public function __construct()
    {
        $this->client = new InventoryServiceClient(config('inventory.address'), [
            'credentials' => ChannelCredentials::createInsecure(),
        ]);
    }
    public function reserve(string $reservationId, array $items): void
    {
        $request = new ReserveRequest(['reservation_id' => $reservationId]);
        $messages = [];
        foreach ($items as $productId => $quantity) {
            $messages[] = new ReservationItem(['product_id' => (int) $productId, 'quantity' => (int) $quantity]);
        }
        $request->setItems($messages);
        $this->call(fn ($options) => $this->client->Reserve($request, [], $options), 'Inventory reservation failed.');
    }
    public function release(string $reservationId): void
    {
        $request = new ReleaseRequest(['reservation_id' => $reservationId]);
        $this->call(fn ($options) => $this->client->Release($request, [], $options), 'Inventory release failed.');
    }
    public function ensureProduct(int $productId, string $name, int $stock): void
    {
        $request = new EnsureProductRequest(['product_id' => $productId, 'name' => $name, 'stock' => $stock]);
        $this->call(fn ($options) => $this->client->EnsureProduct($request, [], $options), 'Inventory product synchronization failed.');
    }
    public function setStock(int $productId, int $stock): void
    {
        $request = new SetStockRequest(['product_id' => $productId, 'stock' => $stock]);
        $this->call(fn ($options) => $this->client->SetStock($request, [], $options), 'Inventory stock synchronization failed.');
    }
    private function call(callable $operation, string $message): void
    {
        try {
            [$response, $status] = $operation(['timeout' => config('inventory.timeout_ms') * 1000])->wait();
        } catch (Throwable $exception) {
            throw ApiException::conflict($message . ' The inventory service is unavailable.');
        }
        if ($status->code !== \Grpc\STATUS_OK) {
            $detail = $status->details ?: $message;
            throw ApiException::conflict($detail);
        }
    }

}
