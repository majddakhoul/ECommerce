<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Inventory\V1;

/**
 */
class InventoryServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * @param \Inventory\V1\GetProductRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function GetProduct(\Inventory\V1\GetProductRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/inventory.v1.InventoryService/GetProduct',
        $argument,
        ['\Inventory\V1\ProductResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Inventory\V1\EnsureProductRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function EnsureProduct(\Inventory\V1\EnsureProductRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/inventory.v1.InventoryService/EnsureProduct',
        $argument,
        ['\Inventory\V1\ProductResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Inventory\V1\SetStockRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function SetStock(\Inventory\V1\SetStockRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/inventory.v1.InventoryService/SetStock',
        $argument,
        ['\Inventory\V1\ProductResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Inventory\V1\ReserveRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function Reserve(\Inventory\V1\ReserveRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/inventory.v1.InventoryService/Reserve',
        $argument,
        ['\Inventory\V1\ReserveResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Inventory\V1\ReleaseRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function Release(\Inventory\V1\ReleaseRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/inventory.v1.InventoryService/Release',
        $argument,
        ['\Inventory\V1\ReleaseResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Inventory\V1\HealthRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function Health(\Inventory\V1\HealthRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/inventory.v1.InventoryService/Health',
        $argument,
        ['\Inventory\V1\HealthResponse', 'decode'],
        $metadata, $options);
    }

}
