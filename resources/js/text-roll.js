import { animate } from 'motion';

// Port of motion-primitives <TextRoll> with the custom transition/delay variant.
export function textRoll({ duration = 0.3, enterDelay = 0.05, exitDelay = 0.05, replayOnHover = true } = {}) {
    const ease = [0.175, 0.885, 0.32, 1.1];

    return {
        running: false,

        init() {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return;
            }
            this.roll();
            if (replayOnHover) {
                this.$el.addEventListener('mouseenter', () => this.roll());
            }
        },

        async roll() {
            if (this.running) {
                return;
            }
            this.running = true;
            const out = [...this.$el.querySelectorAll('[data-roll-out]')];
            const into = [...this.$el.querySelectorAll('[data-roll-in]')];

            await Promise.all([
                ...out.map((el, i) => animate(el, { y: ['0%', '110%'] }, { duration, ease, delay: i * enterDelay })),
                ...into.map((el, i) => animate(el, { y: ['-110%', '0%'] }, { duration, ease, delay: i * enterDelay + exitDelay })),
            ]);
            this.running = false;
        },
    };
}
