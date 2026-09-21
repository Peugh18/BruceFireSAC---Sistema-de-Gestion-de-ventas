<?php

namespace App\Models;

use Database\Factories\CompanyBankAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $banco
 * @property string $titular
 * @property string $numero_cuenta
 * @property string|null $cci
 * @property string $moneda
 * @property bool $activo
 * @property int $orden
 */
#[Fillable(['banco', 'titular', 'numero_cuenta', 'cci', 'moneda', 'activo', 'orden'])]
class CompanyBankAccount extends Model
{
    /** @use HasFactory<CompanyBankAccountFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}
