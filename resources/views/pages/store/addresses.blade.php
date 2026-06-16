<?php

use App\Models\Address;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Alamat Saya')] class extends Component {
    public ?string $message = null;

    #[Computed]
    public function customer()
    {
        return Auth::guard('customer')->user();
    }

    #[Computed]
    public function addresses()
    {
        return Address::query()->where('customer_id', $this->customer->id)->orderByDesc('is_default')->latest()->get();
    }

    public function setDefault(int $id): void
    {
        $address = Address::query()->where('customer_id', $this->customer->id)->findOrFail($id);

        Address::query()
            ->where('customer_id', $this->customer->id)
            ->update(['is_default' => false]);

        $address->update(['is_default' => true]);

        unset($this->addresses);

        $this->message = 'Alamat utama berhasil diubah.';
    }

    public function deleteAddress(int $id): void
    {
        $address = Address::query()->where('customer_id', $this->customer->id)->findOrFail($id);

        $wasDefault = (bool) $address->is_default;

        $address->delete();

        if ($wasDefault) {
            Address::query()
                ->where('customer_id', $this->customer->id)
                ->latest()
                ->first()
                ?->update(['is_default' => true]);
        }

        unset($this->addresses);

        $this->message = 'Alamat berhasil dihapus.';
    }
};
?>

<div class="min-h-screen bg-base-200 pb-24">
    @if ($message)
        <div x-data="{ show: true }" x-init="setTimeout(() => {
            show = false
            $wire.set('message', null)
        }, 2500)" x-show="show" x-transition
            class="toast toast-top toast-center z-[9999]">
            <div class="alert alert-success alert-soft rounded-2xl shadow-lg">
                <x-heroicon-o-check-circle class="h-5 w-5" />
                <span class="text-sm font-semibold">{{ $message }}</span>
            </div>
        </div>
    @endif

    <header class="sticky top-0 z-40 border-b border-base-300 bg-base-100/95 backdrop-blur">
        <div class="flex items-center gap-3 p-4">
            <a href="{{ route('store.account') }}" wire:navigate class="btn btn-circle btn-sm btn-ghost">
                <x-heroicon-o-chevron-left class="h-5 w-5" />
            </a>

            <div class="min-w-0 flex-1">
                <h1 class="text-lg font-black">Alamat Saya</h1>
                <p class="text-xs text-base-content/50">
                    {{ $this->addresses->count() }} alamat tersimpan
                </p>
            </div>

            <a href="{{ route('store.addresses.create') }}" wire:navigate class="btn btn-primary btn-sm rounded-full">
                <x-heroicon-o-plus class="h-4 w-4" />
                Tambah
            </a>
        </div>
    </header>

    <main class="p-4">
        @if ($this->addresses->isEmpty())
            <section class="rounded-[2rem] bg-base-100 p-6 text-center shadow-sm">
                <div
                    class="mx-auto flex h-20 w-20 items-center justify-center rounded-[2rem] bg-primary/10 text-primary">
                    <x-heroicon-o-map-pin class="h-12 w-12" />
                </div>

                <h2 class="mt-4 text-xl font-black">Belum ada alamat</h2>

                <p class="mt-2 text-sm text-base-content/60">
                    Tambahkan alamat pengiriman supaya checkout lebih cepat.
                </p>

                <a href="{{ route('store.addresses.create') }}" wire:navigate class="btn btn-primary mt-5 rounded-full">
                    Tambah Alamat
                </a>
            </section>
        @else
            <section class="space-y-3">
                @foreach ($this->addresses as $address)
                    <div class="rounded-[2rem] bg-base-100 p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="font-black">{{ $address->label }}</h2>

                                    @if ($address->is_default)
                                        <span class="badge badge-primary badge-sm">Utama</span>
                                    @endif
                                </div>

                                <p class="mt-2 text-sm font-bold">
                                    {{ $address->recipient_name }}
                                </p>

                                <p class="text-sm text-base-content/60">
                                    {{ $address->recipient_phone }}
                                </p>

                                <p class="mt-2 text-sm leading-relaxed text-base-content/70">
                                    {{ $address->full_address }},
                                    {{ $address->subdistrict }},
                                    {{ $address->district }},
                                    {{ $address->city }},
                                    {{ $address->province }},
                                    {{ $address->postal_code }}
                                </p>
                            </div>

                            <div class="dropdown dropdown-end">
                                <button tabindex="0" class="btn btn-ghost btn-sm btn-circle">
                                    <x-heroicon-o-ellipsis-vertical class="h-5 w-5" />
                                </button>

                                <ul tabindex="0"
                                    class="menu dropdown-content z-[1] w-44 rounded-box bg-base-100 p-2 shadow">
                                    <li>
                                        <a href="{{ route('store.addresses.edit', $address->id) }}" wire:navigate>
                                            Edit
                                        </a>
                                    </li>

                                    @if (!$address->is_default)
                                        <li>
                                            <button wire:click="setDefault({{ $address->id }})">
                                                Jadikan Utama
                                            </button>
                                        </li>
                                    @endif

                                    <li>
                                        <button wire:click="deleteAddress({{ $address->id }})" class="text-error">
                                            Hapus
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                @endforeach
            </section>
        @endif
    </main>
</div>
