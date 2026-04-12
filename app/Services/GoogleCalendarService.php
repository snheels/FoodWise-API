<?php

namespace App\Services;

use Google\Client;
use Google\Service\Calendar;
use App\Models\User;
use App\Models\Food;
use Carbon\Carbon;

class GoogleCalendarService
{
    protected $client;
    protected $calendar;

    public function __construct(User $user)
    {
        $this->client = new Client();
        $this->client->setClientId(config('services.google.client_id'));
        $this->client->setClientSecret(config('services.google.client_secret'));
        $this->client->setAccessToken($user->google_token);

        if ($this->client->isAccessTokenExpired()) {
            $this->client->fetchAccessTokenWithRefreshToken($user->google_refresh_token);
            $user->google_token = $this->client->getAccessToken()['access_token'];
            $user->save();
        }

        $this->calendar = new Calendar($this->client);
    }

    // Buat URL untuk update status (frontend)
    private function getFoodUrl($foodId)
    {
        return "http://localhost:5173/detail/{$foodId}";
    }

    public function createEvent(Food $food)
    {
        if (!$food->tanggal_kadaluarsa) {
            return null;
        }

        $tanggalKadaluarsa = Carbon::parse($food->tanggal_kadaluarsa);
        $hariIni = Carbon::now()->startOfDay();

        $selisihHari = $hariIni->diffInDays($tanggalKadaluarsa, false);

        if ($selisihHari <= 0) {
            $tanggalPengingat = $hariIni;
        } elseif ($selisihHari <= 3) {
            $tanggalPengingat = $hariIni;
        } else {
            $tanggalPengingat = $tanggalKadaluarsa->copy()->subDays(3);
        }

        if ($tanggalPengingat < $hariIni) {
            $tanggalPengingat = $hariIni;
        }

        $foodUrl = $this->getFoodUrl($food->id);

        $description = "Makanan: {$food->nama}\n";
        $description .= "Kategori: {$food->jenis}\n";
        $description .= "Jumlah: {$food->jumlah} {$food->satuan}\n";
        $description .= "Tanggal Beli: " . Carbon::parse($food->tanggal_beli)->format('d F Y') . "\n";
        $description .= "⚠️ Kadaluarsa: " . $tanggalKadaluarsa->format('d F Y') . "\n\n";

        if ($selisihHari <= 3 && $selisihHari > 0) {
            $description .= "🚨 PERHATIAN! Makanan ini akan kadaluarsa dalam {$selisihHari} hari!\n\n";
        } elseif ($selisihHari <= 0) {
            $description .= "🚨 PERHATIAN! Makanan ini SUDAH KADALUARSA!\n\n";
        }

        $description .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $description .= "Apa yang harus dilakukan?\n\n";
        $description .= "Sudah dikonsumsi → Klik tombol 'Sudah dikonsumsi' di aplikasi\n";
        $description .= "Dibuang → Klik tombol 'Dibuang' di aplikasi\n";
        $description .= "Ingatkan lagi → Klik tombol 'Ingatkan lagi' di aplikasi\n\n";
        $description .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $description .= "Update status makanan di sini:\n";
        $description .= "{$foodUrl}\n\n";
        $description .= "💡 Dengan mengupdate status, event ini akan otomatis dihapus dari kalender Anda.";

        // PERBAIKAN: Buat EventDateTime object
        $startDateTime = new \Google\Service\Calendar\EventDateTime();
        $startDateTime->setDateTime($tanggalPengingat->format('c'));
        $startDateTime->setTimeZone('Asia/Jakarta');

        $endDateTime = new \Google\Service\Calendar\EventDateTime();
        $endDateTime->setDateTime($tanggalPengingat->format('c'));
        $endDateTime->setTimeZone('Asia/Jakarta');

        $event = new \Google\Service\Calendar\Event([
            'summary' => '⚠️ Makanan Akan Kadaluarsa: ' . $food->nama,
            'description' => $description,
            'start' => $startDateTime,
            'end' => $endDateTime,
            'reminders' => [
                'useDefault' => false,
                'overrides' => [
                    ['method' => 'email', 'minutes' => 24 * 60],
                    ['method' => 'popup', 'minutes' => 60],
                ],
            ],
        ]);

        $calendarId = 'primary';
        $event = $this->calendar->events->insert($calendarId, $event);

        return $event->getId();
    }

    public function updateEvent(Food $food)
    {
        if (!$food->google_event_id || !$food->tanggal_kadaluarsa) {
            \Log::info('No event_id or expiry, calling createEvent');
            return $this->createEvent($food);
        }

        try {
            // Coba cek event masih ada atau tidak
            try {
                $event = $this->calendar->events->get('primary', $food->google_event_id);
            } catch (\Google\Service\Exception $e) {
                if ($e->getCode() == 404) {
                    \Log::info('Event not found, creating new one');
                    return $this->createEvent($food);
                }
                throw $e;
            }

            $tanggalKadaluarsa = Carbon::parse($food->tanggal_kadaluarsa);
            $hariIni = Carbon::now()->startOfDay();

            $selisihHari = $hariIni->diffInDays($tanggalKadaluarsa, false);

            if ($selisihHari <= 0) {
                $tanggalPengingat = $hariIni;
            } elseif ($selisihHari <= 3) {
                $tanggalPengingat = $hariIni;
            } else {
                $tanggalPengingat = $tanggalKadaluarsa->copy()->subDays(3);
            }

            if ($tanggalPengingat < $hariIni) {
                $tanggalPengingat = $hariIni;
            }

            $foodUrl = $this->getFoodUrl($food->id);

            $description = "Makanan: {$food->nama}\n";
            $description .= "Kategori: {$food->jenis}\n";
            $description .= "Jumlah: {$food->jumlah} {$food->satuan}\n";
            $description .= "Tanggal Beli: " . Carbon::parse($food->tanggal_beli)->format('d F Y') . "\n";
            $description .= "⚠️ Kadaluarsa: " . $tanggalKadaluarsa->format('d F Y') . "\n\n";

            if ($selisihHari <= 3 && $selisihHari > 0) {
                $description .= "🚨 PERHATIAN! Makanan ini akan kadaluarsa dalam {$selisihHari} hari!\n\n";
            } elseif ($selisihHari <= 0) {
                $description .= "🚨 PERHATIAN! Makanan ini SUDAH KADALUARSA!\n\n";
            }

            $description .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            $description .= "Apa yang harus dilakukan?\n\n";
            $description .= "Sudah dikonsumsi → Klik tombol 'Sudah dikonsumsi' di aplikasi\n";
            $description .= "Dibuang → Klik tombol 'Dibuang' di aplikasi\n";
            $description .= "Ingatkan lagi → Klik tombol 'Ingatkan lagi' di aplikasi\n\n";
            $description .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            $description .= "Update status makanan di sini:\n";
            $description .= "{$foodUrl}\n\n";
            $description .= "💡 Dengan mengupdate status, event ini akan otomatis dihapus dari kalender Anda.";

            $event->setSummary('⚠️ Makanan Akan Kadaluarsa: ' . $food->nama);
            $event->setDescription($description);

            // Gunakan setStartDateTime dan setEndDateTime (method yang lebih sederhana)
            $event->setStartDateTime($tanggalPengingat->format('c'));
            $event->setEndDateTime($tanggalPengingat->format('c'));
            $event->setStartTimeZone('Asia/Jakarta');
            $event->setEndTimeZone('Asia/Jakarta');

            $this->calendar->events->update('primary', $food->google_event_id, $event);

            \Log::info('Event updated successfully for food: ' . $food->id);

            return $food->google_event_id;
        } catch (\Exception $e) {
            \Log::error('UpdateEvent error: ' . $e->getMessage());
            return $this->createEvent($food);
        }
    }
    /**
     * Buat event dengan tanggal custom (untuk remind besok)
     */
    public function createEventWithCustomDate(Food $food, Carbon $tanggalPengingat)
    {
        if (!$food->tanggal_kadaluarsa) {
            return null;
        }

        $tanggalKadaluarsa = Carbon::parse($food->tanggal_kadaluarsa);
        $foodUrl = $this->getFoodUrl($food->id);
        $hariIni = Carbon::now()->startOfDay();
        $selisihHari = $hariIni->diffInDays($tanggalKadaluarsa, false);

        $description = "Makanan: {$food->nama}\n";
        $description .= "Kategori: {$food->jenis}\n";
        $description .= "Jumlah: {$food->jumlah} {$food->satuan}\n";
        $description .= "Tanggal Beli: " . Carbon::parse($food->tanggal_beli)->format('d F Y') . "\n";
        $description .= "⚠️ Kadaluarsa: " . $tanggalKadaluarsa->format('d F Y') . "\n\n";

        if ($selisihHari <= 3 && $selisihHari > 0) {
            $description .= "🚨 PERHATIAN! Makanan ini akan kadaluarsa dalam {$selisihHari} hari!\n\n";
        } elseif ($selisihHari <= 0) {
            $description .= "🚨 PERHATIAN! Makanan ini SUDAH KADALUARSA!\n\n";
        }

        $description .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $description .= "Pengingat dijadwalkan untuk: " . $tanggalPengingat->format('d F Y') . "\n\n";
        $description .= "Apa yang harus dilakukan?\n\n";
        $description .= "Sudah dikonsumsi → Klik tombol 'Sudah dikonsumsi' di aplikasi\n";
        $description .= "Dibuang → Klik tombol 'Dibuang' di aplikasi\n";
        $description .= "Ingatkan lagi → Klik tombol 'Ingatkan lagi' di aplikasi\n\n";
        $description .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $description .= "Update status makanan di sini:\n";
        $description .= "{$foodUrl}\n\n";
        $description .= "💡 Dengan mengupdate status, event ini akan otomatis dihapus dari kalender Anda.";

        $startDateTime = new \Google\Service\Calendar\EventDateTime();
        $startDateTime->setDateTime($tanggalPengingat->format('c'));
        $startDateTime->setTimeZone('Asia/Jakarta');

        $endDateTime = new \Google\Service\Calendar\EventDateTime();
        $endDateTime->setDateTime($tanggalPengingat->format('c'));
        $endDateTime->setTimeZone('Asia/Jakarta');

        $event = new \Google\Service\Calendar\Event([
            'summary' => '⏰ Ingatkan: ' . $food->nama . ' (kadaluarsa ' . $tanggalKadaluarsa->format('d F Y') . ')',
            'description' => $description,
            'start' => $startDateTime,
            'end' => $endDateTime,
            'reminders' => [
                'useDefault' => false,
                'overrides' => [
                    ['method' => 'email', 'minutes' => 24 * 60],
                    ['method' => 'popup', 'minutes' => 60],
                    ['method' => 'popup', 'minutes' => 120],
                ],
            ],
        ]);

        $calendarId = 'primary';
        $event = $this->calendar->events->insert($calendarId, $event);

        return $event->getId();
    }

    /**
     * Buat event untuk reminder di HARI KADALUARSA (untuk buah & sayur)
     */
    public function createEventOnExpiryDate(Food $food, Carbon $tanggalKadaluarsa)
    {
        if (!$food->tanggal_kadaluarsa) {
            return null;
        }

        $foodUrl = $this->getFoodUrl($food->id);
        $hariIni = Carbon::now()->startOfDay();
        $selisihHari = $hariIni->diffInDays($tanggalKadaluarsa, false);

        $description = "Makanan: {$food->nama}\n";
        $description .= "Kategori: {$food->jenis}\n";
        $description .= "Jumlah: {$food->jumlah} {$food->satuan}\n";
        $description .= "Tanggal Beli: " . Carbon::parse($food->tanggal_beli)->format('d F Y') . "\n";
        $description .= "⚠️ Kadaluarsa: " . $tanggalKadaluarsa->format('d F Y') . "\n\n";

        if ($selisihHari <= 3 && $selisihHari > 0) {
            $description .= "🚨 PERHATIAN! Makanan ini akan kadaluarsa dalam {$selisihHari} hari!\n\n";
        } elseif ($selisihHari <= 0) {
            $description .= "🚨 PERHATIAN! Makanan ini SUDAH KADALUARSA!\n\n";
        }

        $description .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $description .= "Buah/Sayur ini akan kadaluarsa pada: " . $tanggalKadaluarsa->format('d F Y') . "\n\n";
        $description .= "Apa yang harus dilakukan?\n\n";
        $description .= "Sudah dikonsumsi → Klik tombol 'Sudah dikonsumsi' di aplikasi\n";
        $description .= "Dibuang → Klik tombol 'Dibuang' di aplikasi\n";
        $description .= "Ingatkan lagi → Klik tombol 'Ingatkan lagi' di aplikasi\n\n";
        $description .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $description .= "Update status makanan di sini:\n";
        $description .= "{$foodUrl}\n\n";
        $description .= "💡 Dengan mengupdate status, event ini akan otomatis dihapus dari kalender Anda.";

        $startDateTime = new \Google\Service\Calendar\EventDateTime();
        $startDateTime->setDateTime($tanggalKadaluarsa->format('c'));
        $startDateTime->setTimeZone('Asia/Jakarta');

        $endDateTime = new \Google\Service\Calendar\EventDateTime();
        $endDateTime->setDateTime($tanggalKadaluarsa->format('c'));
        $endDateTime->setTimeZone('Asia/Jakarta');

        $event = new \Google\Service\Calendar\Event([
            'summary' => '⚠️ [BUAH/SAYUR] Kadaluarsa: ' . $food->nama,
            'description' => $description,
            'start' => $startDateTime,
            'end' => $endDateTime,
            'reminders' => [
                'useDefault' => false,
                'overrides' => [
                    ['method' => 'email', 'minutes' => 24 * 60],
                    ['method' => 'popup', 'minutes' => 60],
                ],
            ],
        ]);

        $calendarId = 'primary';
        $event = $this->calendar->events->insert($calendarId, $event);

        return $event->getId();
    }

    public function resetEvent(Food $food)
    {
        \Log::info('ResetEvent called for food: ' . $food->id);

        if (!$food->tanggal_kadaluarsa) {
            return null;
        }

        // Hapus event lama jika ada
        if ($food->google_event_id) {
            try {
                $this->calendar->events->delete('primary', $food->google_event_id);
                \Log::info('Deleted old event: ' . $food->google_event_id);
            } catch (\Exception $e) {
                \Log::error('Delete event error: ' . $e->getMessage());
            }
        }

        // Buat event baru
        $newEventId = $this->createEvent($food);

        if ($newEventId) {
            $food->google_event_id = $newEventId;
            $food->save();
            \Log::info('Created new event: ' . $newEventId);
        }

        return $newEventId;
    }

    public function deleteEvent($eventId)
    {
        if (!$eventId) return;

        try {
            $this->calendar->events->delete('primary', $eventId);
        } catch (\Exception $e) {
            \Log::error('Delete event error: ' . $e->getMessage());
        }
    }
}
