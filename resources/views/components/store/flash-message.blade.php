<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

@if (session('flash.message'))
<div x-data="{ show: true }" x-init="setTimeout(() => show = false, 3500)" x-show="show"
    x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 -translate-y-2 scale-95"
    x-transition:enter-end="opacity-100 translate-y-0 scale-100" x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
    x-transition:leave-end="opacity-0 -translate-y-2 scale-95" class="toast toast-top toast-center z-[9999]">
    <div class="alert rounded-2xl shadow-lg
                {{ session('flash.type') === 'success' ? 'alert-success' : '' }}
                {{ session('flash.type') === 'error' ? 'alert-error' : '' }}
                {{ session('flash.type') === 'warning' ? 'alert-warning' : '' }}
                {{ session('flash.type') === 'info' ? 'alert-info' : '' }}">
        @if (session('flash.type') === 'success')
        <x-heroicon-o-check-circle class="h-5 w-5" />
        @elseif (session('flash.type') === 'error')
        <x-heroicon-o-exclamation-triangle class="h-5 w-5" />
        @elseif (session('flash.type') === 'warning')
        <x-heroicon-o-exclamation-circle class="h-5 w-5" />
        @else
        <x-heroicon-o-information-circle class="h-5 w-5" />
        @endif

        <div>
            <p class="text-sm font-bold">
                {{ session('flash.title') }}
            </p>

            <p class="text-xs">
                {{ session('flash.message') }}
            </p>
        </div>
    </div>
</div>
@endif