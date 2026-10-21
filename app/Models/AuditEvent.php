<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AuditEvent extends Model
{
    protected $fillable = ['account_id','actor_id','action','metadata'];
    protected function casts(): array { return ['metadata'=>'array']; }
    public function actor() { return $this->belongsTo(User::class, 'actor_id'); }
}
