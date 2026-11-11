<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property int $amount
 * @property string $source_type
 * @property int|null $source_id
 */
class XpLog extends Model
{
    protected $guarded = ['id'];
}
