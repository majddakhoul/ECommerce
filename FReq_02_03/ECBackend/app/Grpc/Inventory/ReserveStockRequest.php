<?php

namespace App\Grpc\Inventory;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

class ReserveStockRequest extends \Google\Protobuf\Internal\Message
{
    protected $reservation_id = '';

    private $items;

    public function __construct($data = NULL) {
        \App\Grpc\Metadata\Inventory::initOnce();
        parent::__construct($data);
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

    public function getItems()
    {
        return $this->items;
    }

    public function setItems($var)
    {
        $arr = GPBUtil::checkRepeatedField($var, \Google\Protobuf\Internal\GPBType::MESSAGE, \App\Grpc\Inventory\ReservationItem::class);
        $this->items = $arr;

        return $this;
    }
}

