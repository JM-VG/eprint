import { animate } from 'motion';

// Port of motion-primitives <MorphingPopover>: the trigger's box morphs into the panel and back.
export function morphingPopover({ openOnLoad = false } = {}) {
    const spring = { type: 'spring', bounce: 0.05, duration: 0.3 };
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    return {
        isOpen: false,
        animating: false,

        init() {
            if (openOnLoad) {
                this.open();
            }
        },

        async open() {
            if (this.isOpen || this.animating) {
                return;
            }
            const { trigger, panel, content } = this.$refs;
            const from = trigger.getBoundingClientRect();

            this.isOpen = true;
            await this.$nextTick();
            trigger.style.visibility = 'hidden';
            this.$refs.autofocus?.focus({ preventScroll: true });

            if (reduced) {
                return;
            }
            this.animating = true;
            const to = { width: panel.offsetWidth, height: panel.offsetHeight };
            panel.style.width = `${from.width}px`;
            panel.style.height = `${from.height}px`;
            content.style.opacity = '0';
            animate(content, { opacity: [0, 1] }, { duration: 0.2, delay: 0.08 });
            await animate(panel, { width: [from.width, to.width], height: [from.height, to.height] }, spring);
            panel.style.width = panel.style.height = '';
            this.animating = false;
        },

        async close() {
            if (!this.isOpen || this.animating) {
                return;
            }
            const { trigger, panel, content } = this.$refs;

            if (!reduced) {
                this.animating = true;
                const to = trigger.getBoundingClientRect();
                animate(content, { opacity: 0 }, { duration: 0.1 });
                await animate(panel, { width: to.width, height: to.height }, spring);
                this.animating = false;
            }

            this.isOpen = false;
            panel.style.width = panel.style.height = '';
            content.style.opacity = '';
            trigger.style.visibility = '';
            trigger.focus({ preventScroll: true });
        },
    };
}
