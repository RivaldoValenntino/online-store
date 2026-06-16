{{-- @extends('errors::minimal')

@section('title', __('Not Found'))
@section('code', '404')
@section('message', __('Not Found')) --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Halaman Tidak Ditemukan - {{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-base-200 text-base-content">
    <main class="mx-auto flex min-h-screen max-w-md items-center justify-center bg-base-100 px-5">
        <section class="w-full rounded-[2rem] bg-base-100 p-6 text-center shadow-sm">
            <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-[2rem] bg-primary/10 text-primary">
                <x-heroicon-o-face-frown class="h-14 w-14" />
            </div>

            <div class="mt-6">
                {{-- <div class="badge badge-warning mb-3">
                    Error 404
                </div> --}}

                <h1 class="text-3xl font-black leading-tight">
                    Halaman tidak ditemukan
                </h1>

                <p class="mt-3 text-sm leading-relaxed text-base-content/60">
                    Maaf, halaman yang kamu cari tidak tersedia, sudah dipindahkan, atau URL-nya salah.
                </p>
            </div>

            <div class="mt-8 grid grid-cols-1 gap-3">
                <a href="{{ route('store.home') }}" class="btn btn-primary rounded-full">
                    <x-heroicon-o-home class="h-5 w-5" />
                    Kembali ke Beranda
                </a>

                <button onclick="history.back()" class="btn btn-outline rounded-full">
                    <x-heroicon-o-arrow-left class="h-5 w-5" />
                    Kembali
                </button>
            </div>

            {{-- <div class="mt-6 rounded-2xl bg-base-200 p-4 text-left">
                <div class="flex gap-3">
                    <x-heroicon-o-light-bulb class="h-6 w-6 shrink-0 text-warning" />

                    <div>
                        <p class="text-sm font-bold">Tips</p>
                        <p class="mt-1 text-xs leading-relaxed text-base-content/60">
                            Coba cek kembali alamat URL, atau gunakan pencarian produk dari halaman beranda.
                        </p>
                    </div>
                </div>
            </div> --}}
        </section>
    </main>
</body>

</html>