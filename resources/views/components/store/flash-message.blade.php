<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<div x-data="storeFlashMessage()"
    x-init="init()"
    x-on:notify.window="notify($event.detail)"
    x-on:flash-message.window="notify($event.detail)"
    x-show="show"
    x-cloak
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 -translate-y-2 scale-95"
    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
    x-transition:leave-end="opacity-0 -translate-y-2 scale-95"
    class="toast toast-top toast-center z-[9999]"
    style="display: none;">
    <div class="alert rounded-2xl shadow-lg"
        :class="{
            'alert-success': type === 'success',
            'alert-error': type === 'error',
            'alert-warning': type === 'warning',
            'alert-info': type === 'info'
        }">
        <template x-if="type === 'success'">
            <x-heroicon-o-check-circle class="h-5 w-5 shrink-0" />
        </template>
        <template x-if="type === 'error'">
            <x-heroicon-o-exclamation-triangle class="h-5 w-5 shrink-0" />
        </template>
        <template x-if="type === 'warning'">
            <x-heroicon-o-exclamation-circle class="h-5 w-5 shrink-0" />
        </template>
        <template x-if="type === 'info'">
            <x-heroicon-o-information-circle class="h-5 w-5 shrink-0" />
        </template>

        <div class="text-left">
            <p class="text-sm font-bold" x-show="title" x-text="title"></p>
            <p class="text-xs" x-text="message"></p>
        </div>
    </div>
</div>

<script>
    function storeFlashMessage() {
        return {
            show: {{ session('flash.message') ? 'true' : 'false' }},
            type: {{ \Illuminate\Support\Js::from(session('flash.type') ?? 'info') }},
            title: {{ \Illuminate\Support\Js::from(session('flash.title') ?? '') }},
            message: {{ \Illuminate\Support\Js::from(session('flash.message') ?? '') }},
            timeout: null,
            init() {
                if (this.show) {
                    this.timeout = setTimeout(() => this.show = false, 3500);
                }
            },
            notify(detail) {
                if (!detail) return;
                this.type = detail.type || 'success';
                this.title = detail.title || '';
                this.message = detail.message || '';
                this.show = true;
                clearTimeout(this.timeout);
                this.timeout = setTimeout(() => this.show = false, 3500);
            }
        };
    }
</script>
