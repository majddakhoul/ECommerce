<?php

namespace App\Grpc\Inventory;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

class ReserveStockResponse extends \Google\Protobuf\Internal\Message
{
    protected $reserved = false;

    protected $reservation_id = '';

    private $shortages;

    public function __construct($data = NULL) {
        \App\Grpc\Metadata\Inventory::initOnce();
        parent::__construct($data);
    }

    public function getReserved()
    {
        return $this->reserved;
    }

    public function setReserved($var)
    {
        GPBUtil::checkBool($var);
        $this->reserved = $var;

        return $this;
    }

    public function getReservationId()
    {
        return $this->reservation_id;
    }

    public function setReservationId($var)
    {
        GPBUtil::checkString($var, True);
        $this->reservation_id = $var;

        return $this;
    }

    public function getShortages()
    {
        return $this->shortages;
    }

    public function setShortages($var)
    {
        $arr = GPBUtil::checkRepeatedField($var, \Google\Protobuf\Internal\GPBType::MESSAGE, \App\Grpc\Inventory\StockShortage::class);
        $this->shortages = $arr;

        return $this;
    }
}

