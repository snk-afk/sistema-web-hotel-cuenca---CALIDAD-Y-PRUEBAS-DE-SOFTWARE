function formatTimeComponents(seconds) {
    const isNegative = seconds < 0;
    const abs = Math.abs(seconds);
    const hrs = String(Math.floor(abs / 3600)).padStart(2, '0');
    const mins = String(Math.floor((abs % 3600) / 60)).padStart(2, '0');
    const secs = String(abs % 60).padStart(2, '0');

    return { isNegative, hrs, mins, secs, text: `${hrs}h ${mins}m ${secs}s` };
}

function initStayTimers() {
    const containers = document.querySelectorAll('.timer-container');

    containers.forEach(container => {
        const display = container.querySelector('.timer-display');
        if (!display) return;

        let secondsRemaining = parseInt(container.getAttribute('data-seconds'), 10);
        if (isNaN(secondsRemaining)) {
            display.textContent = '---';
            return;
        }

        const updateTimer = () => {
            const time = formatTimeComponents(secondsRemaining);

            if (time.isNegative) {
                display.textContent = `⚠️ Excedido: ${time.text}`;
                display.style.color = '#ef4444';
                display.style.fontWeight = '700';
            } else if (secondsRemaining <= 1800) { // <= 30 mins
                display.textContent = `⏳ ${time.text}`;
                display.style.color = '#f59e0b';
                display.style.fontWeight = '700';
            } else {
                display.textContent = `⏱️ ${time.text}`;
                display.style.color = '#38bdf8';
                display.style.fontWeight = '600';
            }

            secondsRemaining--;
        };

        updateTimer();
        setInterval(updateTimer, 1000);
    });
}

function startTimers() {
    initStayTimers();
}

document.addEventListener('DOMContentLoaded', () => {
    initStayTimers();
});
