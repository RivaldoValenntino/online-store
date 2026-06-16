<?php

use App\Models\Address;
use App\Services\BiteshipService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
// use Throwable;

new #[Title('Form Alamat')] class extends Component {
    public ?int $id = null;

    public string $label = '';
    public string $recipient_name = '';
    public string $recipient_phone = '';

    public string $areaSearch = '';
    public bool $showAreaModal = false;

    public ?string $biteship_area_id = null;
    public ?string $biteship_area_label = null;
    public ?string $biteship_postal_code = null;
    public ?string $biteshipSearchError = null;

    public string $province = '';
    public string $city = '';
    public string $district = '';
    public string $subdistrict = '';

    public ?string $province_id = null;
    public ?string $city_id = null;
    public ?string $district_id = null;
    public ?string $subdistrict_id = null;

    public string $postal_code = '';
    public string $full_address = '';
    public bool $is_default = false;

    public function mount(?int $id = null): void
    {
        $this->id = $id;

        if (!$id) {
            $customer = Auth::guard('customer')->user();

            $this->label = 'Rumah';
            $this->recipient_name = $customer->name ?? '';
            $this->recipient_phone = $customer->phone ?? '';

            return;
        }

        $address = Address::query()
            ->where('customer_id', Auth::guard('customer')->id())
            ->findOrFail($id);

        $this->label = $address->label ?? '';
        $this->recipient_name = $address->recipient_name ?? '';
        $this->recipient_phone = $address->recipient_phone ?? '';

        $this->province = $address->province ?? '';
        $this->city = $address->city ?? '';
        $this->district = $address->district ?? '';
        $this->subdistrict = $address->subdistrict ?? '';

        $this->province_id = $address->province_id ? (string) $address->province_id : null;
        $this->city_id = $address->city_id ? (string) $address->city_id : null;
        $this->district_id = $address->district_id ? (string) $address->district_id : null;
        $this->subdistrict_id = $address->subdistrict_id ? (string) $address->subdistrict_id : null;

        $this->biteship_area_id = $address->biteship_area_id;
        $this->biteship_area_label = $address->biteship_area_label;
        $this->biteship_postal_code = $address->biteship_postal_code;

        $this->postal_code = $address->postal_code ?? '';
        $this->full_address = $address->full_address ?? '';
        $this->is_default = (bool) $address->is_default;

        $this->areaSearch = $this->biteship_area_label ?? '';
    }

    #[Computed]
    public function areaResults(): array
    {
        $this->biteshipSearchError = null;

        if (!$this->showAreaModal || mb_strlen(trim($this->areaSearch)) < 3) {
            return [];
        }

        try {
            return app(BiteshipService::class)->searchAreas($this->areaSearch);
        } catch (\Throwable $e) {
            report($e);

            $this->biteshipSearchError = 'Gagal mencari area Biteship. Coba beberapa saat lagi.';

            return [];
        }
    }

    protected function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:50'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_phone' => ['required', 'string', 'max:30'],

            'biteship_area_id' => ['required', 'string'],
            'biteship_area_label' => ['required', 'string', 'max:255'],
            'biteship_postal_code' => ['nullable', 'string', 'max:10'],

            'postal_code' => ['nullable', 'string', 'max:10'],
            'full_address' => ['required', 'string', 'max:1000'],
            'is_default' => ['boolean'],
        ];
    }

    protected function messages(): array
    {
        return [
            'label.required' => 'Label alamat wajib diisi.',
            'recipient_name.required' => 'Nama penerima wajib diisi.',
            'recipient_phone.required' => 'Nomor HP wajib diisi.',

            'biteship_area_id.required' => 'Area pengiriman wajib dipilih.',
            'biteship_area_label.required' => 'Area pengiriman wajib dipilih.',

            'full_address.required' => 'Alamat lengkap wajib diisi.',
        ];
    }

    public function updated(string $property): void
    {
        if ($property === 'areaSearch') {
            $this->biteship_area_id = null;
            $this->biteship_area_label = null;
            $this->biteship_postal_code = null;
            $this->postal_code = '';
            $this->province = '';
            $this->city = '';
            $this->district = '';
            $this->subdistrict = '';
        }

        if (array_key_exists($property, $this->rules())) {
            $this->validateOnly($property);
        }
    }

    public function openAreaModal(): void
    {
        $this->showAreaModal = true;
        $this->biteshipSearchError = null;
    }

    public function closeAreaModal(): void
    {
        $this->showAreaModal = false;
        $this->biteshipSearchError = null;
    }

    public function selectBiteshipArea(string $areaId): void
    {
        $area = collect($this->areaResults)->firstWhere('id', $areaId);

        if (!$area) {
            $this->biteshipSearchError = 'Area tidak valid. Silakan pilih ulang.';
            return;
        }

        $label = app(BiteshipService::class)->areaLabel($area);

        $this->biteship_area_id = $area['id'];
        $this->biteship_area_label = $label;
        $this->biteship_postal_code = $area['postal_code'] ? (string) $area['postal_code'] : null;

        $this->postal_code = $this->biteship_postal_code ?: $this->postal_code;

        $this->province = $area['administrative_division_level_1_name'] ?? '';
        $this->city = $area['administrative_division_level_2_name'] ?? '';
        $this->district = $area['administrative_division_level_3_name'] ?? '';
        $this->subdistrict = $area['name'] ?? '';

        $this->province_id = null;
        $this->city_id = null;
        $this->district_id = null;
        $this->subdistrict_id = null;

        $this->areaSearch = $label;

        $this->resetErrorBag(['biteship_area_id', 'biteship_area_label']);

        $this->closeAreaModal();
    }

    public function inputClass(string $field): string
    {
        $base = 'input input-bordered w-full rounded-2xl bg-base-100 transition focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20';

        if ($this->getErrorBag()->has($field)) {
            return $base . ' input-error border-error focus:border-error focus:ring-error/20';
        }

        return $base;
    }

    public function textareaClass(string $field): string
    {
        $base = 'textarea textarea-bordered w-full rounded-2xl bg-base-100 transition focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20';

        if ($this->getErrorBag()->has($field)) {
            return $base . ' textarea-error border-error focus:border-error focus:ring-error/20';
        }

        return $base;
    }

    public function areaButtonClass(string $field): string
    {
        $base = 'flex min-h-12 w-full items-center justify-between rounded-2xl border bg-base-100 px-4 py-3 text-left transition active:scale-[0.99] disabled:opacity-50';

        if ($this->getErrorBag()->has($field)) {
            return $base . ' border-error ring-2 ring-error/20';
        }

        return $base . ' border-base-300';
    }

    public function save(): void
    {
        $this->validate();

        if ($this->biteship_postal_code && blank($this->postal_code)) {
            $this->postal_code = $this->biteship_postal_code;
        }

        $customerId = Auth::guard('customer')->id();

        $isFirstAddress = Address::query()->where('customer_id', $customerId)->when($this->id, fn($query) => $query->where('id', '!=', $this->id))->doesntExist();

        if ($isFirstAddress) {
            $this->is_default = true;
        }

        if ($this->is_default) {
            Address::query()
                ->where('customer_id', $customerId)
                ->when($this->id, fn($query) => $query->where('id', '!=', $this->id))
                ->update(['is_default' => false]);
        }

        Address::updateOrCreate(
            [
                'id' => $this->id,
                'customer_id' => $customerId,
            ],
            [
                'label' => $this->label,
                'recipient_name' => $this->recipient_name,
                'recipient_phone' => $this->recipient_phone,

                'province' => $this->province,
                'city' => $this->city,
                'district' => $this->district,
                'subdistrict' => $this->subdistrict,
                'postal_code' => $this->postal_code,

                'full_address' => $this->full_address,

                'province_id' => $this->province_id,
                'city_id' => $this->city_id,
                'district_id' => $this->district_id,

                'biteship_area_id' => $this->biteship_area_id,
                'biteship_area_label' => $this->biteship_area_label,
                'biteship_postal_code' => $this->biteship_postal_code,

                'is_default' => $this->is_default,
            ],
        );

        session()->flash('flash.type', 'success');
        session()->flash('flash.title', 'Berhasil');
        session()->flash('flash.message', $this->id ? 'Alamat berhasil diperbarui.' : 'Alamat berhasil ditambahkan.');

        $this->redirectRoute('store.addresses', navigate: true);
    }
};
?>

<div class="min-h-screen bg-base-200 pb-24">
    {{-- Fullscreen Loading Save --}}
    <div wire:loading.flex wire:target="save"
        class="fixed inset-0 z-[9999] items-center justify-center bg-base-100/70 backdrop-blur-sm">
        <div class="flex flex-col items-center gap-3 rounded-3xl bg-base-100 p-6 shadow-xl">
            <span class="loading loading-spinner loading-lg text-primary"></span>

            <p class="text-sm font-semibold text-base-content/70">
                Menyimpan alamat...
            </p>
        </div>
    </div>
    <div wire:loading.flex wire:target="openAreaModal,selectBiteshipArea"
        class="fixed inset-0 z-[9999] items-center justify-center bg-base-100/70 backdrop-blur-sm">
        <div class="flex flex-col items-center gap-3 rounded-3xl bg-base-100 p-6 shadow-xl">
            <span class="loading loading-spinner loading-lg text-primary"></span>

            {{-- <p class="text-sm font-semibold text-base-content/70">
                Menyimpan alamat...
            </p> --}}
        </div>
    </div>

    <header class="sticky top-0 z-40 border-b border-base-300 bg-base-100/95 backdrop-blur">
        <div class="flex items-center gap-3 p-4">
            <a href="{{ route('store.addresses') }}" wire:navigate class="btn btn-circle btn-sm btn-ghost">
                <x-heroicon-o-chevron-left class="h-5 w-5" />
            </a>

            <div>
                <h1 class="text-lg font-black">
                    {{ $id ? 'Edit Alamat' : 'Tambah Alamat' }}
                </h1>

                <p class="text-xs text-base-content/50">
                    Lengkapi alamat pengiriman kamu
                </p>
            </div>
        </div>
    </header>

    <main class="p-4">
        <section class="rounded-[2rem] bg-base-100 p-4 shadow-sm">
            <form wire:submit="save" class="space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-medium">Label</label>

                    <input type="text" wire:model.live.debounce.500ms="label" placeholder="Rumah / Kantor"
                        class="{{ $this->inputClass('label') }}">

                    @error('label')
                        <p class="mt-1 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Nama Penerima</label>

                    <input type="text" wire:model.live.debounce.500ms="recipient_name" placeholder="Nama penerima"
                        class="{{ $this->inputClass('recipient_name') }}">

                    @error('recipient_name')
                        <p class="mt-1 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Nomor HP</label>

                    <input type="text" wire:model.live.debounce.500ms="recipient_phone" placeholder="08xxxxxxxxxx"
                        class="{{ $this->inputClass('recipient_phone') }}">

                    @error('recipient_phone')
                        <p class="mt-1 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Area Pengiriman</label>

                    <button type="button" wire:click="openAreaModal"
                        class="{{ $this->areaButtonClass('biteship_area_id') }}">
                        <span class="{{ $biteship_area_label ? 'text-base-content' : 'text-base-content/40' }}">
                            {{ $biteship_area_label ?: 'Cari kelurahan, kecamatan, kota, atau kode pos' }}
                        </span>

                        <x-heroicon-o-chevron-right class="h-5 w-5 shrink-0 text-base-content/40" />
                    </button>

                    <p class="mt-1 text-xs text-base-content/50" wire:loading wire:target='openAreaModal'>
                        Memuat modal area pengiriman...
                    </p>


                    @error('biteship_area_id')
                        <p class="mt-1 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>

                @if ($biteship_area_label)
                    <div class="rounded-2xl bg-info/10 p-3 text-sm text-info">
                        <div class="flex gap-2">
                            <x-heroicon-o-map-pin class="h-5 w-5 shrink-0" />

                            <div>
                                <p class="font-bold">Area dipilih</p>
                                <p class="text-xs">{{ $biteship_area_label }}</p>

                                @if ($biteship_area_id)
                                    <p class="mt-1 text-xs">Area ID: {{ $biteship_area_id }}</p>
                                @endif

                                @if ($biteship_postal_code)
                                    <p class="mt-1 text-xs">Kode pos: {{ $biteship_postal_code }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                <div>
                    <label class="mb-1 block text-sm font-medium">Kode Pos</label>

                    <input type="text" wire:model.live.debounce.500ms="postal_code" placeholder="Contoh: 17111"
                        class="{{ $this->inputClass('postal_code') }}">

                    {{-- <p class="mt-1 text-xs text-base-content/50">
                        Otomatis terisi dari area Biteship jika tersedia.
                    </p> --}}

                    @error('postal_code')
                        <p class="mt-1 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Alamat Lengkap</label>

                    <textarea rows="3" wire:model.live.debounce.500ms="full_address"
                        placeholder="Nama jalan, nomor rumah, RT/RW, patokan..." class="{{ $this->textareaClass('full_address') }}"></textarea>

                    @error('full_address')
                        <p class="mt-1 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>

                <label class="flex cursor-pointer items-center gap-3 rounded-2xl bg-base-200 p-3">
                    <input type="checkbox" wire:model="is_default" class="checkbox checkbox-primary checkbox-sm">

                    <div>
                        <p class="text-sm font-bold">Jadikan alamat utama</p>
                        <p class="text-xs text-base-content/50">
                            Dipakai otomatis saat checkout.
                        </p>
                    </div>
                </label>

                <button type="submit" wire:loading.attr="disabled" wire:target="save"
                    class="btn btn-primary w-full rounded-full">
                    <span wire:loading.remove wire:target="save">
                        Simpan Alamat
                    </span>

                    <span wire:loading wire:target="save" class="flex items-center gap-2">
                        <span class="loading loading-spinner loading-sm"></span>
                        Menyimpan...
                    </span>
                </button>
            </form>
        </section>
    </main>

    @if ($showAreaModal)
        <div class="modal modal-open">
            <div class="modal-box flex max-h-[80vh] flex-col rounded-[2rem] p-0">
                <div class="border-b border-base-300 p-4">
                    <div class="mb-3 flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-black">Pilih Area Pengiriman</h3>

                            <p class="text-xs text-base-content/50">
                                Cari area pengiriman.
                            </p>
                        </div>

                        <button type="button" wire:click="closeAreaModal" class="btn btn-ghost btn-sm btn-circle">
                            <x-heroicon-o-x-mark class="h-5 w-5" />
                        </button>
                    </div>

                    <div
                        class="flex h-11 items-center gap-2 rounded-full border border-base-300 bg-base-200 px-4 transition
                               focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20">
                        <x-heroicon-o-magnifying-glass class="h-5 w-5 shrink-0 text-base-content/40" />

                        <input type="text" wire:model.live.debounce.700ms="areaSearch"
                            class="grow border-0 bg-transparent text-sm outline-none placeholder:text-base-content/40 focus:border-0 focus:outline-none focus:ring-0"
                            placeholder="Contoh: Cibinong, Bogor, 16911" autofocus>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto p-2">
                    <div wire:loading wire:target="areaSearch" class="p-6 text-center w-full">
                        <span class="loading loading-spinner loading-md text-primary"></span>
                        <p class="mt-2 text-sm text-base-content/60">Mencari area </p>
                    </div>

                    <div wire:loading.remove wire:target="areaSearch">
                        @if ($biteshipSearchError)
                            <div class="rounded-2xl bg-error/10 p-4 text-sm text-error">
                                <div class="flex gap-2">
                                    <x-heroicon-o-exclamation-triangle class="h-5 w-5 shrink-0" />
                                    <p>{{ $biteshipSearchError }}</p>
                                </div>
                            </div>
                        @elseif (mb_strlen(trim($areaSearch)) < 3)
                            <div class="p-6 text-center">
                                <div
                                    class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-base-200">
                                    <x-heroicon-o-map-pin class="h-9 w-9 text-base-content/30" />
                                </div>

                                <p class="mt-3 font-bold">Cari area pengiriman</p>

                                <p class="mt-1 text-sm text-base-content/50">
                                    Ketik minimal 3 huruf. Contoh: Bekasi, Cibinong, Bandung.
                                </p>
                            </div>
                        @else
                            @forelse ($this->areaResults as $area)
                                @php
                                    $label = app(\App\Services\BiteshipService::class)->areaLabel($area);
                                @endphp

                                <button type="button" wire:click="selectBiteshipArea(@js($area['id']))"
                                    wire:loading.attr="disabled"
                                    wire:target="selectBiteshipArea(@js($area['id']))"
                                    class="flex w-full items-center justify-between rounded-2xl px-4 py-3 text-left hover:bg-base-200 disabled:opacity-60">
                                    <div class="min-w-0">
                                        <p class="font-medium">{{ $label }}</p>

                                        @if ($area['postal_code'])
                                            <p class="mt-1 text-xs text-base-content/50">
                                                Kode pos {{ $area['postal_code'] }}
                                            </p>
                                        @endif
                                    </div>
                                    {{-- 
                                    <span wire:loading wire:target="selectBiteshipArea($area['id'])"
                                        class="loading loading-spinner loading-xs text-primary"></span>

                                    <x-heroicon-o-chevron-right wire:loading.remove
                                        wire:target="selectBiteshipArea($area['id'])"
                                        class="h-5 w-5 shrink-0 text-base-content/30" /> --}}
                                </button>
                            @empty
                                <div class="p-6 text-center">
                                    <div
                                        class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-base-200">
                                        <x-heroicon-o-magnifying-glass class="h-9 w-9 text-base-content/30" />
                                    </div>

                                    <p class="mt-3 font-bold">Area tidak ditemukan</p>

                                    <p class="mt-1 text-sm text-base-content/50">
                                        Coba gunakan kata kunci lain atau pakai kode pos.
                                    </p>
                                </div>
                            @endforelse
                        @endif
                    </div>
                </div>
            </div>

            <div class="modal-backdrop" wire:click="closeAreaModal"></div>
        </div>
    @endif
</div>
