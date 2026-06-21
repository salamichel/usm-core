(function() {
    // ==========================================
    // 1. ACTIONS DE MASSE (Bulk actions)
    // ==========================================
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

    // ==========================================
    // 2. GESTION DES CLICS & MISE À JOUR LIVE
    // ==========================================
    document.addEventListener('click', function(e) {
        // Capter le clic sur le bouton (ou ses icônes enfants)
        const btn = e.target.closest('.status-btn');
        if (!btn) return;

        e.preventDefault();

        // Récupérer la carte parente
        const card = btn.closest('[data-manifestation-id]');
        if (!card) return;

        const manifestationId = parseInt(card.dataset.manifestationId);
        const newStatus = btn.dataset.status;
        const oldStatus = card.dataset.currentStatus || '.';

        // Si on clique sur le statut déjà sélectionné, on annule
        if (newStatus === oldStatus) return;

        // Effet visuel pendant le chargement
        btn.classList.add('opacity-50', 'cursor-wait');

        // Appel AJAX vers votre backend
        fetch('/api/member/participations/upsert', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({manifestation_id: manifestationId, status: newStatus})
        })
        .then(r => r.json())
        .then(data => {
            btn.classList.remove('opacity-50', 'cursor-wait');
            
            if (data.ok) {
                // ==========================================
                // A. MISE À JOUR DES COMPTEURS (Données Serveur)
                // ==========================================
                if (data.counts && typeof data.counts === 'object') {
                    
                    // ÉTAPE 1 : Mettre tous les compteurs de la carte à "0"
                    // (Indispensable car le SQL GROUP BY ne renvoie pas les statuts vides)
                    card.querySelectorAll('.status-btn').forEach(b => {
                        const spans = b.querySelectorAll('span');
                        // S'il y a plus de 1 span, c'est que le 2ème est le chiffre du compteur
                        if (spans.length > 1) {
                            spans[1].textContent = '0';
                        }
                    });

                    // ÉTAPE 2 : Appliquer les vrais chiffres renvoyés par PHP
                    Object.entries(data.counts).forEach(([statName, statValue]) => {
                        const targetBtn = card.querySelector(`[data-status="${statName}"]`);
                        if (targetBtn) {
                            const countSpan = targetBtn.querySelectorAll('span')[1];
                            if (countSpan) {
                                countSpan.textContent = statValue;
                            }
                        }
                    });
                }

                // ==========================================
                // B. MISE À JOUR DES COULEURS & BADGES
                // ==========================================
                
                // Mémoriser le nouveau statut
                card.dataset.currentStatus = newStatus;

                // Changer la bordure gauche épaisse
                card.classList.remove('border-l-emerald-500', 'border-l-amber-400', 'border-l-rose-500', 'border-l-slate-400', 'border-l-slate-200');
                if (['Disponible', 'Présent'].includes(newStatus)) card.classList.add('border-l-emerald-500');
                else if (newStatus === 'Disponible si nécessaire') card.classList.add('border-l-amber-400');
                else if (['Indisponible', 'Absent'].includes(newStatus)) card.classList.add('border-l-rose-500');
                else if (newStatus === 'Ne sait pas encore') card.classList.add('border-l-slate-400');
                else card.classList.add('border-l-slate-200');

                // Changer le petit badge en haut à gauche
                const badge = card.querySelector(`#status-${manifestationId}`);
                if (badge) {
                    let icon = '', bgClass = '', textClass = '', borderClass = '';
                    
                    if (['Disponible', 'Présent'].includes(newStatus)) {
                        icon = '✓'; bgClass = 'bg-emerald-50'; textClass = 'text-emerald-700'; borderClass = 'border-emerald-200';
                    } else if (newStatus === 'Disponible si nécessaire') {
                        icon = '◐'; bgClass = 'bg-amber-50'; textClass = 'text-amber-700'; borderClass = 'border-amber-200';
                    } else if (['Indisponible', 'Absent'].includes(newStatus)) {
                        icon = '✗'; bgClass = 'bg-rose-50'; textClass = 'text-rose-700'; borderClass = 'border-rose-200';
                    } else if (newStatus === 'Ne sait pas encore') {
                        icon = '?'; bgClass = 'bg-slate-50'; textClass = 'text-slate-700'; borderClass = 'border-slate-200';
                    } else {
                        icon = '?'; bgClass = 'bg-slate-50'; textClass = 'text-slate-500'; borderClass = 'border-slate-200';
                    }

                    badge.className = `inline-flex items-center px-2.5 py-1.5 rounded-full text-xs font-bold shadow-sm border ${bgClass} ${textClass} ${borderClass}`;
                    badge.innerHTML = newStatus === '.' 
                        ? `<span class="mr-1 text-sm">?</span> Non renseigné` 
                        : `<span class="mr-1 text-sm">${icon}</span> ${newStatus}`;
                }

                // Animation "Pulse" pour confirmer à l'utilisateur que le clic a marché
                btn.classList.add('animate-pulse');
                setTimeout(() => btn.classList.remove('animate-pulse'), 500);

            } else {
                alert("Erreur lors de l'enregistrement : " + (data.message || "Inconnue"));
            }
        })
        .catch(err => {
            console.error('Erreur AJAX:', err);
            btn.classList.remove('opacity-50', 'cursor-wait');
            alert("Erreur de communication avec le serveur.");
        });
    });
})();