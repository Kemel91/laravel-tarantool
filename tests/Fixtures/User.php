<?php

declare(strict_types=1);

namespace Chocofamily\Tarantool\Tests\Fixtures;

use Chocofamily\Tarantool\Eloquent\Model;

class User extends Model
{
    protected $table = 'users';

    public $timestamps = false;

    protected $guarded = [];
}
