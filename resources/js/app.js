import './bootstrap';

import Alpine from 'alpinejs';
import { startAvailabilityBanner } from './availability';
import { startPriceEstimate } from './estimate';
import { startHeartbeat } from './heartbeat';

window.Alpine = Alpine;

Alpine.start();

startHeartbeat();
startAvailabilityBanner();
startPriceEstimate();
