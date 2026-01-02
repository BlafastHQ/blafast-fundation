<?php

declare(strict_types=1);

namespace Blafast\Foundation\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'name',
        'email',
    ];
}
