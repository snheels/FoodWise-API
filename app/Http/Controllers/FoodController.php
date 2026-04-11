<?php

namespace App\Http\Controllers;

use App\Models\Food;
use Illuminate\Http\Request;
use Carbon\Carbon;

class FoodController extends Controller
{
    // HALAMAN SemuaData
    public function index(Request $request)
    {
        $userId = 1;
        $filter = $request->get('filter', 'semua');
        $perPage = $request->get('per_page', 10);

        $query = Food::where('user_id', $userId)
            ->where('status_penggunaan', 'tersedia');

        if ($filter === 'hampir_kadaluarsa') {
            $query->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNotNull('tanggal_kadaluarsa')
                        ->where('tanggal_kadaluarsa', '<', now());
                })->orWhere(function ($q3) {
                    $q3->whereNotNull('tanggal_kadaluarsa')
                        ->where('tanggal_kadaluarsa', '>=', now())
                        ->where('tanggal_kadaluarsa', '<=', now()->addDays(3));
                });
            });
        }

        $foods = $query->latest()->paginate($perPage);

        $formattedFoods = $foods->map(function ($food) {
            return [
                'id' => $food->id,
                'nama' => $food->nama,
                'kategori' => $food->jenis,
                'tanggalBeli' => $food->tanggal_beli
                    ? \Carbon\Carbon::parse($food->tanggal_beli)->format('d F Y')
                    : 'N/A',
                'kadaluarsa' => $food->tanggal_kadaluarsa
                    ? \Carbon\Carbon::parse($food->tanggal_kadaluarsa)->format('d F Y')
                    : 'N/A',
                'total' => $food->jumlah . ' ' . ($food->satuan ?? 'Pcs')
            ];
        });

        return response()->json([
            'data' => $formattedFoods,
            'meta' => [
                'current_page' => $foods->currentPage(),
                'last_page' => $foods->lastPage(),
                'per_page' => $foods->perPage(),
                'total' => $foods->total(),
                'from' => $foods->firstItem(),
                'to' => $foods->lastItem(),
            ]
        ]);
    }


    // HALAMAN AddFood
    public function store(Request $request)
    {
        $data = $request->validate([
            'nama' => 'required|string|max:255',
            'jenis' => 'required|string|in:Frozen,Buah,Sayur,Daging,Masakan jadi,Minuman Kaleng,Susu,Jus,Snack',
            'tanggal_beli' => 'required|date',
            'tanggal_kadaluarsa' => 'nullable|date|after_or_equal:tanggal_beli',
            'jumlah' => 'required|integer|min:1',
            'satuan' => 'sometimes|string|in:pcs,kg'
        ]);

        $data['user_id'] = 1; // sementara, nanti ganti dengan auth()->id()
        $data['status_penggunaan'] = 'tersedia';

        $food = Food::create($data);

        return response()->json([
            'message' => 'Makanan berhasil ditambahkan!',
            'data' => $food
        ], 201);
    }


    // HALAMAN Detail
    public function show($id)
    {
        $food = Food::where('id', $id)->where('user_id', 1)->firstOrFail();

        return response()->json([
            'id' => $food->id,
            'nama' => $food->nama,
            'kategori' => $food->jenis,
            'tanggalBeli' => $food->tanggal_beli
                ? \Carbon\Carbon::parse($food->tanggal_beli)->format('d F Y')
                : 'N/A',
            'kadaluarsa' => $food->tanggal_kadaluarsa
                ? \Carbon\Carbon::parse($food->tanggal_kadaluarsa)->format('d F Y')
                : 'N/A',
            'total' => $food->jumlah . ' ' . ($food->satuan ?? 'Pcs')
        ]);
    }

    // Button Sudah dikonsumsi
    public function consume($id)
    {
        $food = Food::where('id', $id)->where('user_id', 1)->firstOrFail();
        $food->update(['status_penggunaan' => 'habis']);
        return response()->json(['message' => 'Sudah dikonsumsi']);
    }

    // Button Dibuang
    public function discard($id)
    {
        $food = Food::where('id', $id)->where('user_id', 1)->firstOrFail();
        $food->update(['status_penggunaan' => 'dibuang']);
        return response()->json(['message' => 'Dibuang']);
    }

    // Button Ingatkan lagi
    public function remind($id)
    {
        $food = Food::where('id', $id)->where('user_id', 1)->firstOrFail();
        $food->update(['terakhir_diingatkan' => now()]);
        return response()->json(['message' => 'Di ingatkan lagi']);
    }

    // DASHBOARD
    // StatsCard
    public function dashboard(Request $request)
    {
        $userId = 1;  // SEMENTARA, nanti ganti dengan auth()->id()

        return response()->json([
            'total_makanan' => Food::where('user_id', $userId)->count(),
            'jauh_dari_kadaluarsa' => Food::where('user_id', $userId)
                ->where('status_penggunaan', 'tersedia')
                ->where(function ($q) {
                    $q->whereNull('tanggal_kadaluarsa')
                        ->orWhere('tanggal_kadaluarsa', '>', now()->addDays(3));
                })->count(),
            'kadaluarsa' => Food::where('user_id', $userId)
                ->where(function ($q) {
                    $q->where('status_penggunaan', 'dibuang')
                        ->orWhere(function ($q2) {
                            $q2->where('status_penggunaan', 'tersedia')
                                ->whereNotNull('tanggal_kadaluarsa')
                                ->where('tanggal_kadaluarsa', '<', now());
                        });
                })->count(),
        ]);
    }

    // Table
    public function expiringSoon()
    {
        $foods = Food::where('user_id', 1)
            ->where('status_penggunaan', 'tersedia')
            ->whereNotNull('tanggal_kadaluarsa')
            ->where('tanggal_kadaluarsa', '>=', now())
            ->where('tanggal_kadaluarsa', '<=', now()->addDays(3))
            ->take(6)
            ->get()
            ->map(fn($food) => [
                'id' => $food->id,
                'nama' => $food->nama,
                'kategori' => $food->jenis,
                'tanggalBeli' => $food->tanggal_beli
                    ? \Carbon\Carbon::parse($food->tanggal_beli)->format('d F Y')
                    : 'N/A',
                'kadaluarsa' => $food->tanggal_kadaluarsa
                    ? \Carbon\Carbon::parse($food->tanggal_kadaluarsa)->format('d F Y')
                    : 'N/A',
                'total' => $food->jumlah . ' ' . ($food->satuan ?? 'Pcs')
            ]);

        return response()->json($foods);
    }

    // Chart
    public function wasteChart()
    {
        $userId = 1; // sementara, nanti ganti dengan auth()->id()

        $months = [];
        $pcsData = [];
        $kgData = [];

        // 6 bulan terakhir (dari 5 bulan lalu sampai sekarang)
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $months[] = $month->format('M');

            // Hitung total pcs yang dibuang bulan ini
            $pcs = Food::where('user_id', $userId)
                ->where('status_penggunaan', 'dibuang')
                ->where('satuan', 'pcs')
                ->whereMonth('tanggal_beli', $month->month)
                ->whereYear('tanggal_beli', $month->year)
                ->sum('jumlah');

            // Hitung total kg yang dibuang bulan ini
            $kg = Food::where('user_id', $userId)
                ->where('status_penggunaan', 'dibuang')
                ->where('satuan', 'kg')
                ->whereMonth('tanggal_beli', $month->month)
                ->whereYear('tanggal_beli', $month->year)
                ->sum('jumlah');

            $pcsData[] = (int) $pcs;
            $kgData[] = (int) $kg;
        }

        return response()->json([
            'labels' => $months,
            'pcs' => $pcsData,
            'kg' => $kgData
        ]);
    }
}
