<?php

namespace App\Models;

use App\Services\RpzDirtyMarker;
use Illuminate\Database\Eloquent\Model;

class EmpresaDomainException extends Model
{
    protected $fillable = ['empresa_id', 'dominio', 'motivo'];

    protected static function booted(): void
    {
        foreach (['saved', 'deleted'] as $event) {
            static::$event(static function (self $exception): void {
                app(RpzDirtyMarker::class)->markForCompany($exception->empresa_id);
            });
        }
    }
}
