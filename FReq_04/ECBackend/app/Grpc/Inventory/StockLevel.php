<?php

namespace App\Grpc\Inventory;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

class StockLevel extends \Google\Protobuf\Internal\Message
{
    protected $product_id = 0;

    protected $stock = 0;

    public function __construct($data = NULL) {
        \App\Grpc\Metadata\Inventory::initOnce();
        parent::__construct($data);
    }

    public function getProductId()
    {
        return $this->product_id;
    }

    public function setProductId($var)
    {
        GPBUtil::checkInt64($var);
        $this->product_id = $var;

        return $this;
    }

    public function getStock()
    {
        return $this->stock;
    }

    public function setStock($var)
    {
        GPBUtil::checkInt64($var);
        $this->stock = $var;

        return $this;
    }
}

