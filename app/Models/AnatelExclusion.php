<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class AnatelExclusion extends Model { protected $fillable=['lista_id','user_id','type','value','value_hash','active','notes']; protected $casts=['active'=>'boolean']; public function lista(): BelongsTo { return $this->belongsTo(Lista::class); } public function user(): BelongsTo { return $this->belongsTo(User::class); } }
