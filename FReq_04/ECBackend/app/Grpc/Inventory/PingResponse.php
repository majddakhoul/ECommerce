<?php

namespace App\Grpc\Inventory;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

class PingResponse extends \Google\Protobuf\Internal\Message
{
    protected $ok = false;

    public function __construct($data = NULL) {
        \App\Grpc\Metadata\Inventory::initOnce();
        parent::__construct($data);
    }

    public function getOk()
    {
        return $this->ok;
    }

    public function setOk($var)
    {
        GPBUtil::checkBool($var);
        $this->ok = $var;

        return $this;
    }
}

