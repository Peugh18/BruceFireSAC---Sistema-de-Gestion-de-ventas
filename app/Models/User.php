<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Concerns\HasTeams;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property int|null $current_team_id
 * @property int|null $sede_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team|null $currentTeam
 * @property-read Sede|null $sede
 * @property-read Collection<int, Team> $ownedTeams
 * @property-read Collection<int, Membership> $teamMemberships
 * @property-read Collection<int, Team> $teams
 */
#[Fillable(['name', 'email', 'password', 'current_team_id', 'sede_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    use HasRoles, HasTeams {
        // "teams" ya lo define HasTeams (nuestro propio concepto de workspace);
        // el "teams" de spatie es para su feature de permisos-por-equipo, que
        // no usamos (config/permission.php: 'teams' => false).
        HasTeams::teams insteadof HasRoles;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Sede única del trabajador. Null significa que ve todas las sedes.
     *
     * @return BelongsTo<Sede, $this>
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * Sede a la que se acota lo operativo del usuario. El Gerente y los
     * usuarios sin sede asignada ven todas las sedes (devuelve null).
     */
    public function sedeRestringidaId(): ?int
    {
        if ($this->hasRole('Gerente')) {
            return null;
        }

        return $this->sede_id;
    }

    /**
     * Sede de cuyo stock puede ver el usuario: la de su almacén efectivo
     * (una tienda usa el almacén asignado). Null si ve todas las sedes.
     */
    public function almacenRestringidoId(): ?int
    {
        $sedeId = $this->sedeRestringidaId();

        return $sedeId ? Sede::find($sedeId)?->almacenEfectivoId() : null;
    }

    /**
     * Registros de auditoría generados por este usuario (§37).
     *
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
