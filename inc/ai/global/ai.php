<?php

abstract class GlobalAI
{
    public function test($request)
    {
        return $this->request($request);
    }
}
