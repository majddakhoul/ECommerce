<?php

namespace App\Grpc\Inventory;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

class DeleteStockResponse extends \Google\Protobuf\Internal\Message
{
    protected $deleted = false;

    public function __construct($data = NULL) {
        \App\Grpc\Metadata\Inventory::initOnce();
        parent::__construct($data);
    }

    public function getDeleted()
    {
        return $this->deleted;
    }

    public function setDeleted($var)
    {
        GPBUtil::checkBool($var);
        $this->deleted = $var;

        return $this;
    }
}

