<?php

namespace App\Grpc\Inventory;

class InventoryServiceClient extends \Grpc\BaseStub
{
    private const SERVICE = '/inventory.v1.InventoryService/';

    public function __construct($hostname, $opts, $channel = null)
    {
        parent::__construct($hostname, $opts, $channel);
    }

    public function Ping(PingRequest $argument, $metadata = [], $options = [])
    {
        return $this->_simpleRequest(self::SERVICE . 'Ping', $argument, [PingResponse::class, 'decode'], $metadata, $options);
    }

    public function GetStock(GetStockRequest $argument, $metadata = [], $options = [])
    {
        return $this->_simpleRequest(self::SERVICE . 'GetStock', $argument, [GetStockResponse::class, 'decode'], $metadata, $options);
    }

    public function GetStocks(GetStocksRequest $argument, $metadata = [], $options = [])
    {
        return $this->_simpleRequest(self::SERVICE . 'GetStocks', $argument, [GetStocksResponse::class, 'decode'], $metadata, $options);
    }

    public function ListAvailableProductIds(ListAvailableProductIdsRequest $argument, $metadata = [], $options = [])
    {
        return $this->_simpleRequest(self::SERVICE . 'ListAvailableProductIds', $argument, [ListAvailableProductIdsResponse::class, 'decode'], $metadata, $options);
    }

    public function SetStock(SetStockRequest $argument, $metadata = [], $options = [])
    {
        return $this->_simpleRequest(self::SERVICE . 'SetStock', $argument, [SetStockResponse::class, 'decode'], $metadata, $options);
    }

    public function DeleteStock(DeleteStockRequest $argument, $metadata = [], $options = [])
    {
        return $this->_simpleRequest(self::SERVICE . 'DeleteStock', $argument, [DeleteStockResponse::class, 'decode'], $metadata, $options);
    }

    public function ReserveStock(ReserveStockRequest $argument, $metadata = [], $options = [])
    {
        return $this->_simpleRequest(self::SERVICE . 'ReserveStock', $argument, [ReserveStockResponse::class, 'decode'], $metadata, $options);
    }

    public function ReleaseStock(ReleaseStockRequest $argument, $metadata = [], $options = [])
    {
        return $this->_simpleRequest(self::SERVICE . 'ReleaseStock', $argument, [ReleaseStockResponse::class, 'decode'], $metadata, $options);
    }
}
