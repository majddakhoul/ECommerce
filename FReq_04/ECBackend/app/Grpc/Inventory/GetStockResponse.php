<?php

namespace App\Grpc\Inventory;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

class GetStockResponse extends \Google\Protobuf\Internal\Message
{
    protected $found = false;

    protected $stock = 0;

    public function __construct($data = NULL) {
        \App\Grpc\Metadata\Inventory::initOnce();
        parent::__construct($data);
    }

    public function getFound()
    {
        return $this->found;
    }

    public function setFound($var)
    {
        GPBUtil::checkBool($var);
        $this->found = $var;

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

