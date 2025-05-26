const events = [
    {
        title: "🏡 Open House",
        desc: "<strong>Date:</strong> Saturday, 10am-4pm<br><strong>Location:</strong> 10 Rue Sextius Michel",
        img: "../assets/images/event2.png",
        details: "#"
    },
    {
        title: "💼 Investment Seminar",
        desc: "<strong>Date:</strong> Sunday, 2pm<br><strong>Location:</strong> Main Office",
        img: "../assets/images/event3.png",
        details: "#"
    },
    {
        title: "🤝 Meet the Agents",
        desc: "<strong>Date:</strong> Friday, 6pm<br><strong>Location:</strong> Online (register now!)",
        img: "../assets/images/event1.png",
        details: "#"
    }
];

let currentEvent = 0;
let eventInterval;

function showEvent(idx) {
    const event = events[idx];
    document.getElementById('event-title').innerHTML = event.title;
    document.getElementById('event-desc').innerHTML = event.desc;
    document.getElementById('event-img').src = event.img;
    document.getElementById('event-details-btn').href = event.details;
}

function nextEvent() {
    currentEvent = (currentEvent + 1) % events.length;
    showEvent(currentEvent);
}

function prevEvent() {
    currentEvent = (currentEvent - 1 + events.length) % events.length;
    showEvent(currentEvent);
}

function startEventInterval() {
    eventInterval = setInterval(nextEvent, 5000);
}

function resetEventInterval() {
    clearInterval(eventInterval);
    startEventInterval();
}

document.addEventListener('DOMContentLoaded', function() {
    showEvent(currentEvent);
    startEventInterval();
    document.getElementById('event-next').onclick = function() {
        nextEvent();
        resetEventInterval();
    };
    document.getElementById('event-prev').onclick = function() {
        prevEvent();
        resetEventInterval();
    };
});
