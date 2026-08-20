<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'empresa_id', 'avatar'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isCliente(): bool
    {
        return $this->role === 'cliente';
    }

    public static function avatarOptions(): array
    {
        return [
            'raposa' => ['label' => 'Raposa', 'symbol' => '🦊'],
            'lobo' => ['label' => 'Lobo', 'symbol' => '🐺'],
            'coruja' => ['label' => 'Coruja', 'symbol' => '🦉'],
            'gato' => ['label' => 'Gato', 'symbol' => '🐱'],
            'aguia' => ['label' => 'Águia', 'symbol' => '🦅'],
            'robo' => ['label' => 'Robô', 'symbol' => '🤖'],
            'astronauta' => ['label' => 'Astronauta', 'symbol' => '🧑‍🚀'],
            'tecnico' => ['label' => 'Técnico', 'symbol' => '🎧'],
            'operador' => ['label' => 'Operador', 'symbol' => '🖱️'],
            'escudo' => ['label' => 'Escudo', 'symbol' => '🛡️'],
            'rede' => ['label' => 'Rede', 'symbol' => '🌐'],
            'servidor' => ['label' => 'Servidor', 'symbol' => '🖥️'],
            'engenheiro' => ['label' => 'Engenheiro', 'symbol' => '👷'],
            'ceo' => ['label' => 'CEO', 'symbol' => '🧑‍💼'],
            'burro' => ['label' => 'Burro', 'symbol' => '🐴'],
        ];
    }

    public function avatarSymbol(): ?string
    {
        $options = self::avatarOptions();

        return $options[$this->avatar]['symbol'] ?? null;
    }
}
