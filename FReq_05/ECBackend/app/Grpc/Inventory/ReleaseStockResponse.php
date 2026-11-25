<?php

namespace App\Grpc\Inventory;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

class ReleaseStockResponse extends \Google\Protobuf\Internal\Message
{
    protected $released = false;

    protected $items_released = 0;

    public function __construct($data = NULL) {
        \App\Grpc\Metadata\Inventory::initOnce();
        parent::__construct($data);
    }

    public function getReleased()
    {
        return $this->released;
    }

    public function setReleased($var)
    {
        GPBUtil::checkBool($var);
        $this->released = $var;

        return $this;
    }

    public function getItemsReleased()
    {
        return $this->items_released;
    }

    public function setItemsReleased($var)
    {
        GPBUtil::checkInt64($var);
        $this->items_released = $var;

        return $this;
    }
}

