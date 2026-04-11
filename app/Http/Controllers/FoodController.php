<?php

namespace App\Http\Controllers;

use App\Models\Food;
use Illuminate\Http\Request;
use Carbon\Carbon;

class FoodController extends Controller
{
    // GET ALL
    public function index()
    {
        return Food::latest()
            ->where('status_penggunaan', 'tersedia')
            ->get()
            ->map(function ($food) {
                return [
                    'id' => $food->id,
                    'nama' => $food->nama,
                    'kategori' => $food->jenis,
                    'tanggal_beli' => $food->tanggal_beli->format('d F Y'),
                    'expired_info' => $this->getExpiredLabel($food),
                    'jumlah' => $food->jumlah,
                    'satuan' => 'Pcs',
                    'status' => $this->getStatus($food),
                ];
            });
    }

    // STORE
    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'nama' => 'required|string',
            'jenis' => 'required',
            'tanggal_beli' => 'required|date',
            'tanggal_kadaluarsa' => 'nullable|date',
            'jumlah' => 'required|integer|min:1',
        ]);

        if ($this->tanpaExpired($data['jenis'])) {
            $data['tanggal_kadaluarsa'] = null;
        }

        $food = Food::create($data);

        // Google Calendar
        // if (session('google_token')) {
        //     $this->createCalendarEvent($food);
        // }

        return $food;
    }

    // MARK AS CONSUMED
    public function consume($id)
    {
        $food = Food::findOrFail($id);
        $food->status_penggunaan = 'habis';
        $food->save();

        return response()->json(['message' => 'Food consumed']);
    }

    // MARK AS DISCARDED
    public function discard($id)
    {
        $food = Food::findOrFail($id);
        $food->status_penggunaan = 'dibuang';
        $food->save();

        return response()->json(['message' => 'Food discarded']);
    }

    // DASHBOARD
    public function dashboard()
    {
        $total = Food::count();
        $tersedia = Food::where('status_penggunaan', 'tersedia')->count();
        $habis = Food::where('status_penggunaan', 'habis')->count();
        $dibuang = Food::where('status_penggunaan', 'dibuang')->count();

        return response()->json([
            'total' => $total,
            'tersedia' => $tersedia,
            'habis' => $habis,
            'dibuang' => $dibuang
        ]);
    }

    public function expiringSoon()
    {
        $foods = Food::where('status_penggunaan', 'tersedia')
            ->whereNotNull('tanggal_kadaluarsa')
            ->get()
            ->filter(function ($food) {
                $days = Carbon::now()->diffInDays($food->tanggal_kadaluarsa, false);
                return $days <= 3 && $days >= 0;
            })
            ->values();

        return $foods->map(function ($food) {
            return [
                'id' => $food->id,
                'nama' => $food->nama,
                'tanggal_kadaluarsa' => $food->tanggal_kadaluarsa->format('d F Y'),
                'status' => 'warning'
            ];
        });
    }

    // ======================
    // HELPER
    // ======================

    private function tanpaExpired($jenis)
    {
        return in_array($jenis, [
            'Buah',
            'Sayur',
            'Masakan jadi',
            'Jus'
        ]);
    }

    private function getExpiredLabel($food)
    {
        if (!$food->tanggal_kadaluarsa) {
            return 'Baik digunakan segera';
        }

        return 'Kadaluarsa: ' . $food->tanggal_kadaluarsa->format('d F Y');
    }

    private function getStatus($food)
    {
        if ($food->status_penggunaan === 'habis') return 'done';
        if ($food->status_penggunaan === 'dibuang') return 'expired';

        if (!$food->tanggal_kadaluarsa) return 'normal';

        $days = Carbon::now()->diffInDays($food->tanggal_kadaluarsa, false);

        if ($days < 0) return 'expired';
        if ($days <= 3) return 'warning';

        return 'safe';
    }

    // ======================
    // GOOGLE CALENDAR
    // ======================

    // private function createCalendarEvent($food)
    // {
    //     if (!$food->tanggal_kadaluarsa) return;

    //     $client = new \Google\Client();
    //     $client->setClientId(config('services.google.client_id'));
    //     $client->setClientSecret(config('services.google.client_secret'));
    //     $client->setAccessToken(session('google_token'));

    //     $service = new \Google\Service\Calendar($client);

    //     $event = new \Google\Service\Calendar\Event([
    //         'summary' => 'Food Expiring: ' . $food->nama,
    //         'start' => ['date' => $food->tanggal_kadaluarsa->toDateString()],
    //         'end' => ['date' => $food->tanggal_kadaluarsa->toDateString()],
    //     ]);

    //     $service->events->insert('primary', $event);
    // }
}
