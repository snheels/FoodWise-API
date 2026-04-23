<?php

namespace App\Http\Controllers;

use App\Models\Food;
use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FoodController extends Controller
{
    // HALAMAN SemuaData
    public function index(Request $request)
    {
        $userId = Auth::id(); // ✅ Ambil dari user yang login
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

        $data['user_id'] = Auth::id();
        $data['status_penggunaan'] = 'tersedia';

        if (!isset($data['satuan'])) {
            $data['satuan'] = 'pcs';
        }

        // LOGIKA KHUSUS BUAH & SAYUR
        if ($data['jenis'] === 'Buah' || $data['jenis'] === 'Sayur') {
            // Tanggal kadaluarsa = 3 hari setelah tanggal beli
            $tanggalBeli = Carbon::parse($data['tanggal_beli']);
            $data['tanggal_kadaluarsa'] = $tanggalBeli->addDays(3)->format('Y-m-d');
        }

        $food = Food::create($data);

        // Buat event Google Calendar jika user punya token dan tanggal kadaluarsa ada
        if ($food->tanggal_kadaluarsa && Auth::user()->google_token) {
            try {
                $calendarService = new GoogleCalendarService(Auth::user());

                // Untuk Buah & Sayur: reminder di hari kadaluarsa
                if ($food->jenis === 'Buah' || $food->jenis === 'Sayur') {
                    $tanggalKadaluarsa = Carbon::parse($food->tanggal_kadaluarsa);
                    $eventId = $calendarService->createEventOnExpiryDate($food, $tanggalKadaluarsa);
                } else {
                    $eventId = $calendarService->createEvent($food);
                }

                $food->google_event_id = $eventId;
                $food->save();
            } catch (\Exception $e) {
                Log::error('Google Calendar Error: ' . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Makanan berhasil ditambahkan!',
            'data' => $food
        ], 201);
    }

    // Update event calendar saat food diupdate
    public function update(Request $request, $id)
    {
        $food = Food::where('user_id', Auth::id())->findOrFail($id);

        $data = $request->validate([
            'nama' => 'sometimes|string|max:255',
            'jenis' => 'sometimes|string',
            'tanggal_beli' => 'sometimes|date',
            'tanggal_kadaluarsa' => 'nullable|date',
            'jumlah' => 'sometimes|integer|min:1',
            'satuan' => 'sometimes|string|in:pcs,kg'
        ]);

        $food->update($data);

        // Update event calendar jika ada perubahan tanggal kadaluarsa
        if ($food->tanggal_kadaluarsa && Auth::user()->google_token) {
            try {
                $calendarService = new GoogleCalendarService(Auth::user());
                $calendarService->updateEvent($food);
            } catch (\Exception $e) {
                Log::error('Google Calendar Error: ' . $e->getMessage());
            }
        }

        return response()->json(['message' => 'Makanan berhasil diupdate', 'data' => $food]);
    }

    // Remind - tambah reminder ke calendar
    public function remind($id)
    {
        try {
            $food = Food::where('user_id', Auth::id())->findOrFail($id);

            $today = Carbon::now()->startOfDay();
            $expiryDate = $food->tanggal_kadaluarsa ? Carbon::parse($food->tanggal_kadaluarsa)->startOfDay() : null;

            // Tentukan tanggal reminder berikutnya
            $nextReminder = null;
            if ($expiryDate) {
                $lastReminder = $food->terakhir_diingatkan ? Carbon::parse($food->terakhir_diingatkan) : $today;
                $nextReminder = $lastReminder->copy()->addDay();

                if ($nextReminder->greaterThan($expiryDate)) {
                    $nextReminder = $expiryDate;
                }
                if ($nextReminder->lessThan($today)) {
                    $nextReminder = $today;
                }
            }

            $food->terakhir_diingatkan = now();
            $food->save();

            // Update atau buat event di Google Calendar
            $eventId = null;
            if (Auth::user()->google_token && $food->tanggal_kadaluarsa && $nextReminder) {
                try {
                    $calendarService = new GoogleCalendarService(Auth::user());

                    if ($food->google_event_id) {
                        try {
                            $calendarService->deleteEvent($food->google_event_id);
                        } catch (\Exception $e) {
                            Log::error('Delete old event error: ' . $e->getMessage());
                        }
                    }

                    // Untuk Buah & Sayur: reminder di hari kadaluarsa
                    if ($food->jenis === 'Buah' || $food->jenis === 'Sayur') {
                        // Pastikan reminder tidak melebihi tanggal kadaluarsa
                        $reminderDate = $nextReminder->copy();
                        if ($reminderDate->greaterThan($expiryDate)) {
                            $reminderDate = $expiryDate;
                        }
                        $eventId = $calendarService->createEventOnExpiryDate($food, $reminderDate);
                    } else {
                        $eventId = $calendarService->createEventWithCustomDate($food, $nextReminder);
                    }

                    $food->google_event_id = $eventId;
                    $food->save();
                } catch (\Exception $e) {
                    Log::error('Google Calendar Error in remind: ' . $e->getMessage());
                }
            }

            return response()->json([
                'message' => '🔔 Pengingat berhasil ditambahkan!',
                'data' => $food,
                'next_reminder' => $nextReminder ? $nextReminder->format('d F Y') : null,
                'google_event_id' => $eventId
            ]);
        } catch (\Exception $e) {
            Log::error('Remind method error: ' . $e->getMessage());
            return response()->json(['error' => 'Gagal memproses pengingat: ' . $e->getMessage()], 500);
        }
    }

    // Consume - hapus event calendar jika makanan sudah dikonsumsi
    public function consume($id)
    {
        $food = Food::where('user_id', Auth::id())->findOrFail($id);
        $food->update(['status_penggunaan' => 'habis']);

        // Hapus event dari Google Calendar
        if ($food->google_event_id && Auth::user()->google_token) {
            try {
                $calendarService = new GoogleCalendarService(Auth::user());
                $calendarService->deleteEvent($food->google_event_id);
            } catch (\Exception $e) {
                Log::error('Google Calendar Error: ' . $e->getMessage());
            }
        }

        return response()->json(['message' => 'Sudah dikonsumsi']);
    }

    // Discard - hapus event calendar jika makanan dibuang
    public function discard($id)
    {
        $food = Food::where('user_id', Auth::id())->findOrFail($id);
        $food->update(['status_penggunaan' => 'dibuang']);

        // Hapus event dari Google Calendar
        if ($food->google_event_id && Auth::user()->google_token) {
            try {
                $calendarService = new GoogleCalendarService(Auth::user());
                $calendarService->deleteEvent($food->google_event_id);
            } catch (\Exception $e) {
                Log::error('Google Calendar Error: ' . $e->getMessage());
            }
        }

        return response()->json(['message' => 'Dibuang']);
    }

    // HALAMAN Detail
    public function show($id)
    {
        $food = Food::where('id', $id)->where('user_id', Auth::id())->firstOrFail();

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

    // DASHBOARD - StatsCard
    public function dashboard(Request $request)
    {
        $userId = Auth::id();

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

    // DASHBOARD - Table (Makanan Mendekati Kadaluarsa)
    public function expiringSoon()
    {
        $foods = Food::where('user_id', Auth::id())
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

    // DASHBOARD - Chart
    public function wasteChart()
    {
        $userId = Auth::id();

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
