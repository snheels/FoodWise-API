<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Food;
use Carbon\Carbon;

class FoodController extends Controller
{
    // get all (munculin semua data)
    public function index()
    {
        return Food::latest()->get()->map(function ($food) {
            return [
                'id' => $food->id,
                'nama' => $food->nama,
                'kategori' => ucfirst($food->jenis),
                'tanggal_beli' => $food->tanggal_beli->format('d F Y'),
                'info_expired' => $food->tanggal_kadaluarsa
                    ? 'Kadaluarsa: ' . $food->tanggal_kadaluarsa->format('d F Y')
                    : 'Baik digunakan segera',
                'jumlah' => $food->jumlah,
                'satuan' => 'Pcs',
                'status' => $this->getStatus($food),
            ];
        });
    }

    // create
    public function store(Request $request)
    {
        $data = $request->all();

        // kalau jenis tertentu → null
        if ($this->tanpaExpired($data['jenis'])) {
            $data['tanggal_kadaluarsa'] = null;
        }

        $food = Food::create([
            'user_id' => $data['user_id'],
            'nama' => $data['nama'],
            'jenis' => $data['jenis'],
            'tanggal_beli' => $data['tanggal_beli'],
            'tanggal_kadaluarsa' => $data['tanggal_kadaluarsa'],
            'jumlah' => $data['jumlah'],
            'status_penggunaan' => 'tersedia'
        ]);

        return $food;
    }

    // detail
    public function show($id)
    {
        return Food::findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        //
    }

    // delete
    public function destroy($id)
    {
        Food::destroy($id);
        return response()->json(['message' => 'Deleted']);
    }

    // dashboard data
    public function dashboard()
    {
        return [
            'total' => Food::count(),
            'safe' => Food::where('tanggal_kadaluarsa', '>', now()->addDays(3))->count(),
            'expired' => Food::where('tanggal_kadaluarsa', '<', now())->count(),
        ];
    }

    // expired
    public function expiringSoon()
    {
        return Food::whereBetween('tanggal_kadaluarsa', [
            Carbon::today(),
            Carbon::today()->addDays(3)
        ])->get();
    }

    // tanpa expired
    private function tanpaExpired($jenis)
    {
        return in_array($jenis, [
            'Buah',
            'Sayur',
            'Masakan jadi',
            'Jus'
        ]);
    }

    // warna ui status
    private function getStatus($food)
    {
        $today = now();

        if ($food->tanggal_kadaluarsa < $today) {
            return 'expired'; // merah
        }

        if ($food->tanggal_kadaluarsa <= $today->copy()->addDays(3)) {
            return 'warning'; // kuning
        }

        return 'safe'; // biru/hijau
    }
}
