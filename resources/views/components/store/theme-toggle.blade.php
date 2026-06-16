<button type="button" x-data="{
    theme: localStorage.getItem('theme') || 'light',

    init() {
        this.theme = localStorage.getItem('theme') || 'light';
        applyStoreTheme();
    },

    toggleTheme() {
        this.theme = this.theme === 'light' ? 'dark' : 'light';
        localStorage.setItem('theme', this.theme);
        applyStoreTheme();
    }
}" x-on:click="toggleTheme" class="btn btn-circle btn-ghost btn-sm"
    aria-label="Toggle dark mode">
    <template x-if="theme === 'light'">
        <x-heroicon-o-moon class="h-5 w-5" />
    </template>

    <template x-if="theme === 'dark'">
        <x-heroicon-o-sun class="h-5 w-5" />
    </template>
</button>
