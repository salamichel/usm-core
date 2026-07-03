(function() {
    // ==========================================
    // 1. ACTIONS DE MASSE (Bulk actions)
    // ==========================================
    // ==========================================
    // 1. ACTIONS DE MASSE (Bulk actions)
    // ==========================================
    function initBulkFilters() {
        console.log("initBulkFilters: Initialisation des filtres d'actions groupées...");
        const typeSelect = document.getElementById('bulk-type-select');
        const subtypeSelect = document.getElementById('bulk-subtype-select');
        const locationSelect = document.getElementById('bulk-location-select');
        const kindSelect = document.getElementById('bulk-kind-select');
        const applyBtn = document.getElementById('bulk-apply-btn');

        if (!typeSelect || !subtypeSelect || !locationSelect || !kindSelect || !applyBtn) {
            console.log("initBulkFilters: Éléments HTML des actions groupées manquants dans le DOM, abandon.");
            return;
        }

        const grid = document.getElementById('event-grid');
        if (!grid) {
            console.log("initBulkFilters: Grille #event-grid introuvable.");
            return;
        }

        // Récupérer toutes les cartes d'événements
        const cards = Array.from(grid.querySelectorAll('[data-manifestation-id]'));
        console.log("initBulkFilters: Nombre de cartes d'événements trouvées :", cards.length);

        function applyBulkFiltersToCards() {
            const typeVal = typeSelect.value;
            const subtypeVal = subtypeSelect.value;
            const locationVal = locationSelect.value;
            const kindVal = kindSelect.value;

            cards.forEach(card => {
                // 1. Filtrage type
                const cardType = card.dataset.eventTypeRaw || '';
                if (typeVal && cardType !== typeVal) {
                    card.style.display = 'none';
                    return;
                }

                // 2. Filtrage sous-type
                const cardSubtype = card.dataset.eventSubtypeRaw || '';
                if (subtypeVal && cardSubtype !== subtypeVal) {
                    card.style.display = 'none';
                    return;
                }

                // 3. Filtrage lieu
                const cardLocation = card.dataset.eventLocationRaw || '';
                if (locationVal && cardLocation.toLowerCase().trim() !== locationVal.toLowerCase().trim()) {
                    card.style.display = 'none';
                    return;
                }

                // 4. Filtrage dispo/présence
                const cardKind = card.dataset.eventKindRaw || '';
                if (kindVal && cardKind !== kindVal) {
                    card.style.display = 'none';
                    return;
                }

                // Si tout correspond, afficher la carte
                card.style.display = '';
            });

            // Gérer le placeholder "Aucun événement ne correspond à ce filtre."
            const visibleCards = cards.filter(c => c.style.display !== 'none');
            let placeholder = document.getElementById('no-filter-events-placeholder');
            if (visibleCards.length === 0) {
                if (!placeholder) {
                    placeholder = document.createElement('div');
                    placeholder.id = 'no-filter-events-placeholder';
                    placeholder.className = 'p-8 text-center bg-white rounded-2xl border border-slate-100 text-slate-400 text-sm w-full';
                    placeholder.textContent = 'Aucun événement ne correspond à ces critères.';
                    grid.appendChild(placeholder);
                }
            } else {
                if (placeholder) placeholder.remove();
            }

            // Régénérer le slider de dates selon les événements visibles
            if (typeof buildDateSlider === 'function') {
                buildDateSlider();
            }
        }

        function updateBulkFilters() {
            const events = cards.map(card => ({
                type: card.dataset.eventTypeRaw || '',
                subtype: card.dataset.eventSubtypeRaw || '',
                location: card.dataset.eventLocationRaw || '',
                kind: card.dataset.eventKindRaw || ''
            }));

            console.log("updateBulkFilters: Liste brute des événements :", events);

            // Sélections actuelles
            let selectedType = typeSelect.value;
            let selectedSubtype = subtypeSelect.value;
            let selectedLocation = locationSelect.value;
            let selectedKind = kindSelect.value;

            // Clear highlights of active dashboard filters if user interacts with bulk filters
            if (selectedType || selectedSubtype || selectedLocation || selectedKind) {
                activeFilterType = null;
                activeFilterValue = null;
                document.querySelectorAll('[data-kpi-filter]').forEach(el => {
                    el.classList.remove('ring-2', 'ring-indigo-600', 'bg-indigo-50');
                });
                document.querySelectorAll('[data-type-filter]').forEach(el => {
                    el.classList.remove('bg-indigo-50/80', 'font-bold', 'text-indigo-900');
                });
                document.querySelectorAll('[data-lieu-filter]').forEach(el => {
                    el.classList.remove('bg-indigo-50/80', 'font-bold', 'text-indigo-900');
                });
                const resetBtn = document.getElementById('reset-dashboard-filters');
                if (resetBtn) resetBtn.classList.remove('hidden');
            }

            // Filtrer les événements par type
            const eventsForType = selectedType ? events.filter(e => e.type === selectedType) : events;
            
            // Si le sous-type sélectionné n'est plus valide, le réinitialiser
            const validSubtypes = [...new Set(eventsForType.map(e => e.subtype))].filter(Boolean);
            if (selectedSubtype && !validSubtypes.includes(selectedSubtype)) {
                selectedSubtype = '';
                subtypeSelect.value = '';
            }

            // Filtrer les événements par type ET sous-type
            const eventsForSubtype = selectedSubtype ? eventsForType.filter(e => e.subtype === selectedSubtype) : eventsForType;

            // Si le lieu sélectionné n'est plus valide, le réinitialiser
            const validLocations = [...new Set(eventsForSubtype.map(e => e.location))].filter(Boolean);
            if (selectedLocation && !validLocations.includes(selectedLocation)) {
                selectedLocation = '';
                locationSelect.value = '';
            }

            // Filtrer les événements par type, sous-type ET lieu
            const eventsForLocation = selectedLocation ? eventsForSubtype.filter(e => e.location === selectedLocation) : eventsForSubtype;

            // Si la nature (kind) sélectionnée n'est plus valide, la réinitialiser
            const validKinds = [...new Set(eventsForLocation.map(e => e.kind))].filter(Boolean);
            if (selectedKind && !validKinds.includes(selectedKind)) {
                selectedKind = '';
                kindSelect.value = '';
            }

            // Mettre à jour les options des sélecteurs
            // 1. Type (toujours tous les types uniques du tableau initial)
            const uniqueTypes = [...new Set(events.map(e => e.type))].filter(Boolean).sort();
            console.log("updateBulkFilters: Types uniques trouvés :", uniqueTypes);
            updateSelectOptions(typeSelect, uniqueTypes, selectedType, 'Tous les types');

            // 2. Sous-type (selon le type sélectionné)
            const uniqueSubtypes = validSubtypes.sort();
            console.log("updateBulkFilters: Sous-types uniques trouvés :", uniqueSubtypes);
            updateSelectOptions(subtypeSelect, uniqueSubtypes, selectedSubtype, 'Tous les sous-types');

            // 3. Lieu (selon type + sous-type)
            const uniqueLocations = validLocations.sort();
            console.log("updateBulkFilters: Lieux uniques trouvés :", uniqueLocations);
            updateSelectOptions(locationSelect, uniqueLocations, selectedLocation, 'Tous les lieux');

            // 4. Nature (selon type + sous-type + lieu)
            const uniqueKinds = validKinds.sort();
            console.log("updateBulkFilters: Natures uniques trouvées :", uniqueKinds);
            updateSelectOptions(kindSelect, uniqueKinds, selectedKind, 'Tous (Dispo & Présences)');

            // Appliquer le filtre visuel en direct sur les cartes
            applyBulkFiltersToCards();
        }

        function updateSelectOptions(selectElement, optionsArray, currentValue, defaultLabel) {
            selectElement.innerHTML = `<option value="">${defaultLabel}</option>`;
            optionsArray.forEach(opt => {
                const option = document.createElement('option');
                option.value = opt;
                option.textContent = opt;
                if (opt === currentValue) {
                    option.selected = true;
                }
                selectElement.appendChild(option);
            });
        }

        // Enregistrer les écouteurs de changement
        typeSelect.addEventListener('change', updateBulkFilters);
        subtypeSelect.addEventListener('change', updateBulkFilters);
        locationSelect.addEventListener('change', updateBulkFilters);
        kindSelect.addEventListener('change', updateBulkFilters);

        // Alimentation initiale des sélecteurs
        updateBulkFilters();

        // Gestionnaire d'application des actions de masse
        applyBtn.addEventListener('click', () => {
            const typeVal = typeSelect.value;
            const subtypeVal = subtypeSelect.value;
            const locationVal = locationSelect.value;
            const kindVal = kindSelect.value;
            const statusChoice = document.getElementById('bulk-status-select').value;

            if (!statusChoice) return;

            console.log("applyBtn click: application du statut groupé...", { typeVal, subtypeVal, locationVal, kindVal, statusChoice });

            cards.forEach(card => {
                // 1. Filtrage type
                const cardType = card.dataset.eventTypeRaw || '';
                if (typeVal && cardType !== typeVal) return;

                // 2. Filtrage sous-type
                const cardSubtype = card.dataset.eventSubtypeRaw || '';
                if (subtypeVal && cardSubtype !== subtypeVal) return;

                // 3. Filtrage lieu
                const cardLocation = card.dataset.eventLocationRaw || '';
                if (locationVal && cardLocation.toLowerCase().trim() !== locationVal.toLowerCase().trim()) return;

                // 4. Filtrage dispo/présence
                const cardKind = card.dataset.eventKindRaw || '';
                if (kindVal && cardKind !== kindVal) return;

                // 5. Détermination du statut
                const isMatch = (card.dataset.eventFilter === 'match');
                let targetStatus = statusChoice;
                if (statusChoice === 'Disponible') {
                    targetStatus = isMatch ? 'Disponible' : 'Présent';
                } else if (statusChoice === 'Indisponible') {
                    targetStatus = isMatch ? 'Indisponible' : 'Absent';
                }

                // Cliquer le bouton correspondant
                const btn = [...card.querySelectorAll('.status-btn')].find(b => b.dataset.status === targetStatus);
                if (btn) {
                    btn.click();
                }
            });

            // Filtre visuel de tableau de bord optionnel
            if (typeVal) {
                activeFilterType = 'type';
                if (typeVal.toLowerCase().includes('match')) activeFilterValue = 'match';
                else if (typeVal.toLowerCase().includes('entrain')) activeFilterValue = 'entrainement';
                else if (typeVal.toLowerCase().includes('tournoi') || typeVal.toLowerCase().includes('plateau')) activeFilterValue = 'tournois';
                else if (typeVal.toLowerCase().includes('forum')) activeFilterValue = 'forum';
                else activeFilterValue = typeVal.toLowerCase();
                applyDashboardFilters();
            } else if (locationVal) {
                activeFilterType = 'lieu';
                activeFilterValue = locationVal;
                applyDashboardFilters();
            }
        });
    }

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
        const btn = e.target.closest('.status-btn');
        if (!btn) return;

        e.preventDefault();

        const card = btn.closest('[data-manifestation-id]');
        if (!card) return;

        const manifestationId = parseInt(card.dataset.manifestationId);
        const newStatus = btn.dataset.status;
        const oldStatus = card.dataset.currentStatus || '.';

        if (newStatus === oldStatus) return;

        btn.classList.add('opacity-50', 'cursor-wait');

        fetch('/api/member/participations/upsert', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({manifestation_id: manifestationId, status: newStatus})
        })
        .then(r => r.json())
        .then(data => {
            btn.classList.remove('opacity-50', 'cursor-wait');
            
            if (data.ok) {
                card.dataset.currentStatus = newStatus;

                // Mettre à jour l'apparence active/inactive des boutons d'action
                card.querySelectorAll('.status-btn').forEach(b => {
                    const status = b.dataset.status;
                    const isActive = status === newStatus;
                    
                    if (['Disponible', 'Présent'].includes(status)) {
                        b.className = `status-btn flex-1 min-w-[80px] px-3 py-2 rounded-xl text-xs font-bold transition-all active:scale-95 flex items-center justify-center gap-1 ${
                            isActive ? 'bg-emerald-600 text-white' : 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100 border border-emerald-100'
                        }`;
                    } else if (status === 'Disponible si nécessaire') {
                        b.className = `status-btn flex-1 min-w-[80px] px-3 py-2 rounded-xl text-xs font-bold transition-all active:scale-95 flex items-center justify-center gap-1 ${
                            isActive ? 'bg-amber-500 text-white' : 'bg-amber-50 text-amber-600 hover:bg-amber-100 border border-amber-100'
                        }`;
                    } else if (['Indisponible', 'Absent'].includes(status)) {
                        b.className = `status-btn flex-1 min-w-[80px] px-3 py-2 rounded-xl text-xs font-bold transition-all active:scale-95 flex items-center justify-center gap-1 ${
                            isActive ? 'bg-rose-600 text-white' : 'bg-rose-50 text-rose-600 hover:bg-rose-100 border border-rose-100'
                        }`;
                    } else if (status === '.') {
                        const isResetActive = !newStatus || newStatus === '.';
                        b.className = `status-btn px-2.5 py-2 rounded-xl text-xs font-medium transition-all active:scale-95 flex items-center justify-center gap-1 ${
                            isResetActive ? 'bg-slate-400 text-white' : 'bg-slate-100 text-slate-400 hover:bg-slate-200'
                        }`;
                    }
                });

                // Changer le badge textuel de statut
                const badge = card.querySelector(`#status-${manifestationId}`);
                if (badge) {
                    let icon = '', textClass = '';
                    
                    if (['Disponible', 'Présent'].includes(newStatus)) {
                        icon = '✓'; textClass = 'text-emerald-600';
                    } else if (newStatus === 'Disponible si nécessaire') {
                        icon = '◐'; textClass = 'text-amber-500';
                    } else if (['Indisponible', 'Absent'].includes(newStatus)) {
                        icon = '✗'; textClass = 'text-rose-500';
                    } else if (newStatus === 'Ne sait pas encore') {
                        icon = '?'; textClass = 'text-slate-600';
                    } else {
                        icon = '?'; textClass = 'text-slate-400';
                    }

                    badge.className = `inline-flex items-center text-xs font-black mt-0.5 ${textClass}`;
                    badge.innerHTML = newStatus === '.' 
                        ? `Non renseigné` 
                        : `<span class="mr-1 text-xs">${icon}</span> ${newStatus === 'Disponible si nécessaire' ? 'Si besoin' : newStatus}`;
                }

                // A. MISE À JOUR DES COMPTEURS & BULLES STATS
                if (data.counts && typeof data.counts === 'object') {
                    card.querySelectorAll('[data-status-key]').forEach(bubble => {
                        const countVal = bubble.querySelector('.count-value');
                        if (countVal) countVal.textContent = '0';
                    });

                    Object.entries(data.counts).forEach(([statName, statValue]) => {
                        const bubble = card.querySelector(`[data-status-key="${statName}"]`);
                        if (bubble) {
                            const countVal = bubble.querySelector('.count-value');
                            if (countVal) countVal.textContent = statValue;
                        }
                    });
                }

                // B. MISE À JOUR DE LA BARRE DE PROGRESSION
                const progressBar = card.querySelector('.progress-bar');
                const progressLabel = card.querySelector('.progress-label');
                if (progressBar && progressLabel) {
                    const isMatch = card.dataset.eventFilter === 'match';
                    if (isMatch) {
                        const minPlayers = parseInt(card.dataset.minPlayers || 6);
                        const totalCount = (data.counts['Présent'] || 0) + (data.counts['Disponible'] || 0) + (data.counts['Disponible si nécessaire'] || 0);
                        const pct = (totalCount >= minPlayers) ? 100 : (totalCount / minPlayers * 100);
                        progressBar.style.width = pct + '%';
                        
                        if (totalCount >= minPlayers) {
                            progressBar.className = 'progress-bar h-full rounded-full transition-all duration-500 bg-gradient-to-r from-emerald-400 to-teal-500';
                            progressLabel.innerHTML = '<span class="text-emerald-600 flex items-center gap-1">✓ Équipe complète (' + totalCount + ')</span>';
                        } else {
                            progressBar.className = 'progress-bar h-full rounded-full transition-all duration-500 bg-gradient-to-r from-orange-400 to-amber-500';
                            progressLabel.innerHTML = '<span class="text-orange-500">⚠ Sous-effectif (' + totalCount + '/' + minPlayers + ')</span>';
                        }
                    } else {
                        const totalPresents = data.counts['Présent'] || 0;
                        const pct = (totalPresents >= 12) ? 100 : (totalPresents / 12 * 100);
                        progressBar.style.width = pct + '%';
                        progressLabel.innerHTML = '<span class="text-indigo-500">' + totalPresents + ' présent(s)</span>';
                    }
                }

                // C. MISE À JOUR DES AVATARS EN DIRECT
                const grid = document.getElementById('event-grid');
                const currentUserId = parseInt(grid?.dataset.loggedInUserId || 0);
                const currentUserName = grid?.dataset.loggedInUserName || '';

                if (currentUserId && currentUserName) {
                    card.querySelectorAll('[data-status-key]').forEach(bubble => {
                        let players = [];
                        try {
                            players = JSON.parse(bubble.dataset.players || '[]');
                        } catch(e) {}
                        
                        players = players.filter(p => p.id !== currentUserId);
                        
                        const key = bubble.dataset.statusKey;
                        if (key === newStatus) {
                            players.push({id: currentUserId, nom: currentUserName});
                        }
                        
                        bubble.dataset.players = JSON.stringify(players);
                    });
                    
                    rebuildAvatarStack(card);
                }

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

    // Reconstruire dynamiquement les avatars empilés d'une carte
    function rebuildAvatarStack(card) {
        const isMatch = card.dataset.eventFilter === 'match';
        let activePlayers = [];
        
        if (isMatch) {
            const dispBubble = card.querySelector('[data-status-key="Disponible"]');
            const sibBubble = card.querySelector('[data-status-key="Disponible si nécessaire"]');
            
            const dispPlayers = dispBubble ? JSON.parse(dispBubble.dataset.players || '[]') : [];
            const sibPlayers = sibBubble ? JSON.parse(sibBubble.dataset.players || '[]') : [];
            activePlayers = [...dispPlayers, ...sibPlayers];
        } else {
            const presBubble = card.querySelector('[data-status-key="Présent"]');
            const presPlayers = presBubble ? JSON.parse(presBubble.dataset.players || '[]') : [];
            activePlayers = [...presPlayers];
        }
        
        const stackContainer = card.querySelector('.avatar-stack');
        if (stackContainer) {
            stackContainer.innerHTML = '';
            const maxAvatars = 5;
            const displayed = activePlayers.slice(0, maxAvatars);
            
            displayed.forEach(p => {
                const parts = p.nom.split(' ');
                const prenom = parts[parts.length - 1] || p.nom;
                const initial = prenom.charAt(0).toUpperCase();
                
                const colors = [
                    'bg-blue-100 text-blue-700',
                    'bg-emerald-100 text-emerald-700',
                    'bg-violet-100 text-violet-700',
                    'bg-pink-100 text-pink-700',
                    'bg-amber-100 text-amber-700',
                    'bg-teal-100 text-teal-700'
                ];
                const colorClass = colors[p.id % colors.length];
                
                const avatar = document.createElement('div');
                avatar.className = `w-7 h-7 rounded-full border-2 border-white ${colorClass} flex items-center justify-center text-[9px] font-black -ml-2.5 first:ml-0 shadow-sm relative z-30`;
                avatar.title = p.nom;
                avatar.textContent = initial;
                stackContainer.appendChild(avatar);
            });
            
            if (activePlayers.length > maxAvatars) {
                const overflow = document.createElement('div');
                overflow.className = `player-list-trigger cursor-pointer w-7 h-7 rounded-full border-2 border-white bg-slate-100 text-slate-700 flex items-center justify-center text-[9px] font-black -ml-2.5 relative z-30 shadow-sm hover:scale-110 hover:z-40 transition-all`;
                overflow.dataset.title = 'Joueurs inscrits';
                overflow.dataset.players = JSON.stringify(activePlayers);
                overflow.textContent = `+${activePlayers.length - maxAvatars}`;
                stackContainer.appendChild(overflow);
            } else if (activePlayers.length > 0) {
                const info = document.createElement('div');
                info.className = `player-list-trigger cursor-pointer w-7 h-7 rounded-full border-2 border-white bg-slate-50 text-slate-400 flex items-center justify-center text-[9px] font-bold -ml-2.5 relative z-30 shadow-sm hover:scale-110 hover:z-40 transition-all`;
                info.dataset.title = 'Joueurs inscrits';
                info.dataset.players = JSON.stringify(activePlayers);
                info.textContent = 'ℹ';
                stackContainer.appendChild(info);
            } else {
                stackContainer.innerHTML = '<span class="text-[10px] text-slate-400 italic -ml-1">Aucun</span>';
            }
        }
    }

    // ==========================================
    // 3. GESTION DE LA MODALE & RECHERCHE & SLIDER
    // ==========================================
    const modal = document.getElementById('player-modal');
    const modalTitle = document.getElementById('modal-title');
    const modalList = document.getElementById('modal-list');
    const closeBtn = document.getElementById('close-modal');

    function openModal(title, names, colorClass) {
        if (!modalTitle || !modalList || !modal) return;
        modalTitle.textContent = title;
        modalList.innerHTML = '';

        if (names.length === 0) {
            modalList.innerHTML = '<li class="text-sm text-slate-400 italic text-center py-6 bg-slate-50 rounded-xl">Aucun joueur dans cette liste.</li>';
        } else {
            names.forEach(name => {
                const li = document.createElement('li');
                li.className = 'flex items-center gap-3 p-3 rounded-xl bg-slate-50/80 border border-slate-100 text-sm font-bold text-slate-700 hover:bg-slate-100 transition-colors';
                const initial = name.charAt(0).toUpperCase();
                li.innerHTML = `
                    <div class="w-8 h-8 rounded-full ${colorClass} flex items-center justify-center text-xs font-black shadow-sm shrink-0">
                        ${initial}
                    </div>
                    <span>${name}</span>
                `;
                modalList.appendChild(li);
            });
        }

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            modal.querySelector('div').classList.remove('scale-95');
        }, 10);
    }

    function closeModal() {
        if (!modal) return;
        modal.classList.add('opacity-0');
        modal.querySelector('div').classList.add('scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }, 300);
    }

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeModal(); 
        });
    }

    // Écouteur global pour ouvrir la modale
    document.addEventListener('click', function(e) {
        const trigger = e.target.closest('.player-list-trigger');
        if (!trigger) return;

        e.preventDefault();
        e.stopPropagation();

        const title = trigger.dataset.title;
        const playersJson = trigger.dataset.players;
        let names = [];

        let colorClass = 'bg-slate-200 text-slate-600';
        if (trigger.classList.contains('text-emerald-700') || trigger.classList.contains('bg-emerald-50')) colorClass = 'bg-emerald-100 text-emerald-700';
        if (trigger.classList.contains('text-amber-700') || trigger.classList.contains('bg-amber-50') || trigger.classList.contains('text-amber-600')) colorClass = 'bg-amber-100 text-amber-700';
        if (trigger.classList.contains('text-rose-700') || trigger.classList.contains('bg-rose-50')) colorClass = 'bg-rose-100 text-rose-700';

        try {
            const playersArray = JSON.parse(playersJson);
            names = playersArray.map(p => p.nom);
        } catch(err) {
            console.error("Erreur de parsing des joueurs", err);
        }

        openModal(title, names, colorClass);
    });

    // ==========================================
    // 4. INTERACTIVITÉ DU SLIDER & RECHERCHE
    // ==========================================
    function initAll() {
        console.log("initAll: Initialisation globale des composants...");
        // Toggle affichage compact
        const toggleCompactBtn = document.getElementById('toggle-compact-btn');
        const eventGrid = document.getElementById('event-grid');
        if (toggleCompactBtn && eventGrid) {
            const localPref = localStorage.getItem('usm-dashboard-compact');
            const isCompact = localPref === null ? true : localPref === 'true';
            
            if (isCompact) {
                eventGrid.classList.add('compact-grid');
                updateCompactBtnUI(true);
            } else {
                eventGrid.classList.remove('compact-grid');
                updateCompactBtnUI(false);
            }

            toggleCompactBtn.addEventListener('click', () => {
                const active = eventGrid.classList.toggle('compact-grid');
                localStorage.setItem('usm-dashboard-compact', active);
                updateCompactBtnUI(active);
            });
        }

        function updateCompactBtnUI(isCompact) {
            const iconEl = document.getElementById('toggle-compact-icon');
            const textEl = document.getElementById('toggle-compact-text');
            if (isCompact) {
                if (iconEl) iconEl.textContent = '⊞';
                if (textEl) textEl.textContent = 'Vue détaillée';
            } else {
                if (iconEl) iconEl.textContent = '☰';
                if (textEl) textEl.textContent = 'Vue compacte';
            }
        }

        // Toggle filtres
        const toggleFiltersBtn = document.getElementById('toggle-filters-btn');
        const collapsibleFilters = document.getElementById('collapsible-filters');
        if (toggleFiltersBtn && collapsibleFilters) {
            toggleFiltersBtn.addEventListener('click', () => {
                const isHidden = collapsibleFilters.classList.toggle('hidden');
                toggleFiltersBtn.classList.toggle('bg-slate-800', !isHidden);
            });
        }

        // Recherche dynamique client-side
        const searchInput = document.getElementById('agenda-search');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                const val = e.target.value.toLowerCase().trim();
                const cards = document.querySelectorAll('#event-grid > div');
                cards.forEach(card => {
                    const title = card.querySelector('h3').textContent.toLowerCase();
                    const location = card.querySelector('.text-slate-500')?.textContent.toLowerCase() || '';
                    const matches = title.includes(val) || location.includes(val);
                    card.style.display = matches ? '' : 'none';
                });
                // Régénérer le slider de dates selon les événements visibles
                buildDateSlider();
            });
        }

        // Initialisation du slider et des observateurs
        buildDateSlider();
        
        // Initialisation des filtres du tableau de bord adhérent
        initDashboardFilters();
        initBulkFilters();

        // Gestion des filtres et du défilement automatique via URL
        handleUrlParamsOnLoad();
    }

    function handleUrlParamsOnLoad() {
        const urlParams = new URLSearchParams(window.location.search);
        
        // 1. Gestion du filtre automatique de l'espace membre
        if (urlParams.get('filter') === 'this-week') {
            activeFilterType = 'kpi';
            activeFilterValue = 'this-week';
            if (typeof applyDashboardFilters === 'function') {
                applyDashboardFilters();
            }
            if (typeof buildDateSlider === 'function') {
                buildDateSlider();
            }
        }
        
        // 2. Défilement automatique vers la première carte
        if (urlParams.get('scroll') === '1') {
            setTimeout(() => {
                const firstCard = document.querySelector('#event-grid > div[data-manifestation-id]:not([style*="display: none"])');
                if (firstCard) {
                    const headerHeight = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--header-h')) || 76;
                    const dateSlider = document.getElementById('date-slider');
                    const sliderHeight = dateSlider ? 85 : 0;
                    const yOffset = -(headerHeight + sliderHeight - 10);
                    
                    const y = firstCard.getBoundingClientRect().top + (window.scrollY || window.pageYOffset) + yOffset;
                    window.scrollTo({ top: y, behavior: 'smooth' });
                }
            }, 300);
        }

        // 3. Défilement et surbrillance d'un événement spécifique
        const targetEventId = urlParams.get('event_id');
        if (targetEventId) {
            setTimeout(() => {
                // S'assurer que les filtres de la page ne masquent pas cet événement
                const resetBtn = document.getElementById('reset-dashboard-filters');
                if (resetBtn) {
                    resetBtn.click();
                }
                
                const targetCard = document.querySelector(`#event-grid > div[data-manifestation-id="${targetEventId}"]`);
                if (targetCard) {
                    targetCard.style.display = ''; // S'assurer qu'elle est visible
                    
                    const headerHeight = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--header-h')) || 76;
                    const dateSlider = document.getElementById('date-slider');
                    const sliderHeight = dateSlider ? 85 : 0;
                    const yOffset = -(headerHeight + sliderHeight - 15);
                    
                    const y = targetCard.getBoundingClientRect().top + (window.scrollY || window.pageYOffset) + yOffset;
                    window.scrollTo({ top: y, behavior: 'smooth' });
                    
                    // Appliquer une animation/surbrillance temporaire
                    targetCard.classList.add('ring-4', 'ring-indigo-600/40', 'border-indigo-500', 'shadow-lg');
                    
                    setTimeout(() => {
                        targetCard.classList.remove('ring-4', 'ring-indigo-600/40');
                    }, 4000);
                }
            }, 500);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }

    // GESTION DU SLIDER DE DATES HORIZONTAL
    const frenchDays = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
    let isSliderScrolling = false;
    let sliderScrollTimeout;

    // Fonctions d'aide pour gérer les dates en local (évite les décalages de fuseau horaire)
    function parseLocalDate(dateStr) {
        if (!dateStr) return new Date();
        const parts = dateStr.split('-');
        return new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
    }

    function formatLocalDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    const observerOptions = {
        root: null,
        threshold: [0, 0.05, 0.1, 0.2, 0.5, 0.8, 1.0]
    };

    const scrollObserver = new IntersectionObserver((entries) => {
        if (isSliderScrolling) return;

        // On cherche la carte visible la plus proche du haut de l'écran (sous le header fixe)
        const visibleCards = Array.from(document.querySelectorAll('#event-grid > div:not([style*="display: none"])'));
        const headerHeight = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--header-h')) || 76;
        const sliderHeight = 85; // hauteur approximative du slider de dates
        const offsetLimit = headerHeight + sliderHeight + 20;

        const intersecting = visibleCards.filter(card => {
            const rect = card.getBoundingClientRect();
            // La carte doit avoir dépassé le bas du slider mais ne doit pas être sortie par le bas de l'écran
            return rect.bottom > offsetLimit && rect.top < window.innerHeight;
        });

        if (intersecting.length > 0) {
            const topCard = intersecting[0];
            const dateStr = topCard.dataset.eventDate;
            highlightSliderDate(dateStr);
        }
    }, observerOptions);

    function buildDateSlider() {
        const slider = document.getElementById('date-slider');
        if (!slider) return;
        slider.innerHTML = '';

        const cards = document.querySelectorAll('#event-grid > div:not([style*="display: none"])');
        const dates = [];
        cards.forEach(card => {
            const dateStr = card.dataset.eventDate;
            if (dateStr && !dates.includes(dateStr)) {
                dates.push(dateStr);
            }
        });

        if (dates.length === 0) {
            slider.innerHTML = '<span class="text-xs text-slate-400 italic py-2">Aucune date disponible</span>';
            return;
        }

        dates.sort();

        // Récupérer la date minimale et maximale pour créer une frise chronologique
        const minDate = parseLocalDate(dates[0]);
        const maxDate = parseLocalDate(dates[dates.length - 1]);
        const diffTime = Math.abs(maxDate - minDate);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        // Limiter l'affichage à 90 jours consécutifs pour la lisibilité
        const totalSliderDays = Math.min(diffDays + 1, 90);

        for (let i = 0; i < totalSliderDays; i++) {
            const d = new Date(minDate);
            d.setDate(minDate.getDate() + i);
            const dateStr = formatLocalDate(d);

            const hasEvents = document.querySelectorAll(`#event-grid > div[data-event-date="${dateStr}"]:not([style*="display: none"])`).length > 0;
            const dayNum = d.getDate();
            const dayName = frenchDays[d.getDay()];

            const dayItem = document.createElement('div');
            dayItem.dataset.date = dateStr;

            if (hasEvents) {
                dayItem.className = 'flex flex-col items-center justify-center min-w-[3.25rem] py-2.5 rounded-2xl text-slate-800 font-extrabold hover:bg-slate-50 transition-all cursor-pointer select-none';
                dayItem.innerHTML = `
                    <span class="text-[9px] text-slate-400 font-bold uppercase tracking-wider">${dayName}</span>
                    <span class="text-sm font-black mt-0.5">${dayNum}</span>
                    <span class="w-1.5 h-1.5 rounded-full bg-[var(--primary)] mt-1.5"></span>
                `;
                dayItem.addEventListener('click', () => {
                    scrollToDateCard(dateStr);
                });
            } else {
                dayItem.className = 'flex flex-col items-center justify-center min-w-[3.25rem] py-2.5 rounded-2xl text-slate-300 font-medium opacity-50 cursor-not-allowed select-none';
                dayItem.innerHTML = `
                    <span class="text-[9px] uppercase tracking-wider">${dayName}</span>
                    <span class="text-sm mt-0.5">${dayNum}</span>
                    <span class="w-1.5 h-1.5 rounded-full bg-transparent mt-1.5"></span>
                `;
            }
            slider.appendChild(dayItem);
        }

        // Observer les cartes pour mettre à jour la sélection du slider lors du défilement
        scrollObserver.disconnect();
        cards.forEach(card => scrollObserver.observe(card));

        // Sélectionner par défaut la première date
        if (dates[0]) {
            highlightSliderDate(dates[0]);
        }
    }

    function highlightSliderDate(dateStr) {
        const slider = document.getElementById('date-slider');
        if (!slider) return;

        const items = slider.querySelectorAll('div[data-date]');
        items.forEach(item => {
            const itemDate = item.dataset.date;
            const isClickable = !item.classList.contains('cursor-not-allowed');

            if (itemDate === dateStr && isClickable) {
                item.className = 'flex flex-col items-center justify-center min-w-[3.25rem] py-2.5 rounded-2xl bg-[var(--primary)] text-white shadow-md font-bold transition-all transform scale-105 select-none';
                const parsedDate = parseLocalDate(itemDate);
                item.innerHTML = `
                    <span class="text-[9px] text-white/80 font-bold uppercase tracking-wider">${frenchDays[parsedDate.getDay()]}</span>
                    <span class="text-sm font-black mt-0.5">${parsedDate.getDate()}</span>
                    <span class="w-1.5 h-1.5 rounded-full bg-white mt-1.5"></span>
                `;
                // Centrer l'élément dans le conteneur scrollable
                item.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
            } else if (isClickable) {
                item.className = 'flex flex-col items-center justify-center min-w-[3.25rem] py-2.5 rounded-2xl text-slate-800 font-extrabold hover:bg-slate-50 transition-all cursor-pointer select-none';
                const parsedDate = parseLocalDate(itemDate);
                item.innerHTML = `
                    <span class="text-[9px] text-slate-400 font-bold uppercase tracking-wider">${frenchDays[parsedDate.getDay()]}</span>
                    <span class="text-sm font-black mt-0.5">${parsedDate.getDate()}</span>
                    <span class="w-1.5 h-1.5 rounded-full bg-[var(--primary)] mt-1.5"></span>
                `;
            }
        });
    }

    function scrollToDateCard(dateStr) {
        const targetCard = document.querySelector(`#event-grid > div[data-event-date="${dateStr}"]:not([style*="display: none"])`);
        if (targetCard) {
            isSliderScrolling = true;
            clearTimeout(sliderScrollTimeout);

            // Scroll offset pour atterrir juste sous le slider et header fixes
            const headerHeight = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--header-h')) || 76;
            const sliderHeight = 85;
            const yOffset = -(headerHeight + sliderHeight - 10);
            
            const y = targetCard.getBoundingClientRect().top + window.pageYOffset + yOffset;
            window.scrollTo({ top: y, behavior: 'smooth' });
            
            highlightSliderDate(dateStr);

            sliderScrollTimeout = setTimeout(() => {
                isSliderScrolling = false;
            }, 800);
        }
    }

    // ==========================================
    // 5. FILTRES DU TABLEAU DE BORD (Dashboard)
    // ==========================================
    let activeFilterType = null;
    let activeFilterValue = null;

    function getWeekRange(offsetWeeks = 0) {
        const today = new Date();
        let day = today.getDay();
        if (day === 0) day = 7; // Sunday is day 7
        
        const monday = new Date(today);
        monday.setDate(today.getDate() - (day - 1) + (offsetWeeks * 7));
        monday.setHours(0, 0, 0, 0);
        
        const sunday = new Date(monday);
        sunday.setDate(monday.getDate() + 6);
        sunday.setHours(23, 59, 59, 999);
        
        return { start: monday, end: sunday };
    }

    function applyDashboardFilters() {
        const cards = document.querySelectorAll('#event-grid > div[data-manifestation-id]');
        const labelSpan = document.getElementById('active-filter-label');
        const resetBtn = document.getElementById('reset-dashboard-filters');
        
        // Nettoyer les surbrillances
        document.querySelectorAll('[data-kpi-filter]').forEach(el => {
            el.classList.remove('ring-2', 'ring-indigo-600', 'bg-indigo-50');
        });
        document.querySelectorAll('[data-type-filter]').forEach(el => {
            el.classList.remove('bg-indigo-50/80', 'font-bold', 'text-indigo-900');
        });
        document.querySelectorAll('[data-lieu-filter]').forEach(el => {
            el.classList.remove('bg-indigo-50/80', 'font-bold', 'text-indigo-900');
        });
        
        if (!activeFilterType) {
            cards.forEach(card => card.style.display = '');
            if (labelSpan) labelSpan.textContent = '';
            if (resetBtn) resetBtn.classList.add('hidden');
            
            // Retirer le placeholder si présent
            const placeholder = document.getElementById('no-filter-events-placeholder');
            if (placeholder) placeholder.remove();
            
            return;
        }
        
        if (resetBtn) resetBtn.classList.remove('hidden');
        
        // Appliquer la surbrillance sur les éléments actifs
        if (activeFilterType === 'kpi') {
            const el = document.querySelector(`[data-kpi-filter="${activeFilterValue}"]`);
            if (el) el.classList.add('ring-2', 'ring-indigo-600', 'bg-indigo-50');
            
            let label = '';
            if (activeFilterValue === 'this-week') label = 'Cette semaine';
            else if (activeFilterValue === 'next-week') label = 'Semaine prochaine';
            else if (activeFilterValue === 'action-required') label = 'À répondre';
            if (labelSpan) labelSpan.textContent = ` (Filtre : ${label})`;
            
            const thisWeekRange = getWeekRange(0);
            const nextWeekRange = getWeekRange(1);
            
            cards.forEach(card => {
                const dateStr = card.dataset.eventDate;
                const eventDate = new Date(dateStr + 'T00:00:00');
                const status = card.dataset.currentStatus || '.';
                
                let matches = false;
                if (activeFilterValue === 'this-week') {
                    matches = eventDate >= thisWeekRange.start && eventDate <= thisWeekRange.end;
                } else if (activeFilterValue === 'next-week') {
                    matches = eventDate >= nextWeekRange.start && eventDate <= nextWeekRange.end;
                } else if (activeFilterValue === 'action-required') {
                    matches = !status || status === '.' || status === 'Ne sait pas encore';
                }
                card.style.display = matches ? '' : 'none';
            });
        } else if (activeFilterType === 'type') {
            document.querySelectorAll(`[data-type-filter="${activeFilterValue}"]`).forEach(el => {
                el.classList.add('bg-indigo-50/80', 'font-bold', 'text-indigo-900');
            });
            
            let label = activeFilterValue.charAt(0).toUpperCase() + activeFilterValue.slice(1);
            if (activeFilterValue === 'entrainement') label = 'Entraînements';
            else if (activeFilterValue === 'match') label = 'Matchs';
            else if (activeFilterValue === 'tournois') label = 'Tournois / Plateaux';
            else if (activeFilterValue === 'forum') label = 'Forums';
            else if (activeFilterValue === 'others') label = 'Autres';
            if (labelSpan) labelSpan.textContent = ` (Filtre : ${label})`;
            
            cards.forEach(card => {
                const filterVal = card.dataset.eventFilter || '';
                let matches = false;
                if (activeFilterValue === 'match') {
                    matches = filterVal === 'match';
                } else if (activeFilterValue === 'entrainement') {
                    matches = filterVal.includes('entrain') || filterVal.includes('entraîn');
                } else if (activeFilterValue === 'tournois') {
                    matches = filterVal.includes('tournoi') || filterVal.includes('plateau');
                } else if (activeFilterValue === 'forum') {
                    matches = filterVal === 'forum';
                } else if (activeFilterValue === 'others') {
                    matches = filterVal !== 'match' && 
                              filterVal !== 'forum' && 
                              !filterVal.includes('entrain') && 
                              !filterVal.includes('entraîn') && 
                              !filterVal.includes('tournoi') && 
                              !filterVal.includes('plateau');
                } else {
                    matches = filterVal.includes(activeFilterValue);
                }
                card.style.display = matches ? '' : 'none';
            });
        } else if (activeFilterType === 'lieu') {
            document.querySelectorAll(`[data-lieu-filter="${activeFilterValue}"]`).forEach(el => {
                el.classList.add('bg-indigo-50/80', 'font-bold', 'text-indigo-900');
            });
            
            if (labelSpan) labelSpan.textContent = ` (Filtre : ${activeFilterValue})`;
            
            cards.forEach(card => {
                const cardLieu = (card.dataset.eventLocation || '').trim().toLowerCase();
                const filterLieu = activeFilterValue.trim().toLowerCase();
                card.style.display = cardLieu === filterLieu ? '' : 'none';
            });
        }
        
        // Gérer le placeholder si aucun résultat
        const visibleCards = Array.from(cards).filter(c => c.style.display !== 'none');
        let placeholder = document.getElementById('no-filter-events-placeholder');
        if (visibleCards.length === 0) {
            if (!placeholder) {
                placeholder = document.createElement('div');
                placeholder.id = 'no-filter-events-placeholder';
                placeholder.className = 'p-8 text-center bg-white rounded-2xl border border-slate-100 text-slate-400 text-sm w-full';
                placeholder.textContent = 'Aucun événement ne correspond à ce filtre.';
                const grid = document.getElementById('event-grid');
                if (grid) grid.appendChild(placeholder);
            }
        } else {
            if (placeholder) placeholder.remove();
        }
    }

    function resetBulkSelectsWithoutTriggering() {
        const typeSelect = document.getElementById('bulk-type-select');
        const subtypeSelect = document.getElementById('bulk-subtype-select');
        const locationSelect = document.getElementById('bulk-location-select');
        const kindSelect = document.getElementById('bulk-kind-select');
        if (typeSelect) {
            typeSelect.value = '';
            subtypeSelect.value = '';
            locationSelect.value = '';
            kindSelect.value = '';
            
            // Re-populate selects to default unfiltered states
            const grid = document.getElementById('event-grid');
            if (grid) {
                const cards = Array.from(grid.querySelectorAll('[data-manifestation-id]'));
                const events = cards.map(card => ({
                    type: card.dataset.eventTypeRaw || '',
                    subtype: card.dataset.eventSubtypeRaw || '',
                    location: card.dataset.eventLocationRaw || '',
                    kind: card.dataset.eventKindRaw || ''
                }));
                const uniqueTypes = [...new Set(events.map(e => e.type))].filter(Boolean).sort();
                const uniqueSubtypes = [...new Set(events.map(e => e.subtype))].filter(Boolean).sort();
                const uniqueLocations = [...new Set(events.map(e => e.location))].filter(Boolean).sort();
                const uniqueKinds = [...new Set(events.map(e => e.kind))].filter(Boolean).sort();
                
                typeSelect.innerHTML = `<option value="">Tous les types</option>`;
                uniqueTypes.forEach(opt => {
                    const option = document.createElement('option');
                    option.value = opt;
                    option.textContent = opt;
                    typeSelect.appendChild(option);
                });
                subtypeSelect.innerHTML = `<option value="">Tous les sous-types</option>`;
                uniqueSubtypes.forEach(opt => {
                    const option = document.createElement('option');
                    option.value = opt;
                    option.textContent = opt;
                    subtypeSelect.appendChild(option);
                });
                locationSelect.innerHTML = `<option value="">Tous les lieux</option>`;
                uniqueLocations.forEach(opt => {
                    const option = document.createElement('option');
                    option.value = opt;
                    option.textContent = opt;
                    locationSelect.appendChild(option);
                });
                kindSelect.innerHTML = `<option value="">Tous (Dispo & Présences)</option>`;
                uniqueKinds.forEach(opt => {
                    const option = document.createElement('option');
                    option.value = opt;
                    option.textContent = opt;
                    kindSelect.appendChild(option);
                });
            }
        }
    }

    function initDashboardFilters() {
        // Enregistrer les écouteurs de clics sur les KPIs
        document.querySelectorAll('[data-kpi-filter]').forEach(el => {
            el.addEventListener('click', () => {
                resetBulkSelectsWithoutTriggering();
                const val = el.dataset.kpiFilter;
                if (activeFilterType === 'kpi' && activeFilterValue === val) {
                    activeFilterType = null;
                    activeFilterValue = null;
                } else {
                    activeFilterType = 'kpi';
                    activeFilterValue = val;
                }
                applyDashboardFilters();
            });
        });

        // Enregistrer les écouteurs sur les types d'événements
        document.querySelectorAll('[data-type-filter]').forEach(el => {
            el.addEventListener('click', () => {
                resetBulkSelectsWithoutTriggering();
                const val = el.dataset.typeFilter;
                if (activeFilterType === 'type' && activeFilterValue === val) {
                    activeFilterType = null;
                    activeFilterValue = null;
                } else {
                    activeFilterType = 'type';
                    activeFilterValue = val;
                }
                applyDashboardFilters();
            });
        });

        // Enregistrer les écouteurs sur les lieux
        document.querySelectorAll('[data-lieu-filter]').forEach(el => {
            el.addEventListener('click', () => {
                resetBulkSelectsWithoutTriggering();
                const val = el.dataset.lieuFilter;
                if (activeFilterType === 'lieu' && activeFilterValue === val) {
                    activeFilterType = null;
                    activeFilterValue = null;
                } else {
                    activeFilterType = 'lieu';
                    activeFilterValue = val;
                }
                applyDashboardFilters();
            });
        });

        // Bouton réinitialiser
        document.getElementById('reset-dashboard-filters')?.addEventListener('click', () => {
            resetBulkSelectsWithoutTriggering();
            activeFilterType = null;
            activeFilterValue = null;
            applyDashboardFilters();
        });
    }

})();