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

    document.addEventListener('DOMContentLoaded', function() {
        
        // Éléments de la modale
        const modal = document.getElementById('player-modal');
        const modalTitle = document.getElementById('modal-title');
        const modalList = document.getElementById('modal-list');
        const closeBtn = document.getElementById('close-modal');

        // Fonction pour ouvrir la modale
        function openModal(title, names, colorClass) {
            modalTitle.textContent = title;
            modalList.innerHTML = ''; // On vide la liste

            if (names.length === 0) {
                modalList.innerHTML = '<li class="text-sm text-slate-400 italic text-center py-6 bg-slate-50 rounded-xl">Aucun joueur dans cette liste.</li>';
            } else {
                names.forEach(name => {
                    // On crée une jolie ligne pour chaque joueur
                    const li = document.createElement('li');
                    li.className = 'flex items-center gap-3 p-3 rounded-xl bg-slate-50/80 border border-slate-100 text-sm font-bold text-slate-700 hover:bg-slate-100 transition-colors';
                    
                    // On extrait la première lettre pour faire un petit avatar
                    const initial = name.charAt(0).toUpperCase();
                    li.innerHTML = `
                        <div class="w-8 h-8 rounded-full ${colorClass} flex items-center justify-center text-xs font-black shadow-sm">
                            ${initial}
                        </div>
                        ${name}
                    `;
                    modalList.appendChild(li);
                });
            }

            // Affichage avec animation
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            // Un tout petit délai pour que la transition CSS s'applique
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                modal.querySelector('div').classList.remove('scale-95');
            }, 10);
        }

        // Fonction pour fermer la modale
        function closeModal() {
            modal.classList.add('opacity-0');
            modal.querySelector('div').classList.add('scale-95');
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }, 300); // Correspond à la durée de la transition CSS (duration-300)
        }

        // Écouteurs pour fermer la modale
        if(closeBtn) closeBtn.addEventListener('click', closeModal);
        if(modal) {
            modal.addEventListener('click', function(e) {
                // Ferme si on clique en dehors de la boîte blanche
                if (e.target === modal) closeModal(); 
            });
        }

        // Écoute les clics sur les pastilles de statistiques
        document.querySelectorAll('.player-list-trigger').forEach(trigger => {
            trigger.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const title = this.dataset.title;
                const playersJson = this.dataset.players;
                let names = [];

                // Déduction de la couleur de l'avatar selon la couleur de la pastille cliquée
                let colorClass = 'bg-slate-200 text-slate-600';
                if (this.classList.contains('text-emerald-700')) colorClass = 'bg-emerald-100 text-emerald-700';
                if (this.classList.contains('text-amber-700')) colorClass = 'bg-amber-100 text-amber-700';
                if (this.classList.contains('text-rose-700')) colorClass = 'bg-rose-100 text-rose-700';

                try {
                    const playersArray = JSON.parse(playersJson);
                    names = playersArray.map(p => p.nom);
                } catch(err) {
                    console.error("Erreur de parsing des joueurs", err);
                }

                openModal(title, names, colorClass);
            });
        });
    });  
})();