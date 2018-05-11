<?php

namespace Common\Common\Logic\Transformer;

abstract class BaseTransformer {

    public function transformCollection($data)
    {
        return array_map([$this,'transform'], $data);
    }

    public abstract function transform($item);
}