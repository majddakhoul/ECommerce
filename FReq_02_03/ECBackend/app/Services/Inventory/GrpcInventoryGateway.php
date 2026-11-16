<?php

namespace App\Services\Inventory;

use App\Exceptions\InventoryUnavailableException;
use App\Grpc\Inventory\DeleteStockRequest;
use App\Grpc\Inventory\GetStockRequest;
use App\Grpc\Inventory\GetStocksRequest;
use App\Grpc\Inventory\InventoryServiceClient;
use App\Grpc\Inventory\ListAvailableProductIdsRequest;
use App\Grpc\Inventory\ReleaseStockRequest;
use App\Grpc\Inventory\ReservationItem;
use App\Grpc\Inventory\ReserveStockRequest;
use App\Grpc\Inventory\SetStockRequest;
use App\Grpc\Inventory\ShortageReason;
use Grpc\ChannelCredentials;
use Grpc\UnaryCall;
use Illuminate\Support\Facades\Log;

class GrpcInventoryGateway implements InventoryGatewayInterface
{
    private const MICROSECONDS_PER_SECOND = 1000000;

    private ?InventoryServiceClient $client = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $apiKey,
        private readonly float $timeoutSeconds
    ) {}

    public function getStock(int $productId): int
    {
        $request = (new GetStockRequest)->setProductId($productId);

        return (int) $this->invoke('GetStock', $this->client()->GetStock($request, $this->metadata(), $this->options()))->getStock();
    }

    public function getStocks(array $productIds): array
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));

        if ($productIds === []) {
            return [];
        }

        $request = (new GetStocksRequest)->setProductIds($productIds);
        $response = $this->invoke('GetStocks', $this->client()->GetStocks($request, $this->metadata(), $this->options()));

        $levels = [];

        foreach ($response->getLevels() as $level) {
            $levels[$level->getProductId()] = (int) $level->getStock();
        }

        return $levels;
    }

    public function availableProductIds(): array
    {
        $response = $this->invoke(
            'ListAvailableProductIds',
            $this->client()->ListAvailableProductIds(new ListAvailableProductIdsRequest, $this->metadata(), $this->options())
        );

        $ids = [];

        foreach ($response->getProductIds() as $id) {
            $ids[] = (int) $id;
        }

        return $ids;
    }

    public function setStock(int $productId, int $stock): void
    {
        $request = (new SetStockRequest)->setProductId($productId)->setStock($stock);

        $this->invoke('SetStock', $this->client()->SetStock($request, $this->metadata(), $this->options()));
    }

    public function deleteStock(int $productId): void
    {
        $request = (new DeleteStockRequest)->setProductId($productId);

        $this->invoke('DeleteStock', $this->client()->DeleteStock($request, $this->metadata(), $this->options()));
    }

    public function reserve(string $reservationId, array $items): ReservationResult
    {
        $lines = [];

        foreach ($items as $productId => $quantity) {
            $lines[] = (new ReservationItem)->setProductId((int) $productId)->setQuantity((int) $quantity);
        }

        $request = (new ReserveStockRequest)->setReservationId($reservationId)->setItems($lines);
        $response = $this->invoke('ReserveStock', $this->client()->ReserveStock($request, $this->metadata(), $this->options()));

        $shortages = [];

        foreach ($response->getShortages() as $shortage) {
            $shortages[] = new StockShortage(
                (int) $shortage->getProductId(),
                (int) $shortage->getRequested(),
                (int) $shortage->getAvailable(),
                $shortage->getReason() === ShortageReason::PRODUCT_NOT_FOUND
                    ? StockShortage::PRODUCT_NOT_FOUND
                    : StockShortage::INSUFFICIENT_STOCK
            );
        }

        return new ReservationResult($response->getReserved(), $reservationId, $shortages);
    }

    public function release(string $reservationId): void
    {
        $request = (new ReleaseStockRequest)->setReservationId($reservationId);

        $this->invoke('ReleaseStock', $this->client()->ReleaseStock($request, $this->metadata(), $this->options()));
    }

    private function client(): InventoryServiceClient
    {
        return $this->client ??= new InventoryServiceClient("{$this->host}:{$this->port}", [
            'credentials' => ChannelCredentials::createInsecure(),
        ]);
    }

    private function metadata(): array
    {
        return $this->apiKey === '' ? [] : ['x-api-key' => [$this->apiKey]];
    }

    private function options(): array
    {
        return ['timeout' => (int) ($this->timeoutSeconds * self::MICROSECONDS_PER_SECOND)];
    }

    private function invoke(string $rpc, UnaryCall $call)
    {
        [$response, $status] = $call->wait();

        if ($status->code !== \Grpc\STATUS_OK || $response === null) {
            Log::error('Inventory gRPC call failed', [
                'rpc' => $rpc,
                'code' => $status->code,
                'details' => $status->details,
            ]);

            throw new InventoryUnavailableException;
        }

        return $response;
    }
}
