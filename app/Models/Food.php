<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

#[Fillable([
    'user_id',
    'nama',
    'jenis',
    'tanggal_beli',
    'tanggal_kadaluarsa',
    'jumlah',
    'status_penggunaan'
])]
class Food extends Model
{
    use HasFactory;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'tanggal_beli' => 'date',
            'tanggal_kadaluarsa' => 'date',
        ];
    }
}
