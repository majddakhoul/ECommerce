<?php

namespace App\Grpc\Inventory;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

class SetStockResponse extends \Google\Protobuf\Internal\Message
{
    protected $stock = 0;

    public function __construct($data = NULL) {
        \App\Grpc\Metadata\Inventory::initOnce();
        parent::__construct($data);
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

