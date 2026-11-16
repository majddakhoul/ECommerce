<?php

namespace App\Grpc\Inventory;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

class ReleaseStockRequest extends \Google\Protobuf\Internal\Message
{
    protected $reservation_id = '';

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
}

