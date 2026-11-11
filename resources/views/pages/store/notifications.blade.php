<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Notifikasi')] class extends Component {
    public string $filter = 'all'; // 'all' or 'unread'

    #[Computed]
    public function customer()
    {
        return Auth::guard('customer')->user();
    }

    #[Computed]
    public function notifications()
    {
        if (! $this->customer) {
            return collect();
        }

        $query = $this->customer->notifications();

        if ($this->filter === 'unread') {
            $query->whereNull('read_at');
        }

        return $query->latest()->get();
    }

    #[Computed]
    public function unreadCount(): int
    {
        return $this->customer ? (int) $this->customer->unreadNotifications()->count() : 0;
    }

    public function markAsRead(string $notificationId, ?string $redirectUrl = null)
    {
        if (! $this->customer) {
            return;
        }

        $notification = $this->customer->notifications()->where('id', $notificationId)->first();

        if ($notification && ! $notification->read_at) {
            $notification->markAsRead();
            unset($this->notifications);
            unset($this->unreadCount);
        }

        if ($redirectUrl) {
            return $this->redirect($redirectUrl, navigate: true);
        }
    }

    public function markAllAsRead(): void
    {
        if (! $this->customer) {
            return;
        }

        $this->customer->unreadNotifications->markAsRead();

        unset($this->notifications);
        unset($this->unreadCount);

        $this->dispatch('notify',
            type: 'success',
            title: 'Berhasil',
            message: 'Semua notifikasi telah ditandai dibaca.'
        );
    }

    public function deleteNotification(string $notificationId): void
    {
        if (! $this->customer) {
            return;
        }

        $this->customer->notifications()->where('id', $notificationId)->delete();

        unset($this->notifications);
        unset($this->unreadCount);

        $this->dispatch('notify',
            type: 'info',
            title: 'Dihapus',
            message: 'Notifikasi berhasil dihapus.'
        );
    }
};
?>

<div class="min-h-screen bg-base-200 pb-24">
    <header class="sticky top-0 z-40 border-b border-base-300 bg-base-100/95 backdrop-blur">
        <div class="flex items-center justify-between p-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('store.home') }}" wire:navigate class="btn btn-circle btn-sm btn-ghost">
                    <x-heroicon-o-chevron-left class="h-5 w-5" />
                </a>

                <div>
                    <h1 class="text-lg font-black">Notifikasi</h1>
                    <p class="text-xs text-base-content/50">
                        @if ($this->unreadCount > 0)
                            <span class="font-bold text-primary">{{ $this->unreadCount }}</span> belum dibaca
                        @else
                            Semua sudah dibaca
                        @endif
                    </p>
                </div>
            </div>

            @if ($this->unreadCount > 0)
                <button type="button" wire:click="markAllAsRead"
                    class="btn btn-ghost btn-xs text-primary font-bold">
                    Tandai dibaca
                </button>
            @endif
        </div>

        {{-- Filter tabs --}}
        <div class="flex gap-2 px-4 pb-3">
            <button type="button" wire:click="$set('filter', 'all')"
                class="btn btn-xs rounded-full {{ $filter === 'all' ? 'btn-primary' : 'btn-ghost bg-base-200 text-base-content/70' }}">
                Semua
            </button>
            <button type="button" wire:click="$set('filter', 'unread')"
                class="btn btn-xs rounded-full gap-1.5 {{ $filter === 'unread' ? 'btn-primary' : 'btn-ghost bg-base-200 text-base-content/70' }}">
                Belum Dibaca
                @if ($this->unreadCount > 0)
                    <span class="badge badge-xs {{ $filter === 'unread' ? 'bg-white text-primary font-black' : 'badge-primary' }}">
                        {{ $this->unreadCount }}
                    </span>
                @endif
            </button>
        </div>
    </header>

    <main class="p-4">
        @if ($this->notifications->isEmpty())
            <div class="rounded-[2rem] bg-base-100 p-8 text-center shadow-sm">
                <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-[2rem] bg-primary/10 text-primary">
                    <x-heroicon-o-bell-slash class="h-10 w-10" />
                </div>

                <h2 class="mt-4 text-lg font-black">
                    {{ $filter === 'unread' ? 'Tidak ada notifikasi baru' : 'Belum ada notifikasi' }}
                </h2>
                <p class="mt-2 text-xs leading-relaxed text-base-content/60">
                    {{ $filter === 'unread' ? 'Semua notifikasi pesanan kamu sudah dibaca.' : 'Pemberitahuan status pesanan dan promo akan muncul di sini.' }}
                </p>

                <a href="{{ route('store.products') }}" wire:navigate class="btn btn-primary btn-sm mt-6 rounded-full px-6">
                    <x-heroicon-o-shopping-bag class="h-4 w-4" />
                    Mulai Belanja
                </a>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($this->notifications as $notification)
                    @php
                        $data = $notification->data;
                        $isUnread = is_null($notification->read_at);
                        $isPromo = ($data['type'] ?? '') === 'promo';
                        $color = $isPromo ? 'primary' : ($data['color'] ?? 'primary');
                        $url = $data['url'] ?? ($data['action_url'] ?? null);
                        $messageText = $data['message'] ?? ($data['body'] ?? '');
                    @endphp

                    <div wire:key="notif-{{ $notification->id }}"
                        wire:click="markAsRead('{{ $notification->id }}', '{{ $url }}')"
                        class="relative flex items-start gap-3.5 rounded-3xl p-4 transition cursor-pointer active:scale-[0.99] {{ $isUnread ? 'bg-base-100 border-2 border-primary/20 shadow-sm' : 'bg-base-100/70 border border-base-300 opacity-90' }}">
                        
                        {{-- Icon badge --}}
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl
                            @if ($color === 'emerald') bg-emerald-100 text-emerald-600
                            @elseif ($color === 'amber') bg-amber-100 text-amber-600
                            @elseif ($color === 'sky') bg-sky-100 text-sky-600
                            @elseif ($color === 'indigo') bg-indigo-100 text-indigo-600
                            @elseif ($color === 'rose') bg-rose-100 text-rose-600
                            @elseif ($color === 'purple') bg-purple-100 text-purple-600
                            @else bg-primary/10 text-primary
                            @endif">
                            @if ($isPromo)
                                <x-heroicon-o-ticket class="h-6 w-6" />
                            @elseif (($data['icon'] ?? '') === 'heroicon-o-check-circle')
                                <x-heroicon-o-check-circle class="h-6 w-6" />
                            @elseif (($data['icon'] ?? '') === 'heroicon-o-truck')
                                <x-heroicon-o-truck class="h-6 w-6" />
                            @elseif (($data['icon'] ?? '') === 'heroicon-o-arrow-path')
                                <x-heroicon-o-arrow-path class="h-6 w-6" />
                            @elseif (($data['icon'] ?? '') === 'heroicon-o-shopping-bag')
                                <x-heroicon-o-shopping-bag class="h-6 w-6" />
                            @elseif (($data['icon'] ?? '') === 'heroicon-o-sparkles')
                                <x-heroicon-o-sparkles class="h-6 w-6" />
                            @elseif (($data['icon'] ?? '') === 'heroicon-o-x-circle')
                                <x-heroicon-o-x-circle class="h-6 w-6" />
                            @elseif (($data['icon'] ?? '') === 'heroicon-o-arrow-uturn-left')
                                <x-heroicon-o-arrow-uturn-left class="h-6 w-6" />
                            @else
                                <x-heroicon-o-bell class="h-6 w-6" />
                            @endif
                        </div>

                        {{-- Text Details --}}
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="truncate text-sm font-bold text-base-content {{ $isUnread ? 'font-black' : '' }}">
                                    {{ $data['title'] ?? 'Notifikasi' }}
                                </h3>

                                <span class="shrink-0 text-[11px] text-base-content/40">
                                    {{ $notification->created_at->diffForHumans() }}
                                </span>
                            </div>

                            @if (!empty($data['order_number']))
                                <span class="inline-block mt-0.5 text-[10px] font-bold px-2 py-0.5 rounded-full bg-base-200 text-base-content/70">
                                    #{{ $data['order_number'] }}
                                </span>
                            @endif

                            @if (!empty($data['voucher_code']))
                                <span class="inline-flex items-center gap-1 mt-0.5 text-[10px] font-bold px-2 py-0.5 rounded-full bg-primary/10 text-primary font-mono">
                                    <x-heroicon-o-ticket class="h-3 w-3" />
                                    {{ $data['voucher_code'] }}
                                </span>
                            @endif

                            <p class="mt-1 text-xs leading-relaxed text-base-content/70 {{ $isUnread ? 'text-base-content font-medium' : '' }}">
                                {{ $messageText }}
                            </p>
                        </div>

                        {{-- Actions & unread indicator --}}
                        <div class="flex flex-col items-end justify-between self-stretch gap-2">
                            @if ($isUnread)
                                <span class="h-2.5 w-2.5 rounded-full bg-primary shrink-0 animate-pulse" title="Belum dibaca"></span>
                            @else
                                <span class="h-2.5 w-2.5"></span>
                            @endif

                            <button type="button" wire:click.stop="deleteNotification('{{ $notification->id }}')"
                                class="btn btn-ghost btn-circle btn-xs text-base-content/30 hover:text-rose-500"
                                title="Hapus notifikasi">
                                <x-heroicon-o-trash class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </main>
</div>
