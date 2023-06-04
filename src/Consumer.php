<?php

namespace FlyCms\MysqlQueue;

interface Consumer
{
    public function consume($data);
}
