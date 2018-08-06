<?php

namespace Tests\Helpers;


use Illuminate\Database\Eloquent\Model;

class Person extends Model
{
    protected $guarded = [];

    public function invalid()
    {
        // a relationship goes here.
        return 'INVALID';
    }

    public function status()
    {
        return 'status';
    }

    public function getNumber()
    {
        return 'number';
    }
}
