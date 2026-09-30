import { animate } from 'motion';

// Port of motion-primitives <Dock>: items grow toward `magnification` as the pointer nears them.
export function dock({ base = 40, magnification = 64, distance = 140 } = {}) {
    const spring = { type: 'spring', mass: 0.1, stiffness: 150, damping: 12 };
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    return {
        items: [],

        init() {
            this.items = [...this.$el.querySelectorAll('[data-dock-item]')];
        },

        move(event) {
            if (reduced) {
                return;
            }
            for (const item of this.items) {
                const rect = item.getBoundingClientRect();
                const offset = Math.abs(event.clientX - (rect.left + rect.width / 2));
                const t = Math.max(0, 1 - offset / distance);
                this.resize(item, base + (magnification - base) * t);
            }
        },

        leave() {
            this.items.forEach((item) => this.resize(item, base));
        },

        resize(item, size) {
            animate(item, { width: size, height: size }, spring);
        },
    };
}
