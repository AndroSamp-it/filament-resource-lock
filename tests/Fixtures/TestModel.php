<?php

namespace Androsamp\FilamentResourceLock\Tests\Fixtures;

use Androsamp\FilamentResourceLock\Concerns\HasResourceLocks;
use Illuminate\Database\Eloquent\Model;

class TestModel extends Model
{
    use HasResourceLocks;

    protected $table = 'test_models';

    protected $guarded = [];
}
