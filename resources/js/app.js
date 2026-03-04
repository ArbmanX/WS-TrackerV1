/**
 * WS-TrackerV1 Frontend Application
 *
 * Entry point for all frontend JavaScript.
 */

// Chart.js
import Chart from 'chart.js/auto';
window.Chart = Chart;

// Alpine.js State Stores
// Must be imported before Alpine starts (loaded via Livewire)
import './alpine/stores.js';
