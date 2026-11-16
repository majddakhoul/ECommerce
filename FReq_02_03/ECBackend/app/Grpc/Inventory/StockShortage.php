<?php

namespace App\Grpc\Inventory;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

class StockShortage extends \Google\Protobuf\Internal\Message
{
    protected $product_id = 0;

    protected $requested = 0;

    protected $available = 0;

    protected $reason = 0;

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

    public function getRequested()
    {
        return $this->requested;
    }

    public function setRequested($var)
    {
        GPBUtil::checkInt64($var);
        $this->requested = $var;

        return $this;
    }

    public function getAvailable()
    {
        return $this->available;
    }

    public function setAvailable($var)
    {
        GPBUtil::checkInt64($var);
        $this->available = $var;

        return $this;
    }

    public function getReason()
    {
        return $this->reason;
    }

    public function setReason($var)
    {
        GPBUtil::checkEnum($var, \App\Grpc\Inventory\ShortageReason::class);
        $this->reason = $var;

        return $this;
    }
}

