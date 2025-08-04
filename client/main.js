import './dom-setup.js';
import './search-handler.js';
import { observer } from './infinite-scroll.js';
import * as ErrorLogger from './error-logger.js';

// Optionally expose logger globally if you want simple access from browser console or legacy code:
window.ErrorLogger = ErrorLogger;
