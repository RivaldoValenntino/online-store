@extends('layouts.store')

@section('content')
<div class="max-w-lg mx-auto py-16 px-4 text-center">
    <div class="bg-base-100 p-8 rounded-2xl shadow-sm border border-base-200">
        <div class="w-16 h-16 bg-success/10 text-success rounded-full flex items-center justify-center mx-auto mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
        </div>
        <h1 class="text-2xl font-bold mb-2">Berhasil Berhenti Berlangganan</h1>
        <p class="text-base-content/70 text-sm mb-6">
            Email <strong>{{ $customer->email }}</strong> tidak akan menerima email promosi lagi dari Sami Raos. Anda tetap dapat berbelanja dan menerima notifikasi pesanan seperti biasa.
        </p>
        <a href="{{ route('store.home') }}" class="btn btn-primary rounded-xl px-6">
            Kembali ke Beranda
        </a>
    </div>
</div>
@endsection
