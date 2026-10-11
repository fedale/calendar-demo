import './stimulus_bootstrap.js';
import '@fedale/calendar-bundle/styles/calendar.css';
import './styles/app.css';

// The "Add event" button of the rooms calendar dispatches this event; a real
// application would open its own booking form here.
document.addEventListener('demo:add-event', (event) => {
    alert(`Handle "add event" here (calendar: ${event.detail.calendar}, date: ${event.detail.date}).`);
});
