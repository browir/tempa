<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Hanya data kecil yang dibutuhkan banyak halaman; yang lebih berat dibungkus closure agar lazy.
     * Kolom baru yang dibaca di sini → semua halaman 500 bila migrasi produksi belum jalan.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user?->only(['id', 'name', 'email']),
            ],
        ];
    }
}
