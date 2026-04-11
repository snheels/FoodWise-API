<?php

namespace Database\Seeders;

use App\Models\Food;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FoodSeeder extends Seeder
{
    public function run()
    {
        $user = User::where('email', 'test@example.com')->first();
        if (!$user) {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => bcrypt('password')
            ]);
        }

        Food::create([
            'user_id' => $user->id,
            'nama' => 'Susu',
            'jenis' => 'Susu',
            'tanggal_beli' => '2026-04-02',
            'tanggal_kadaluarsa' => '2026-04-09',
            'jumlah' => 2,
            'satuan' => 'pcs'
        ]);

        Food::create([
            'user_id' => $user->id,
            'nama' => 'Roti',
            'jenis' => 'Masakan jadi',
            'tanggal_beli' => '2026-04-10',
            'tanggal_kadaluarsa' => '2026-04-23',
            'jumlah' => 5,
            'satuan' => 'pcs'
        ]);

        Food::create([
            'user_id' => $user->id,
            'nama' => 'Dimsum Frozen',
            'jenis' => 'Frozen',
            'tanggal_beli' => '2026-02-28',
            'tanggal_kadaluarsa' => '2026-04-12',
            'jumlah' => 2,
            'satuan' => 'kg'
        ]);

        Food::create([
            'user_id' => $user->id,
            'nama' => 'Mangga',
            'jenis' => 'Buah',
            'tanggal_beli' => '2026-02-07',
            'jumlah' => 4,
            'satuan' => 'kg'
        ]);

        Food::create([
            'user_id' => $user->id,
            'nama' => 'Apel',
            'jenis' => 'Buah',
            'tanggal_beli' => '2026-03-01',
            'tanggal_kadaluarsa' => '2026-03-15',
            'jumlah' => 3,
            'satuan' => 'kg'
        ]);

        Food::create([
            'user_id' => $user->id,
            'nama' => 'Daging Sapi',
            'jenis' => 'Daging',
            'tanggal_beli' => '2026-04-05',
            'tanggal_kadaluarsa' => '2026-04-12',
            'jumlah' => 1,
            'satuan' => 'kg'
        ]);

        Food::create([
            'user_id' => $user->id,
            'nama' => 'Telur',
            'jenis' => 'Susu',
            'tanggal_beli' => '2026-04-08',
            'tanggal_kadaluarsa' => '2026-04-12',
            'jumlah' => 10,
            'satuan' => 'pcs'
        ]);

        Food::create([
            'user_id' => $user->id,
            'nama' => 'Yogurt',
            'jenis' => 'Susu',
            'tanggal_beli' => '2026-04-01',
            'tanggal_kadaluarsa' => '2026-04-12',
            'jumlah' => 4,
            'satuan' => 'pcs'
        ]);
    }
}
