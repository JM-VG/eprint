import { animate } from 'motion';

// Port of motion-primitives <Spotlight>: a soft glow that follows the pointer across its parent.
export function spotlight({ size = 200 } = {}) {
    return {
        visible: false,

        init() {
            const parent = this.$el.parentElement;
            const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const spring = reduced ? { duration: 0 } : { type: 'spring', bounce: 0, duration: 0.5 };

            parent.addEventListener('mouseenter', () => (this.visible = true));
            parent.addEventListener('mouseleave', () => (this.visible = false));
            parent.addEventListener('mousemove', (event) => {
                this.visible = true;
                const { left, top } = parent.getBoundingClientRect();
                animate(this.$el, { x: event.clientX - left - size / 2, y: event.clientY - top - size / 2 }, spring);
            });
        },
    };
}
