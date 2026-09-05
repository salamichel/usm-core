(function () {
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

        function updateBulkFilters() {
            const events = cards.map(card => ({
                type: card.dataset.eventTypeRaw || '',
                subtype: card.dataset.eventSubtypeRaw || '',
                location: card.dataset.eventLocationRaw || '',
                kind: card.dataset.eventKindRaw || ''
            }));

            // Sélections actuelles
            let selectedType = typeSelect.value;
            let selectedSubtype = subtypeSelect.value;
            let selectedLocation = locationSelect.value;
            let selectedKind = kindSelect.value;

            // Filtrer les options dynamiques par cascade
            const eventsForType = selectedType ? events.filter(e => e.type === selectedType) : events;

            const validSubtypes = [...new Set(eventsForType.map(e => e.subtype))].filter(Boolean);
            if (selectedSubtype && !validSubtypes.includes(selectedSubtype)) {
                selectedSubtype = '';
                subtypeSelect.value = '';
            }

            const eventsForSubtype = selectedSubtype ? eventsForType.filter(e => e.subtype === selectedSubtype) : eventsForType;

            const validLocations = [...new Set(eventsForSubtype.map(e => e.location))].filter(Boolean);
            if (selectedLocation && !validLocations.includes(selectedLocation)) {
                selectedLocation = '';
                locationSelect.value = '';
            }

            const eventsForLocation = selectedLocation ? eventsForSubtype.filter(e => e.location === selectedLocation) : eventsForSubtype;

            const validKinds = [...new Set(eventsForLocation.map(e => e.kind))].filter(Boolean);
            if (selectedKind && !validKinds.includes(selectedKind)) {
                selectedKind = '';
                kindSelect.value = '';
            }

            // Mettre à jour les options des sélecteurs
            const uniqueTypes = [...new Set(events.map(e => e.type))].filter(Boolean).sort();
            updateSelectOptions(typeSelect, uniqueTypes, selectedType, 'Tous les types');

            const uniqueSubtypes = validSubtypes.sort();
            updateSelectOptions(subtypeSelect, uniqueSubtypes, selectedSubtype, 'Tous les sous-types');

            const uniqueLocations = validLocations.sort();
            updateSelectOptions(locationSelect, uniqueLocations, selectedLocation, 'Tous les lieux');

            const uniqueKinds = validKinds.sort();
            updateSelectOptions(kindSelect, uniqueKinds, selectedKind, 'Tous (Dispo & Présences)');

            // Appliquer le filtrage unifié en direct
            if (typeof applyUnifiedFilters === 'function') {
                applyUnifiedFilters();
            }
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

        // Gestionnaire d'application des actions de masse sur les cartes VISIBLES
        applyBtn.addEventListener('click', () => {
            const statusChoice = document.getElementById('bulk-status-select').value;
            if (!statusChoice) return;

            // Appliquer uniquement aux cartes actuellement visibles
            const visibleCards = cards.filter(c => c.style.display !== 'none');
            console.log("applyBtn click: application du statut groupé sur cartes visibles...", { statusChoice, count: visibleCards.length });

            visibleCards.forEach(card => {
                const isMatch = (card.dataset.eventFilter === 'match');
                let targetStatus = statusChoice;
                if (statusChoice === 'Disponible') {
                    targetStatus = isMatch ? 'Disponible' : 'Présent(e)';
                } else if (statusChoice === 'Indisponible') {
                    targetStatus = isMatch ? 'Indisponible' : 'Absent(e)';
                }

                // Cliquer le bouton correspondant ou changer le select
                const btn = [...card.querySelectorAll('.status-btn')].find(b => b.dataset.status === targetStatus);
                if (btn) {
                    btn.click();
                } else {
                    const select = card.querySelector('.status-select');
                    if (select) {
                        const hasOption = [...select.options].some(opt => opt.value === targetStatus);
                        if (hasOption) {
                            select.value = targetStatus;
                            select.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    }
                }
            });
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
    const helperCat = (s) => {
        if (!s || s === '.') return 'empty';
        const map = {
            'Sélectionné(e)': 'selected', 'En réserve': 'selected',
            'Disponible': 'available', 'Joker': 'available',
            'Disponible si nécessaire': 'available_if_needed', 'Disponible si n': 'available_if_needed',
            'Indisponible': 'unavailable', 'Absent': 'absent', 'Absent(e)': 'absent', 'Non': 'absent',
            'Présent': 'present', 'Présent(e)': 'present', 'Présent(e) à 2': 'present', 'Présent(e) à 3': 'present', 'Présent(e) à 4': 'present', 'Présent(e) à 5': 'present',
            'Ne sait pas': 'unknown', '?': 'unknown', 'Ne sait pas encore': 'unknown'
        };
        return map[s] || 'unknown';
    };
    const submitStatusUpdate = (element, manifestationId, newStatus, oldStatus, card) => {
        if (newStatus === oldStatus) return;

        element.classList.add('opacity-50', 'cursor-wait');

        fetch('/api/member/participations/upsert', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ manifestation_id: manifestationId, status: newStatus })
        })
            .then(r => r.json())
            .then(data => {
                element.classList.remove('opacity-50', 'cursor-wait');

                if (data.ok) {
                    card.dataset.currentStatus = newStatus;

                    // Mettre à jour l'apparence active/inactive des boutons et select
                    card.querySelectorAll('.status-btn, .status-select').forEach(el => {
                        if (el.tagName === 'SELECT') {
                            const isSelectActive = helperCat(newStatus) === 'present';
                            if (isSelectActive) {
                                el.value = newStatus;
                                el.className = `status-select flex-1 min-w-[90px] px-3 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer focus:ring-2 focus:ring-[var(--primary)]/20 focus:outline-none border bg-emerald-600 text-white border-emerald-600`;
                            } else {
                                el.value = '';
                                el.className = `status-select flex-1 min-w-[90px] px-3 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer focus:ring-2 focus:ring-[var(--primary)]/20 focus:outline-none border bg-emerald-50 text-emerald-600 hover:bg-emerald-100 border-emerald-100`;
                            }
                        } else {
                            const status = el.dataset.status;
                            const isActive = status === newStatus;
                            const cat = helperCat(status);

                            if (cat === 'available' || cat === 'present') {
                                el.className = `status-btn flex-1 min-w-[80px] px-3 py-2 rounded-xl text-xs font-bold transition-all active:scale-95 flex items-center justify-center gap-1 ${isActive ? 'bg-emerald-600 text-white' : 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100 border border-emerald-100'
                                    }`;
                            } else if (cat === 'available_if_needed') {
                                el.className = `status-btn flex-1 min-w-[80px] px-3 py-2 rounded-xl text-xs font-bold transition-all active:scale-95 flex items-center justify-center gap-1 ${isActive ? 'bg-amber-500 text-white' : 'bg-amber-50 text-amber-600 hover:bg-amber-100 border border-amber-100'
                                    }`;
                            } else if (cat === 'unavailable' || cat === 'absent') {
                                el.className = `status-btn flex-1 min-w-[80px] px-3 py-2 rounded-xl text-xs font-bold transition-all active:scale-95 flex items-center justify-center gap-1 ${isActive ? 'bg-rose-600 text-white' : 'bg-rose-50 text-rose-600 hover:bg-rose-100 border border-rose-100'
                                    }`;
                            } else if (cat === 'unknown') {
                                el.className = `status-btn flex-1 min-w-[80px] px-3 py-2 rounded-xl text-xs font-bold transition-all active:scale-95 flex items-center justify-center gap-1 ${isActive ? 'bg-slate-600 text-white' : 'bg-slate-50 text-slate-500 hover:bg-slate-100 border border-slate-100'
                                    }`;
                            } else if (cat === 'empty') {
                                const isResetActive = !newStatus || newStatus === '.';
                                el.className = `status-btn px-2.5 py-2 rounded-xl text-xs font-medium transition-all active:scale-95 flex items-center justify-center gap-1 ${isResetActive ? 'bg-slate-400 text-white' : 'bg-slate-100 text-slate-400 hover:bg-slate-200'
                                    }`;
                            }
                        }
                    });

                    // Changer le badge textuel de statut
                    const badge = card.querySelector(`#status-${manifestationId}`);
                    if (badge) {
                        let icon = '', textClass = '';
                        const newCat = helperCat(newStatus);

                        if (newCat === 'available' || newCat === 'present') {
                            icon = '✓'; textClass = 'text-emerald-600';
                        } else if (newCat === 'available_if_needed') {
                            icon = '◐'; textClass = 'text-amber-500';
                        } else if (newCat === 'unavailable' || newCat === 'absent') {
                            icon = '✗'; textClass = 'text-rose-500';
                        } else if (newCat === 'unknown') {
                            icon = '?'; textClass = 'text-slate-600';
                        } else {
                            icon = '?'; textClass = 'text-slate-400';
                        }

                        badge.className = `inline-flex items-center text-xs font-black mt-0.5 ${textClass}`;
                        badge.innerHTML = newStatus === '.'
                            ? `Non renseigné`
                            : `<span class="mr-1 text-xs">${icon}</span> ${newStatus}`;
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
                            const totalCount = (data.counts['present'] || 0) + (data.counts['available'] || 0) + (data.counts['available_if_needed'] || 0) + (data.counts['selected'] || 0);
                            const pct = (totalCount >= 6) ? 100 : (totalCount / 6 * 100);
                            progressBar.style.width = pct + '%';

                            if (totalCount >= 6) {
                                progressBar.className = 'progress-bar h-full rounded-full transition-all duration-500 bg-gradient-to-r from-emerald-400 to-teal-500';
                                progressLabel.innerHTML = '<span class="text-emerald-600 flex items-center gap-1">✓ Équipe complète (' + totalCount + ')</span>';
                            } else {
                                progressBar.className = 'progress-bar h-full rounded-full transition-all duration-500 bg-gradient-to-r from-orange-400 to-amber-500';
                                progressLabel.innerHTML = '<span class="text-orange-500">⚠ Sous-effectif (' + totalCount + '/6)</span>';
                            }
                        } else {
                            const totalPresents = data.counts['present'] || 0;
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
                            } catch (e) { }

                            players = players.filter(p => p.id !== currentUserId);

                            const key = bubble.dataset.statusKey;
                            const newCat = helperCat(newStatus);
                            if (key === newCat) {
                                let compCount = 0;
                                if (newStatus && (newStatus.indexOf('Présent') !== -1 || newStatus.indexOf('present') !== -1)) {
                                    const match = newStatus.match(/(\d+)/);
                                    if (match) {
                                        compCount = Math.max(0, parseInt(match[1]) - 1);
                                    }
                                }
                                players.push({ id: currentUserId, nom: currentUserName, companion_count: compCount });
                            }

                            bubble.dataset.players = JSON.stringify(players);
                        });

                        rebuildAvatarStack(card);
                    }

                    element.classList.add('animate-pulse');
                    setTimeout(() => element.classList.remove('animate-pulse'), 500);

                } else {
                    alert("Erreur lors de l'enregistrement : " + (data.message || "Inconnue"));
                    if (element.tagName === 'SELECT') {
                        element.value = oldStatus;
                    }
                }
            })
            .catch(err => {
                console.error('Erreur AJAX:', err);
                element.classList.remove('opacity-50', 'cursor-wait');
                alert("Erreur de communication avec le serveur.");
                if (element.tagName === 'SELECT') {
                    element.value = oldStatus;
                }
            });
    };

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.status-btn');
        if (!btn) return;

        e.preventDefault();
        e.stopPropagation();

        const card = btn.closest('[data-manifestation-id]');
        if (!card) return;

        const manifestationId = parseInt(card.dataset.manifestationId);
        const newStatus = btn.dataset.status;
        const oldStatus = card.dataset.currentStatus || '.';

        submitStatusUpdate(btn, manifestationId, newStatus, oldStatus, card);
    });

    // Navigation globale au clic sur la carte (sans intercepter les boutons, selects, modales, etc.)
    document.addEventListener('click', function (e) {
        // Ignorer si le clic provient d'un élément interactif
        if (e.target.closest('.status-btn, .status-select, .player-list-trigger, a, button, select, input, label, textarea')) {
            return;
        }

        const card = e.target.closest('[data-event-url]');
        if (card && card.dataset.eventUrl) {
            window.location.href = card.dataset.eventUrl;
        }
    });

    document.addEventListener('change', function (e) {
        const select = e.target.closest('.status-select');
        if (!select) return;

        const card = select.closest('[data-manifestation-id]');
        if (!card) return;

        const manifestationId = parseInt(card.dataset.manifestationId);
        const newStatus = select.value;
        const oldStatus = card.dataset.currentStatus || '.';

        submitStatusUpdate(select, manifestationId, newStatus, oldStatus, card);
    });

    // Reconstruire dynamiquement les avatars empilés d'une carte
    function rebuildAvatarStack(card) {
        const isMatch = card.dataset.eventFilter === 'match';
        let activePlayers = [];

        if (isMatch) {
            const dispBubble = card.querySelector('[data-status-key="available"]');
            const sibBubble = card.querySelector('[data-status-key="available_if_needed"]');
            const selBubble = card.querySelector('[data-status-key="selected"]');

            const dispPlayers = dispBubble ? JSON.parse(dispBubble.dataset.players || '[]') : [];
            const sibPlayers = sibBubble ? JSON.parse(sibBubble.dataset.players || '[]') : [];
            const selPlayers = selBubble ? JSON.parse(selBubble.dataset.players || '[]') : [];
            activePlayers = [...dispPlayers, ...sibPlayers, ...selPlayers];
        } else {
            const presBubble = card.querySelector('[data-status-key="present"]');
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
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });
    }

    // Écouteur global pour ouvrir la modale
    document.addEventListener('click', function (e) {
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
            const playersArray = JSON.parse(playersJson || '[]');
            names = playersArray.map(p => {
                if (p.companion_count && p.companion_count > 0) {
                    return `${p.nom} (à ${p.companion_count + 1})`;
                }
                return p.nom;
            });
        } catch (err) {
            console.error("Erreur de parsing des joueurs", err);
        }

        openModal(title, names, colorClass);
    });

    // ==========================================
    // 4. INTERACTIVITÉ DU SLIDER & RECHERCHE
    // ==========================================
    function initAll() {
        console.log("initAll: Initialisation globale des composants...");
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
            searchInput.addEventListener('input', () => {
                if (typeof applyUnifiedFilters === 'function') {
                    applyUnifiedFilters();
                } else {
                    const val = searchInput.value.toLowerCase().trim();
                    const cards = document.querySelectorAll('#event-grid > div');
                    cards.forEach(card => {
                        const title = card.querySelector('h3')?.textContent.toLowerCase() || '';
                        const location = card.querySelector('.text-slate-500')?.textContent.toLowerCase() || '';
                        const matches = title.includes(val) || location.includes(val);
                        card.style.display = matches ? '' : 'none';
                    });
                    buildDateSlider();
                }
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
    // 5. FILTRES DU TABLEAU DE BORD (Dashboard - Unifiés & Cumulatifs)
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

    function normalizeString(str) {
        return (str || '')
            .toLowerCase()
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .trim();
    }

    function applyUnifiedFilters() {
        const cards = document.querySelectorAll('#event-grid > div[data-manifestation-id]');
        if (cards.length === 0) return;

        const labelSpan = document.getElementById('active-filter-label');
        const resetBtn = document.getElementById('reset-dashboard-filters');
        const targetCountBadge = document.getElementById('bulk-target-count');

        const typeSelect = document.getElementById('bulk-type-select');
        const subtypeSelect = document.getElementById('bulk-subtype-select');
        const locationSelect = document.getElementById('bulk-location-select');
        const kindSelect = document.getElementById('bulk-kind-select');
        const searchInput = document.getElementById('agenda-search');

        const typeVal = typeSelect ? typeSelect.value : '';
        const subtypeVal = subtypeSelect ? subtypeSelect.value : '';
        const locationVal = locationSelect ? locationSelect.value : '';
        const kindVal = kindSelect ? kindSelect.value : '';
        const searchVal = searchInput ? searchInput.value.toLowerCase().trim() : '';

        // Nettoyer surbrillances UI
        document.querySelectorAll('[data-kpi-filter]').forEach(el => {
            el.classList.remove('ring-2', 'ring-indigo-600', 'bg-indigo-50');
        });
        document.querySelectorAll('[data-type-filter]').forEach(el => {
            el.classList.remove('bg-indigo-50/80', 'font-bold', 'text-indigo-900');
        });
        document.querySelectorAll('[data-lieu-filter]').forEach(el => {
            el.classList.remove('bg-indigo-50/80', 'font-bold', 'text-indigo-900');
        });

        // Surbrillance active
        const labels = [];
        if (activeFilterType === 'kpi' && activeFilterValue) {
            const el = document.querySelector(`[data-kpi-filter="${activeFilterValue}"]`);
            if (el) el.classList.add('ring-2', 'ring-indigo-600', 'bg-indigo-50');

            if (activeFilterValue === 'this-week') labels.push('Cette semaine');
            else if (activeFilterValue === 'next-week') labels.push('Semaine prochaine');
            else if (activeFilterValue === 'action-required') labels.push('À répondre');
        } else if (activeFilterType === 'type' && activeFilterValue) {
            const activeFilterNorm = normalizeString(activeFilterValue);
            document.querySelectorAll('[data-type-filter]').forEach(el => {
                const filterNorm = normalizeString(el.dataset.typeFilter || '');
                const isMatch = (filterNorm === activeFilterNorm) || 
                                (filterNorm === 'tournois' && activeFilterNorm === 'tournoi') || 
                                (filterNorm === 'tournoi' && activeFilterNorm === 'tournois');
                if (isMatch) el.classList.add('bg-indigo-50/80', 'font-bold', 'text-indigo-900');
            });
            labels.push(activeFilterValue);
        } else if (activeFilterType === 'lieu' && activeFilterValue) {
            document.querySelectorAll(`[data-lieu-filter="${activeFilterValue}"]`).forEach(el => {
                el.classList.add('bg-indigo-50/80', 'font-bold', 'text-indigo-900');
            });
            labels.push(activeFilterValue);
        }

        if (typeVal) labels.push(typeVal);
        if (subtypeVal) labels.push(subtypeVal);
        if (locationVal) labels.push(locationVal);
        if (kindVal) labels.push(kindVal);

        const hasActiveFilters = Boolean(activeFilterType || typeVal || subtypeVal || locationVal || kindVal || searchVal);
        if (resetBtn) resetBtn.classList.toggle('hidden', !hasActiveFilters);
        if (labelSpan) labelSpan.textContent = labels.length > 0 ? ` (${labels.join(' + ')})` : '';

        const thisWeekRange = getWeekRange(0);
        const nextWeekRange = getWeekRange(1);

        let visibleCount = 0;

        cards.forEach(card => {
            // A. Condition KPI / Section
            let matchesSection = true;
            if (activeFilterType === 'kpi') {
                const dateStr = card.dataset.eventDate;
                const eventDate = new Date(dateStr + 'T00:00:00');
                const status = card.dataset.currentStatus || '.';

                if (activeFilterValue === 'this-week') {
                    matchesSection = eventDate >= thisWeekRange.start && eventDate <= thisWeekRange.end;
                } else if (activeFilterValue === 'next-week') {
                    matchesSection = eventDate >= nextWeekRange.start && eventDate <= nextWeekRange.end;
                } else if (activeFilterValue === 'action-required') {
                    matchesSection = !status || status === '.' || status === 'Ne sait pas encore';
                }
            } else if (activeFilterType === 'type') {
                const cardType = card.dataset.eventTypeRaw || '';
                const normCardType = normalizeString(cardType);
                const normFilterVal = normalizeString(activeFilterValue);
                if (normFilterVal === 'match') {
                    matchesSection = normCardType.includes('match');
                } else if (normFilterVal === 'entrainement') {
                    matchesSection = normCardType.includes('entrain');
                } else if (normFilterVal === 'tournois' || normFilterVal === 'tournoi') {
                    matchesSection = normCardType.includes('tournoi') || normCardType.includes('plateau');
                } else {
                    matchesSection = normCardType === normFilterVal;
                }
            } else if (activeFilterType === 'lieu') {
                const cardLieu = (card.dataset.eventLocation || '').trim().toLowerCase();
                const filterLieu = (activeFilterValue || '').trim().toLowerCase();
                matchesSection = cardLieu === filterLieu;
            }

            if (!matchesSection) {
                card.style.display = 'none';
                return;
            }

            // B. Condition Type dropdown
            const cardType = card.dataset.eventTypeRaw || '';
            if (typeVal && cardType !== typeVal) {
                card.style.display = 'none';
                return;
            }

            // C. Condition Sous-Type dropdown
            const cardSubtype = card.dataset.eventSubtypeRaw || '';
            if (subtypeVal && cardSubtype !== subtypeVal) {
                card.style.display = 'none';
                return;
            }

            // D. Condition Lieu dropdown
            const cardLocation = card.dataset.eventLocationRaw || '';
            if (locationVal && cardLocation.toLowerCase().trim() !== locationVal.toLowerCase().trim()) {
                card.style.display = 'none';
                return;
            }

            // E. Condition Nature dropdown
            const cardKind = card.dataset.eventKindRaw || '';
            if (kindVal && cardKind !== kindVal) {
                card.style.display = 'none';
                return;
            }

            // F. Condition Barre de recherche (si présente)
            if (searchVal) {
                const title = card.querySelector('h3')?.textContent.toLowerCase() || '';
                const loc = card.querySelector('.text-slate-500')?.textContent.toLowerCase() || '';
                if (!title.includes(searchVal) && !loc.includes(searchVal)) {
                    card.style.display = 'none';
                    return;
                }
            }

            // Tout correspond !
            card.style.display = '';
            visibleCount++;
        });

        // Mettre à jour le badge du bouton d'action groupée
        if (targetCountBadge) {
            targetCountBadge.textContent = visibleCount;
        }

        // Gérer le placeholder si aucun résultat
        let placeholder = document.getElementById('no-filter-events-placeholder');
        const grid = document.getElementById('event-grid');
        if (visibleCount === 0) {
            if (!placeholder && grid) {
                placeholder = document.createElement('div');
                placeholder.id = 'no-filter-events-placeholder';
                placeholder.className = 'p-8 text-center bg-white rounded-2xl border border-slate-100 text-slate-400 text-sm w-full';
                placeholder.textContent = 'Aucun événement ne correspond à ces critères cumulés.';
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

    // Alias pour la compatibilité
    window.applyDashboardFilters = applyUnifiedFilters;
    window.applyUnifiedFilters = applyUnifiedFilters;

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

            // Réinitialiser les options de sélection complètes
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
        // Enregistrer les écouteurs de clics sur les KPIs (cumulatif avec les selects)
        document.querySelectorAll('[data-kpi-filter]').forEach(el => {
            el.addEventListener('click', () => {
                const val = el.dataset.kpiFilter;
                if (activeFilterType === 'kpi' && activeFilterValue === val) {
                    activeFilterType = null;
                    activeFilterValue = null;
                } else {
                    activeFilterType = 'kpi';
                    activeFilterValue = val;
                }
                applyUnifiedFilters();
            });
        });

        // Enregistrer les écouteurs sur les types d'événements (statistiques)
        document.querySelectorAll('[data-type-filter]').forEach(el => {
            el.addEventListener('click', () => {
                const val = el.dataset.typeFilter;
                if (activeFilterType === 'type' && activeFilterValue === val) {
                    activeFilterType = null;
                    activeFilterValue = null;
                } else {
                    activeFilterType = 'type';
                    activeFilterValue = val;
                }
                applyUnifiedFilters();
            });
        });

        // Enregistrer les écouteurs sur les lieux
        document.querySelectorAll('[data-lieu-filter]').forEach(el => {
            el.addEventListener('click', () => {
                const val = el.dataset.lieuFilter;
                if (activeFilterType === 'lieu' && activeFilterValue === val) {
                    activeFilterType = null;
                    activeFilterValue = null;
                } else {
                    activeFilterType = 'lieu';
                    activeFilterValue = val;
                }
                applyUnifiedFilters();
            });
        });

        // Bouton réinitialiser (efface tout : KPIs, types, lieux, dropdowns et recherche)
        document.getElementById('reset-dashboard-filters')?.addEventListener('click', () => {
            resetBulkSelectsWithoutTriggering();
            activeFilterType = null;
            activeFilterValue = null;
            const searchInput = document.getElementById('agenda-search');
            if (searchInput) searchInput.value = '';
            applyUnifiedFilters();
        });
    }

})();