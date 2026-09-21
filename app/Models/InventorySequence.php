<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventorySequence extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'prefijo',
        'correlativo_actual',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'correlativo_actual' => 'integer',
        ];
    }
}
