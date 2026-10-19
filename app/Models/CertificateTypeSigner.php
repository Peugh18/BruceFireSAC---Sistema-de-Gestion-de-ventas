<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Papel de una persona en un tipo de certificado: en qué orden firma y con
 * qué cargo (la misma persona firma como administrador o como instructor).
 *
 * @property int $certificate_type_id
 * @property int $signer_id
 * @property int $orden
 * @property string|null $cargo
 */
class CertificateTypeSigner extends Pivot
{
    protected $table = 'certificate_type_signer';

    protected function casts(): array
    {
        return ['orden' => 'integer'];
    }
}
