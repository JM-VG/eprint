import './bootstrap';

import Alpine from 'alpinejs';
import { startAvailabilityBanner } from './availability';
import { dock } from './dock';
import { morphingPopover } from './morphing-popover';
import { spotlight } from './spotlight';
import { areaChart } from './area-chart';
import { textRoll } from './text-roll';
import { toasts } from './toasts';
import { startPriceEstimate } from './estimate';
import { startHeartbeat } from './heartbeat';
import { startNotificationBell } from './notification-bell';
import { startQueueBadge } from './queue-badge';
import { startWalkinForm } from './walkin';

window.Alpine = Alpine;

Alpine.data('dock', dock);
Alpine.data('morphingPopover', morphingPopover);
Alpine.data('spotlight', spotlight);
Alpine.data('areaChart', areaChart);
Alpine.data('textRoll', textRoll);
Alpine.data('toasts', toasts);

Alpine.start();

const coinCanvas = document.querySelector('[data-logo-coin]');
if (coinCanvas) {
    import('./logo-coin').then(({ mountLogoCoin }) => mountLogoCoin(coinCanvas));
}

startHeartbeat();
startAvailabilityBanner();
startPriceEstimate();
startQueueBadge();
startNotificationBell();
startWalkinForm();
