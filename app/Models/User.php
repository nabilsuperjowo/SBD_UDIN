<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $table = 'pengguna';
    protected $primaryKey = 'id_pengguna';
    public const CREATED_AT = 'dibuat_pada';
    public const UPDATED_AT = null;
    protected $guarded = ['id_pengguna'];
    protected $hidden = ['password_hash', 'remember_token'];

    public function getAuthPasswordName(): string { return 'password_hash'; }

    protected function casts(): array
    {
        return ['password_hash' => 'hashed', 'status_aktif' => 'boolean'];
    }
}
