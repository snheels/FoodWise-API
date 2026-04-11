<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Food extends Model
{
    protected $table = 'foods';

    use HasFactory;

    protected $fillable = [
        'user_id',
        'nama',
        'jenis',
        'tanggal_beli',
        'tanggal_kadaluarsa',
        'jumlah',
        'status_penggunaan',
        'google_event_id',
        'terakhir_diingatkan'
    ];

    protected $casts = [
        'tanggal_beli' => 'date:Y-m-d',
        'tanggal_kadaluarsa' => 'date:Y-m-d',
        'terakhir_diingatkan' => 'datetime',
    ];

    // buah/sayur auto kadaluarsa
    public function getTanggalKadaluarsaAttribute($value)
    {
        if ($value) return $value;

        // Buah/Sayur auto +7 hari
        if (in_array($this->jenis, ['Buah', 'Sayur'])) {
            return $this->tanggal_beli->addDays(7);
        }

        return null;
    }

    // Status pintar
    public function getStatusAttribute()
    {
        if ($this->status_penggunaan !== 'tersedia') {
            return $this->status_penggunaan;
        }

        $kadaluarsa = $this->tanggal_kadaluarsa;
        if (!$kadaluarsa) return 'tidak_kadaluarsa';

        $hariSisa = $kadaluarsa->diffInDays(now());
        if ($hariSisa < 0) return 'kadaluarsa';
        if ($hariSisa <= 3) return 'warning';
        return 'aman';
    }
}
