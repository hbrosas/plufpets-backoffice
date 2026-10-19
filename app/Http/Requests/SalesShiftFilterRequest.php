<?php

namespace App\Http\Requests;

class SalesShiftFilterRequest extends SalesFilterRequest
{
    protected function defaultPreset(): string
    {
        return '30d';
    }
}
