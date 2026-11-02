let currentActiveStatusFilter = 'all';

document.addEventListener('DOMContentLoaded', () => {
    updateRackStatsCounts();
    if (typeof startTimers === 'function') {
        startTimers();
    }
});

function updateRackStatsCounts() {
    const cards = document.querySelectorAll('#rack-rooms-grid .room-card');
    let free = 0, occupied = 0, overdue = 0, cleaning = 0, maintenance = 0, reserved = 0;

    cards.forEach(card => {
        const st = card.getAttribute('data-status');
        const isOverdue = card.getAttribute('data-is-overdue') === '1' || parseInt(card.getAttribute('data-seconds-remaining') || '1', 10) <= 0;

        if (st === 'free') {
            free++;
        } else if (st === 'occupied') {
            occupied++;
            if (isOverdue) {
                overdue++;
            }
        } else if (st === 'cleaning') {
            cleaning++;
        } else if (st === 'maintenance') {
            maintenance++;
        } else if (st === 'reserved') {
            reserved++;
        }
    });

    const countAllEl = document.getElementById('count-all');
    const countFreeEl = document.getElementById('count-free');
    const countOccEl = document.getElementById('count-occupied');
    const countOverdueEl = document.getElementById('count-overdue');
    const countCleanEl = document.getElementById('count-cleaning');
    const countMaintEl = document.getElementById('count-maintenance');
    const countReservedEl = document.getElementById('count-reserved');

    if (countAllEl) countAllEl.textContent = cards.length;
    if (countFreeEl) countFreeEl.textContent = free;
    if (countOccEl) countOccEl.textContent = occupied;
    if (countOverdueEl) countOverdueEl.textContent = overdue;
    if (countCleanEl) countCleanEl.textContent = cleaning;
    if (countMaintEl) countMaintEl.textContent = maintenance;
    if (countReservedEl) countReservedEl.textContent = reserved;
}

function filterRackByStatus(status, element) {
    currentActiveStatusFilter = status;
    const pills = document.querySelectorAll('.stat-pill');
    pills.forEach(p => p.classList.remove('active-filter'));

    if (element) {
        element.classList.add('active-filter');
    }

    filterRackRooms();
}

function filterRackRooms() {
    const searchInput = document.getElementById('rack-search-input');
    const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
    const cards = document.querySelectorAll('#rack-rooms-grid .room-card');

    cards.forEach(card => {
        const roomNum = (card.getAttribute('data-room-number') || '').toLowerCase();
        const catName = (card.getAttribute('data-category-name') || '').toLowerCase();
        const guestName = (card.getAttribute('data-guest-name') || '').toLowerCase();
        const guestDoc = (card.getAttribute('data-guest-doc') || '').toLowerCase();
        const status = (card.getAttribute('data-status') || '').toLowerCase();
        const isOverdue = card.getAttribute('data-is-overdue') === '1' || parseInt(card.getAttribute('data-seconds-remaining') || '1', 10) <= 0;

        const matchesQuery = query === '' || 
            roomNum.includes(query) || 
            catName.includes(query) || 
            guestName.includes(query) || 
            guestDoc.includes(query);

        let matchesStatus = false;
        if (currentActiveStatusFilter === 'all') {
            matchesStatus = true;
        } else if (currentActiveStatusFilter === 'overdue') {
            matchesStatus = status === 'occupied' && isOverdue;
        } else if (currentActiveStatusFilter === 'occupied') {
            matchesStatus = status === 'occupied';
        } else if (currentActiveStatusFilter === 'maintenance') {
            matchesStatus = status === 'maintenance';
        } else {
            matchesStatus = status === currentActiveStatusFilter;
        }

        if (matchesQuery && matchesStatus) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}

async function quickReleaseCleaningRoom(roomId, event) {
    if (event) {
        event.stopPropagation();
    }

    try {
        const res = await apiFetch('/api/rooms', {
            method: 'POST',
            body: JSON.stringify({
                action: 'update_status',
                room_id: roomId,
                status: 'free',
            })
        });

        if (res.ok) {
            showToast('✨ Habitación liberada y lista para recibir nuevos clientes', 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(res.data.message || 'Error al liberar habitación', 'error');
        }
    } catch (e) {
        showToast('Error de red al liberar habitación', 'error');
    }
}
