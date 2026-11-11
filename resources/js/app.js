document.addEventListener("alpine:init", () => {
    Alpine.data("statusScroller", () => ({
        canPrev: false,
        canNext: false,

        init() {
            this.$nextTick(() => {
                this.update();

                window.addEventListener("resize", () => this.update());

                Livewire.hook("morphed", () => {
                    this.$nextTick(() => this.update());
                });
            });
        },

        update() {
            const el = this.$refs.container;

            this.canPrev = el.scrollLeft > 5;

            this.canNext = el.scrollLeft + el.clientWidth < el.scrollWidth - 5;
        },

        next() {
            this.$refs.container.scrollBy({
                left: 220,
                behavior: "smooth",
            });
        },

        prev() {
            this.$refs.container.scrollBy({
                left: -220,
                behavior: "smooth",
            });
        },
    }));
});
