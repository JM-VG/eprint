import './bootstrap';

import Alpine from 'alpinejs';
import { startAvailabilityBanner } from './availability';
import { startPriceEstimate } from './estimate';
import { startHeartbeat } from './heartbeat';
import { startNotificationBell } from './notification-bell';
import { startQueueBadge } from './queue-badge';

window.Alpine = Alpine;

Alpine.start();

startHeartbeat();
startAvailabilityBanner();
startPriceEstimate();
startQueueBadge();
startNotificationBell();
