<?php

namespace App\Grpc\Inventory;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

class GetStocksResponse extends \Google\Protobuf\Internal\Message
{
    private $levels;

    public function __construct($data = NULL) {
        \App\Grpc\Metadata\Inventory::initOnce();
        parent::__construct($data);
    }

    public function getLevels()
    {
        return $this->levels;
    }

    public function setLevels($var)
    {
        $arr = GPBUtil::checkRepeatedField($var, \Google\Protobuf\Internal\GPBType::MESSAGE, \App\Grpc\Inventory\StockLevel::class);
        $this->levels = $arr;

        return $this;
    }
}

