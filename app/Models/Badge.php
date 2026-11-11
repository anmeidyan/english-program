<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property string|null $icon
 * @property array{type:string, value?:int|string} $criteria
 */
class Badge extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['criteria' => 'array'];
    }
}
