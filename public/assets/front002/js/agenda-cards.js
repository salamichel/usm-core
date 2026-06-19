(function() {
    // Status color mapping
    const statusColors = {
        'Disponible': 'bg-green-100 text-green-700 hover:bg-green-200',
        'Disponible si nécessaire': 'bg-yellow-100 text-yellow-700 hover:bg-yellow-200',
        'Indisponible': 'bg-red-100 text-red-700 hover:bg-red-200',
        'Ne sait pas encore': 'bg-gray-200 text-gray-700 hover:bg-gray-300',
        'Présent': 'bg-green-100 text-green-700 hover:bg-green-200',
        'Absent': 'bg-red-100 text-red-700 hover:bg-red-200',
        '.': 'bg-gray-100 text-gray-600 hover:bg-gray-200'
    };

    // Bulk actions
    const applyBulkStatus = (filter, status) => {
        if (!status) return;
        const grid = document.getElementById('event-grid');
        if (!grid) return;
        const cards = grid.querySelectorAll(`[data-event-filter="${filter}"]`);
        cards.forEach(card => {
            const btn = [...card.querySelectorAll('.status-btn')].find(b => b.dataset.status === status);
            if (btn) btn.click();
        });
    };

    document.getElementById('bulk-match-apply')?.addEventListener('click', () => {
        const status = document.getElementById('bulk-match-status').value;
        if (status) applyBulkStatus('match', status);
    });

    document.getElementById('bulk-entrainement-apply')?.addEventListener('click', () => {
        const status = document.getElementById('bulk-entrainement-status').value;
        if (status) applyBulkStatus('entrainement', status);
    });
    
    document.getElementById('bulk-club-apply')?.addEventListener('click', () => {
        const status = document.getElementById('bulk-club-status').value;
        if (status) applyBulkStatus('club', status);
    });

    document.getElementById('bulk-beach-apply')?.addEventListener('click', () => {
        const status = document.getElementById('bulk-beach-status').value;
        if (status) applyBulkStatus('beach', status);
    });

    document.getElementById('bulk-reset-all')?.addEventListener('click', () => {
        const grid = document.getElementById('event-grid');
        if (!grid) return;
        const btns = grid.querySelectorAll('[data-status="."]');
        btns.forEach(btn => btn.click());
    });

    // Filtrage des événements (si présent sur la page)
    const filterButtons = document.querySelectorAll('.filter-button');
    const activeClasses = ['bg-[var(--primary)]', 'text-white'];
    const inactiveClasses = ['bg-slate-100', 'text-slate-700', 'hover:bg-slate-200'];

    filterButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const filter = this.dataset.filter;
            const grid = document.getElementById('event-grid');
            if (!grid) return;
            const cards = grid.querySelectorAll('[data-event-filter]');

            // Update button states
            filterButtons.forEach(b => {
                b.classList.remove(...activeClasses);
                b.classList.add(...inactiveClasses);
            });
            this.classList.remove(...inactiveClasses);
            this.classList.add(...activeClasses);

            // Filter cards
            cards.forEach(card => {
                if (filter === 'all' || card.dataset.eventFilter === filter) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });

    // Initialize button handlers
    const initButtons = () => {
        document.querySelectorAll('.status-btn').forEach(btn => {
            // Avoid double binding
            if (btn.dataset.initialized) return;
            btn.dataset.initialized = "true";

            btn.addEventListener('click', function(e) {
                e.preventDefault();
                
                const card = this.closest('[data-manifestation-id]');
                const manifestationId = parseInt(card.dataset.manifestationId);
                const status = this.dataset.status;

                // Visual feedback
                const allBtns = card.querySelectorAll('.status-btn');
                allBtns.forEach(b => b.classList.remove('ring-2', 'ring-blue-500', 'bg-blue-50'));
                this.classList.add('ring-2', 'ring-blue-500', 'bg-blue-50');

                // Send AJAX request
                fetch('/api/member/participations/upsert', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({manifestation_id: manifestationId, status: status})
                })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        // Update status badge
                        const badge = card.querySelector('#status-' + manifestationId);
                        if (badge) {
                            if (status === '.') {
                                badge.textContent = '? Non renseigné';
                                badge.className = 'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700';
                            } else {
                                const colors = statusColors[status] || statusColors['Ne sait pas encore'];
                                const [bgClass, textClass] = colors.split(' ');
                                badge.className = 'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ' + bgClass + ' ' + textClass;
                                const icon = status === 'Disponible' ? '✓' : status === 'Disponible si nécessaire' ? '◐' : status === 'Indisponible' ? '✗' : status === 'Présent' ? '✓' : status === 'Absent' ? '✗' : '?';
                                badge.textContent = icon + ' ' + status;
                            }
                        }
                        
                        // Pulse feedback
                        this.classList.add('animate-pulse');
                        setTimeout(() => this.classList.remove('animate-pulse'), 500);
                    }
                })
                .catch(err => console.error('Error:', err));
            });

            // Initialize button appearance based on current status (only if badge exists)
            const card = btn.closest('[data-manifestation-id]');
            const badge = card.querySelector('[id^="status-"]');
            if (badge) {
                const badgeText = badge.textContent.trim();
                const btnStatus = btn.dataset.status;

                // Highlight current status button
                if ((badgeText.includes('Disponible') && btnStatus === 'Disponible') ||
                    (badgeText.includes('Si nécessaire') && btnStatus === 'Disponible si nécessaire') ||
                    (badgeText.includes('Indisponible') && btnStatus === 'Indisponible') ||
                    (badgeText.includes('Présent') && btnStatus === 'Présent') ||
                    (badgeText.includes('Absent') && btnStatus === 'Absent') ||
                    (badgeText.includes('Non renseigné') && btnStatus === '.')) {
                    btn.classList.add('ring-2', 'ring-blue-500', 'bg-blue-50');
                }
            }

            // Apply base colors
            const btnStatus = btn.dataset.status;
            const colors = statusColors[btnStatus] || statusColors['Ne sait pas encore'];
            btn.className = 'status-btn flex-1 py-2 px-2 rounded-md text-xs font-medium transition-colors ' + colors;
        });
    };

    initButtons();
})();