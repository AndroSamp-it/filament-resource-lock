<?php

namespace Androsamp\FilamentResourceLock\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class TestUser extends Model
{
    protected $table = 'users';

    protected $guarded = [];
}
