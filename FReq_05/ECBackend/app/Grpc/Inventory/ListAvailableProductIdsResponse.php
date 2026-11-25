<?php

namespace App\Grpc\Inventory;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

class ListAvailableProductIdsResponse extends \Google\Protobuf\Internal\Message
{
    private $product_ids;

    public function __construct($data = NULL) {
        \App\Grpc\Metadata\Inventory::initOnce();
        parent::__construct($data);
    }

    public function getProductIds()
    {
        return $this->product_ids;
    }

    public function setProductIds($var)
    {
        $arr = GPBUtil::checkRepeatedField($var, \Google\Protobuf\Internal\GPBType::INT64);
        $this->product_ids = $arr;

        return $this;
    }
}

