// Crosshair + tooltip for the server-rendered <x-area-chart>.
export function areaChart({ points = [], max = 1, prefix = '' } = {}) {
    return {
        points,
        index: null,

        track(event) {
            const rect = this.$refs.plot.getBoundingClientRect();
            const ratio = Math.min(1, Math.max(0, (event.clientX - rect.left) / rect.width));
            this.index = Math.round(ratio * (this.points.length - 1));
        },

        step(delta) {
            const start = this.index ?? this.points.length - 1;
            this.index = Math.min(this.points.length - 1, Math.max(0, start + delta));
        },

        get point() {
            return this.index === null ? null : this.points[this.index];
        },

        get left() {
            return this.points.length > 1 ? (this.index / (this.points.length - 1)) * 100 : 50;
        },

        get top() {
            return this.point ? 100 - (this.point.value / max) * 100 : 0;
        },

        get value() {
            return this.point
                ? prefix + this.point.value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                : '';
        },
    };
}
