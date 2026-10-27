<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class PlatformAuditLog extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['meta' => 'array', 'created_at' => 'datetime'];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }
}
