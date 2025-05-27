// Demo agent schedules (busy days: 1=busy, 0=free)
const agentSchedules = {
    john:  [0,0,0,0,1,1,1, 0,0,1,0,0,1,1, 0,0,0,1,0,1,1, 1,0,0,0,1,1,1, 0,1,1],
    jane:  [1,0,0,1,0,1,1, 0,1,0,0,1,1,1, 0,0,1,0,0,1,1, 0,0,0,1,0,1,1, 0,0,1],
    alex:  [0,0,1,0,0,1,1, 1,0,0,0,1,1,1, 0,1,0,0,0,1,1, 0,0,1,0,0,1,1, 0,0,1]
};

const timeSlots = [
    "09:00 - 09:30", "10:00 - 10:30", "11:00 - 11:30", "14:00 - 14:30", "15:00 - 15:30", "16:00 - 16:30"
];

let selectedAgent = 'john';
let selectedDate = null;
let selectedSlot = null;
let selectedMonth = new Date().getMonth();
let selectedYear = new Date().getFullYear();
let currentMonth = selectedMonth;
let currentYear = selectedYear;

function renderCalendar(month, year, agent) {
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const schedule = agentSchedules[agent];
    const calendarBody = document.getElementById('calendarBody');
    const monthYearLabel = document.getElementById('calendarMonthYear');
    calendarBody.innerHTML = '';
    monthYearLabel.textContent = `${new Date(year, month).toLocaleString('default', { month: 'long' })} ${year}`;

    let date = 1;
    for (let i = 0; i < 6; i++) {
        let row = document.createElement('tr');
        for (let j = 0; j < 7; j++) {
            let cell = document.createElement('td');
            if (i === 0 && j < firstDay) {
                cell.innerHTML = '';
            } else if (date > daysInMonth) {
                cell.innerHTML = '';
            } else {
                cell.textContent = date;
                let busy = schedule && schedule[date - 1] === 1;
                cell.className = busy ? 'busy' : 'free';
                if (
                    selectedDate &&
                    date === selectedDate &&
                    month === selectedMonth &&
                    year === selectedYear
                ) {
                    cell.classList.add('selected');
                }
                // FIX: Create a closure to capture the correct date value
                cell.onclick = !busy ? (function(clickedDate) {
                    return function() { 
                        console.log('Clicking date:', clickedDate); // Debug
                        selectDate(clickedDate, month, year); 
                    };
                })(date) : null;
                date++;
            }
            row.appendChild(cell);
        }
        calendarBody.appendChild(row);
        if (date > daysInMonth) break;
    }
}

function selectDate(date, month, year) {
    console.log('Selected date:', date, 'month:', month, 'year:', year); // Debug line
    selectedDate = date;
    selectedMonth = month;
    selectedYear = year;
    currentMonth = month;
    currentYear = year;
    renderCalendar(month, year, selectedAgent);
    document.getElementById('timeSlotsSection').style.display = '';
    document.getElementById('selectedDateLabel').textContent =
        `${String(date).padStart(2, '0')}/${String(month+1).padStart(2, '0')}/${year}`;
    renderTimeSlots();
}

function renderTimeSlots() {
    const slotsDiv = document.getElementById('timeSlots');
    slotsDiv.innerHTML = '';
    selectedSlot = null;
    timeSlots.forEach(slot => {
        const btn = document.createElement('button');
        btn.className = 'btn btn-outline-primary';
        btn.textContent = slot;
        btn.onclick = function() {
            document.querySelectorAll('#timeSlots .btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            selectedSlot = slot;
            document.getElementById('confirmBtn').style.display = '';
        };
        slotsDiv.appendChild(btn);
    });
    document.getElementById('confirmBtn').style.display = 'none';
}

document.getElementById('agentSelect').addEventListener('change', function() {
    selectedAgent = this.value;
    selectedDate = null;
    document.getElementById('timeSlotsSection').style.display = 'none';
    renderCalendar(currentMonth, currentYear, selectedAgent);
});

document.getElementById('prevMonthBtn').addEventListener('click', function() {
    if (currentMonth === 0) {
        currentMonth = 11;
        currentYear--;
    } else {
        currentMonth--;
    }
    selectedDate = null;
    selectedMonth = null; // Reset selected month too
    selectedYear = null;  // Reset selected year too
    document.getElementById('timeSlotsSection').style.display = 'none';
    renderCalendar(currentMonth, currentYear, selectedAgent);
});

document.getElementById('nextMonthBtn').addEventListener('click', function() {
    if (currentMonth === 11) {
        currentMonth = 0;
        currentYear++;
    } else {
        currentMonth++;
    }
    selectedDate = null;
    selectedMonth = null; // Reset selected month too
    selectedYear = null;  // Reset selected year too
    document.getElementById('timeSlotsSection').style.display = 'none';
    renderCalendar(currentMonth, currentYear, selectedAgent);
});

document.getElementById('confirmBtn').addEventListener('click', function() {
    if (selectedDate && selectedSlot) {
        alert(`Appointment booked with ${document.getElementById('agentSelect').selectedOptions[0].text} on ${String(selectedDate).padStart(2, '0')}/${String(selectedMonth+1).padStart(2, '0')}/${selectedYear} at ${selectedSlot}`);
        // Here you would send the booking to your backend
    }
});

// Initial render
renderCalendar(currentMonth, currentYear, selectedAgent);
