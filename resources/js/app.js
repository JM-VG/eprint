import './bootstrap';

import Alpine from 'alpinejs';
import { startHeartbeat } from './heartbeat';

window.Alpine = Alpine;

Alpine.start();

startHeartbeat();
