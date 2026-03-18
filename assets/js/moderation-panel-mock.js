(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var panel = document.querySelector('.mod-panel');
        if (!panel) {
            return;
        }

        var panelView = panel.getAttribute('data-view') || 'list';
        var viewTabs = Array.prototype.slice.call(document.querySelectorAll('.mod-view-tab'));
        var statusTabs = Array.prototype.slice.call(document.querySelectorAll('.mod-filter-pill'));
        var typeTabs = Array.prototype.slice.call(document.querySelectorAll('.mod-type-pill'));
        var cards = Array.prototype.slice.call(document.querySelectorAll('.mod-entry-card'));
        var activityItems = Array.prototype.slice.call(document.querySelectorAll('.mod-activity-item'));
        var searchInput = document.getElementById('modDashboardSearch');
        var emptyState = document.getElementById('modEmpty');
        var entryList = document.getElementById('modEntryList');
        var moderationView = document.getElementById('modModerationView');
        var journalView = document.getElementById('modJournalView');
        var notifyView = document.getElementById('modNotifyView');
        var PREVIEW_JOURNAL_LIMIT = 15;
        var entryModal = document.getElementById('mod-entry-modal');
        var entryModalCard = entryModal ? entryModal.querySelector('.mod-entry-modal-card') : null;
        var entryModalMedia = document.getElementById('modEntryModalMedia');
        var entryModalTitle = document.getElementById('mod-entry-modal-title');
        var entryModalSubtitle = document.getElementById('modEntryModalSubtitle');
        var entryModalStatus = document.getElementById('modEntryModalStatus');
        var entryModalLocation = null;
        var entryModalId = null;
        var entryModalDataTitle = null;
        var entryModalDataGrid = null;
        var entryModalAuthor = null;
        var entryModalDate = null;
        var entryModalNote = null;
        var entryModalReject = null;
        var entryModalEdit = document.getElementById('modEntryModalEdit');
        var entryModalFlash = document.getElementById('modEntryModalFlash');
        var entryModalView = document.getElementById('modEntryModalView');
        var entryModalCancelEdit = document.getElementById('modEntryModalCancelEdit');
        var entryModalSaveEdit = document.getElementById('modEntryModalSaveEdit');
        var entryModalRejectBtn = document.getElementById('modEntryModalRejectBtn');
        var entryModalApproveBtn = document.getElementById('modEntryModalApproveBtn');
        var closeEntryModalNodes = entryModal ? entryModal.querySelectorAll('[data-mod-close-entry-modal]') : [];
        var activeStatus = panel.getAttribute('data-default-status') || 'pending';
        var activeType = panel.getAttribute('data-default-tab') || '';
        if (!activeType && typeTabs[0]) {
            activeType = typeTabs[0].getAttribute('data-type-filter') || '';
        }
        if (!activeType) {
            activeType = 'grave';
        }
        var activePanel = 'moderation';
        var currentCard = null;
        var entryModalViewTemplate = entryModalView ? entryModalView.innerHTML : '';
        var photoModal = document.getElementById('mod-photo-modal');
        var photoModalImg = document.getElementById('mod-photo-modal-img');
        var photoModalTitle = document.getElementById('mod-photo-modal-title');
        var closePhotoModalNodes = photoModal ? photoModal.querySelectorAll('[data-mod-close-photo-modal]') : [];
        var mapModal = document.getElementById('mod-map-modal');
        var mapModalTitle = document.getElementById('mod-map-modal-title');
        var mapModalText = mapModal ? mapModal.querySelector('.mod-map-modal-text') : null;
        var mapModalActions = mapModal ? mapModal.querySelector('.acm-modal__actions') : null;
        var mapModalTopClose = document.getElementById('mod-map-modal-close-top');
        var mapCanvas = document.getElementById('mod-map-canvas');
        var mapHint = document.getElementById('mod-map-hint');
        var applyMapBtn = document.getElementById('mod-apply-map');
        var closeMapModalNodes = mapModal ? mapModal.querySelectorAll('[data-mod-close-map-modal]') : [];
        var rejectModal = document.getElementById('mod-reject-modal');
        var rejectModalReason = document.getElementById('modRejectReasonInput');
        var rejectModalError = document.getElementById('modRejectModalError');
        var rejectModalConfirm = document.getElementById('modRejectConfirmBtn');
        var rejectReasonOptions = rejectModal
            ? Array.prototype.slice.call(rejectModal.querySelectorAll('input[name="mod-reject-reason-choice"]'))
            : [];
        var rejectOtherWrap = document.getElementById('modRejectOtherWrap');
        var closeRejectModalNodes = rejectModal ? rejectModal.querySelectorAll('[data-mod-close-reject-modal]') : [];
        var activityList = document.getElementById('modActivityList');
        var activityListFull = document.getElementById('modActivityListFull');
        var activityPreviewCount = document.getElementById('modActivityPreviewCount');
        var activityTotalCount = document.getElementById('modActivityTotalCount');
        var map = null;
        var mapMarker = null;
        var mapSelected = null;
        var mapApplyHandler = null;
        var mapViewOnly = false;
        var bodyLockScrollY = 0;
        var bodyLockActive = false;

        if (mapModal && !mapModal.dataset.bound) {
            closeMapModalNodes.forEach(function (node) {
                node.addEventListener('click', closeMapModal);
            });
            if (applyMapBtn) {
                applyMapBtn.addEventListener('click', function () {
                    if (typeof mapApplyHandler === 'function') {
                        mapApplyHandler();
                    }
                });
            }
            mapModal.dataset.bound = '1';
        }

        function cacheEntryModalViewNodes() {
            entryModalLocation = document.getElementById('modEntryModalLocation');
            entryModalId = document.getElementById('modEntryModalId');
            entryModalDataTitle = document.getElementById('modEntryModalDataTitle');
            entryModalDataGrid = document.getElementById('modEntryModalDataGrid');
            entryModalAuthor = document.getElementById('modEntryModalAuthor');
            entryModalDate = document.getElementById('modEntryModalDate');
            entryModalNote = document.getElementById('modEntryModalNote');
            entryModalReject = document.getElementById('modEntryModalReject');
        }

        function restoreEntryModalView() {
            if (!entryModalView) {
                return;
            }
            entryModalView.innerHTML = entryModalViewTemplate;
            cacheEntryModalViewNodes();
        }

        cacheEntryModalViewNodes();

        function openPhotoModal(src, title) {
            if (!photoModal || !photoModalImg || !src) {
                return;
            }
            photoModalImg.src = src;
            if (photoModalTitle) {
                photoModalTitle.textContent = title || 'Перегляд фото';
            }
            photoModal.classList.add('is-open');
            photoModal.setAttribute('aria-hidden', 'false');
            updateBodyLock();
        }

        function closePhotoModal() {
            if (!photoModal || !photoModalImg) {
                return;
            }
            photoModal.classList.remove('is-open');
            photoModal.setAttribute('aria-hidden', 'true');
            photoModalImg.removeAttribute('src');
            updateBodyLock();
        }

        function normalizeText(value) {
            return String(value || '').toLowerCase().replace(/[ʼ']/g, '').replace(/\s+/g, ' ').trim();
        }

        function readStateFromUrl() {
            var params = new URLSearchParams(window.location.search || '');
            var nextStatus = params.get('status');
            var nextType = params.get('tab');
            var nextPanel = params.get('panel');
            var nextQuery = params.get('q');

            if (nextStatus && ['pending', 'approved', 'rejected'].indexOf(nextStatus) !== -1) {
                activeStatus = nextStatus;
            }
            if (nextType && ['grave', 'cemetery'].indexOf(nextType) !== -1) {
                activeType = nextType;
            }
            if (nextPanel && ['moderation', 'journal', 'notify'].indexOf(nextPanel) !== -1) {
                activePanel = nextPanel;
            }
            if (searchInput && typeof nextQuery === 'string') {
                searchInput.value = nextQuery;
            }
        }

        function syncStateToUrl() {
            var params = new URLSearchParams(window.location.search || '');
            params.set('status', activeStatus || 'pending');
            params.set('tab', activeType || 'grave');
            params.set('panel', activePanel || 'moderation');
            if (searchInput && String(searchInput.value || '').trim() !== '') {
                params.set('q', String(searchInput.value || '').trim());
            } else {
                params.delete('q');
            }
            var nextQuery = params.toString();
            var nextUrl = window.location.pathname + (nextQuery ? '?' + nextQuery : '');
            window.history.replaceState(null, '', nextUrl);
        }

        function updateBodyLock() {
            var anyOpen = false;
            if (entryModal && entryModal.classList.contains('is-open')) {
                anyOpen = true;
            }
            if (photoModal && photoModal.classList.contains('is-open')) {
                anyOpen = true;
            }
            if (mapModal && mapModal.classList.contains('is-open')) {
                anyOpen = true;
            }
            if (rejectModal && rejectModal.classList.contains('is-open')) {
                anyOpen = true;
            }
            document.body.classList.toggle('mod-body-locked', anyOpen);
            document.documentElement.classList.toggle('mod-body-locked', anyOpen);
            if (anyOpen && !bodyLockActive) {
                bodyLockScrollY = window.scrollY || window.pageYOffset || 0;
                document.body.style.position = 'fixed';
                document.body.style.top = '-' + bodyLockScrollY + 'px';
                document.body.style.left = '0';
                document.body.style.right = '0';
                document.body.style.width = '100%';
                bodyLockActive = true;
            } else if (!anyOpen && bodyLockActive) {
                document.body.style.position = '';
                document.body.style.top = '';
                document.body.style.left = '';
                document.body.style.right = '';
                document.body.style.width = '';
                window.scrollTo(0, bodyLockScrollY);
                bodyLockActive = false;
            }
        }

        function parseCoord(value) {
            var normalized = String(value || '').replace(',', '.').trim();
            if (!normalized) {
                return null;
            }
            var num = Number(normalized);
            return Number.isFinite(num) ? num : null;
        }

        function formatCoord(value) {
            return Number(value).toFixed(7).replace(/\.?0+$/, '');
        }

        function resolveLatLonFromFields(xVal, yVal) {
            if (xVal === null || yVal === null) {
                return null;
            }

            var variants = [
                { lat: xVal, lon: yVal },
                { lat: yVal, lon: xVal }
            ];
            var best = null;
            var bestScore = -999;

            variants.forEach(function (variant) {
                var score = 0;
                if (variant.lat < -90 || variant.lat > 90 || variant.lon < -180 || variant.lon > 180) {
                    score = -999;
                } else {
                    score += 2;
                    if (variant.lat >= 44 && variant.lat <= 53 && variant.lon >= 22 && variant.lon <= 41) {
                        score += 3;
                    } else if (variant.lat >= 35 && variant.lat <= 60 && variant.lon >= 10 && variant.lon <= 60) {
                        score += 1;
                    }
                }

                if (score > bestScore) {
                    bestScore = score;
                    best = variant;
                }
            });

            return bestScore < 0 ? null : best;
        }

        function setMapHint(text, isError) {
            if (!mapHint) {
                return;
            }
            mapHint.textContent = text || '';
            mapHint.style.color = isError ? '#8b2330' : '#476787';
        }

        function setMarker(lat, lon, moveMap) {
            if (!map || !window.L) {
                return;
            }
            mapSelected = { lat: lat, lon: lon };
            if (!mapMarker) {
                mapMarker = window.L.marker([lat, lon], { draggable: !mapViewOnly }).addTo(map);
                mapMarker.on('dragend', function () {
                    if (mapViewOnly) {
                        return;
                    }
                    var point = mapMarker.getLatLng();
                    setMarker(point.lat, point.lng, false);
                });
            } else {
                mapMarker.setLatLng([lat, lon]);
            }

            if (mapMarker && mapMarker.dragging) {
                if (mapViewOnly) {
                    mapMarker.dragging.disable();
                } else {
                    mapMarker.dragging.enable();
                }
            }

            if (moveMap) {
                map.setView([lat, lon], Math.max(map.getZoom(), 15));
            }
            setMapHint('Обрано: Lat ' + formatCoord(lat) + ', Lon ' + formatCoord(lon), false);
        }

        function ensureMap() {
            if (map || !window.L || !mapCanvas) {
                return;
            }

            map = window.L.map('mod-map-canvas', { zoomControl: true }).setView([48.5, 31.2], 6);
            window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap'
            }).addTo(map);
            map.on('click', function (event) {
                if (mapViewOnly) {
                    return;
                }
                setMarker(event.latlng.lat, event.latlng.lng, false);
            });
        }

        function openMapModalWithCoords(gpsx, gpsy, viewOnly) {
            if (!mapModal) {
                return;
            }
            mapModal.classList.add('is-open');
            mapModal.setAttribute('aria-hidden', 'false');
            mapModal.dataset.mode = viewOnly ? 'view' : 'edit';
            mapViewOnly = !!viewOnly;

            if (mapModalTitle) {
                mapModalTitle.textContent = viewOnly ? 'Перегляд вказанних координатів' : 'Вибір координат на карті';
            }
            if (mapModalText) {
                mapModalText.textContent = viewOnly
                    ? 'Перегляд позначених координат на карті.'
                    : 'Клікніть на карті, щоб поставити мітку. Потім натисніть «Застосувати координати».';
            }
            if (mapModalActions) {
                mapModalActions.hidden = !!viewOnly;
                mapModalActions.style.display = viewOnly ? 'none' : '';
            }
            if (mapModalTopClose) {
                mapModalTopClose.hidden = !viewOnly;
            }

            if (applyMapBtn) {
                applyMapBtn.hidden = !!viewOnly;
                applyMapBtn.disabled = !!viewOnly;
            }

            setMapHint('', false);

            if (!window.L) {
                setMapHint('Не вдалося завантажити карту. Перевірте підключення до інтернету.', true);
                updateBodyLock();
                return;
            }

            ensureMap();
            if (!map) {
                updateBodyLock();
                return;
            }

            var xVal = parseCoord(gpsx);
            var yVal = parseCoord(gpsy);
            var resolved = resolveLatLonFromFields(xVal, yVal);
            if (resolved) {
                setMarker(resolved.lat, resolved.lon, true);
            } else if (mapSelected) {
                setMarker(mapSelected.lat, mapSelected.lon, true);
            } else {
                setMapHint('Клікніть на карті, щоб поставити мітку.', false);
            }

            setTimeout(function () {
                map.invalidateSize();
            }, 60);
            updateBodyLock();
        }

        function closeMapModal() {
            if (!mapModal) {
                return;
            }
            mapModal.classList.remove('is-open');
            mapModal.setAttribute('aria-hidden', 'true');
            mapModal.dataset.mode = '';
            if (mapModalActions) {
                mapModalActions.hidden = false;
                mapModalActions.style.display = '';
            }
            if (mapModalTopClose) {
                mapModalTopClose.hidden = true;
            }
            updateBodyLock();
        }

        function getCheckedRejectReasonChoice() {
            var checked = rejectReasonOptions.find(function (input) {
                return input.checked;
            });
            return checked ? String(checked.value || '') : '';
        }

        function toggleRejectOtherField() {
            var selected = getCheckedRejectReasonChoice();
            var isOther = selected === '__other__';
            if (rejectOtherWrap) {
                rejectOtherWrap.hidden = !isOther;
            }
            if (rejectModalReason) {
                rejectModalReason.required = isOther;
                if (!isOther) {
                    rejectModalReason.value = '';
                }
            }
        }

        function applyRejectReasonPreset(reasonText) {
            var reason = String(reasonText || '').trim();
            var matched = false;

            if (!reason) {
                rejectReasonOptions.forEach(function (input) {
                    input.checked = false;
                });
                if (rejectModalReason) {
                    rejectModalReason.value = '';
                }
                toggleRejectOtherField();
                return;
            }

            rejectReasonOptions.forEach(function (input) {
                var value = String(input.value || '');
                var isMatch = reason !== '' && value === reason;
                input.checked = isMatch;
                if (isMatch) {
                    matched = true;
                }
            });

            if (!matched) {
                var otherOption = rejectReasonOptions.find(function (input) {
                    return String(input.value || '') === '__other__';
                });
                if (otherOption) {
                    otherOption.checked = true;
                }
                if (rejectModalReason) {
                    rejectModalReason.value = reason;
                }
            }

            toggleRejectOtherField();
        }

        function closeRejectModal() {
            if (!rejectModal) {
                return;
            }
            rejectModal.classList.remove('is-open');
            rejectModal.setAttribute('aria-hidden', 'true');
            if (rejectModalError) {
                rejectModalError.hidden = true;
                rejectModalError.textContent = '';
            }
            rejectReasonOptions.forEach(function (input) {
                input.checked = false;
            });
            if (rejectOtherWrap) {
                rejectOtherWrap.hidden = true;
            }
            if (rejectModalReason) {
                rejectModalReason.value = '';
                rejectModalReason.required = false;
            }
            updateBodyLock();
        }

        function openRejectModal() {
            if (!rejectModal || !currentCard) {
                return;
            }
            var currentReason = String(currentCard.getAttribute('data-reject-reason') || '').trim();
            applyRejectReasonPreset(currentReason);
            if (rejectModalError) {
                rejectModalError.hidden = true;
                rejectModalError.textContent = '';
            }
            rejectModal.classList.add('is-open');
            rejectModal.setAttribute('aria-hidden', 'false');
            updateBodyLock();
            if (getCheckedRejectReasonChoice() === '__other__' && rejectModalReason) {
                setTimeout(function () {
                    rejectModalReason.focus();
                }, 40);
            }
        }

        function setRejectModalLoading(isLoading) {
            if (rejectModalConfirm) {
                rejectModalConfirm.disabled = !!isLoading;
                rejectModalConfirm.setAttribute('aria-disabled', isLoading ? 'true' : 'false');
            }
        }

        function showRejectModalError(message) {
            if (!rejectModalError) {
                return;
            }
            var text = String(message || '').trim();
            rejectModalError.hidden = text === '';
            rejectModalError.textContent = text;
        }

        function refreshActivityItems() {
            activityItems = Array.prototype.slice.call(document.querySelectorAll('.mod-activity-item'));
        }

        function setStatValue(selector, value) {
            var node = document.querySelector(selector);
            if (node) {
                node.textContent = String(value);
            }
        }

        function updateStatusFilterCounts() {
            var scopedCounts = {
                pending: 0,
                approved: 0,
                rejected: 0
            };

            cards.forEach(function (card) {
                var type = card.getAttribute('data-type') || '';
                if (activeType && type !== activeType) {
                    return;
                }

                var status = card.getAttribute('data-status') || 'pending';
                if (status !== 'approved' && status !== 'rejected' && status !== 'pending') {
                    status = 'pending';
                }
                scopedCounts[status] += 1;
            });

            statusTabs.forEach(function (tab) {
                var key = tab.getAttribute('data-status-filter') || '';
                var countNode = tab.querySelector('.mod-filter-pill__count');
                if (countNode && scopedCounts[key] !== undefined) {
                    countNode.textContent = String(scopedCounts[key]);
                }
            });
        }

        function updateBannerStats() {
            var counts = {
                total: cards.length,
                pending: 0,
                approved: 0,
                rejected: 0,
                grave: 0,
                cemetery: 0
            };

            cards.forEach(function (card) {
                var status = card.getAttribute('data-status') || 'pending';
                var type = card.getAttribute('data-type') || '';
                if (status === 'approved' || status === 'rejected' || status === 'pending') {
                    counts[status] += 1;
                } else {
                    counts.pending += 1;
                }
                if (type === 'grave' || type === 'cemetery') {
                    counts[type] += 1;
                }
            });

            setStatValue('.mod-banner-stat[data-stat="total"] .mod-stat-copy strong', counts.total);
            setStatValue('.mod-banner-stat[data-stat="pending"] .mod-stat-copy strong', counts.pending);
            setStatValue('.mod-banner-stat[data-stat="approved"] .mod-stat-copy strong', counts.approved);
            setStatValue('.mod-banner-stat[data-stat="rejected"] .mod-stat-copy strong', counts.rejected);
            setStatValue('.mod-banner-stat--ratio .mod-stat-copy strong', counts.grave + ' / ' + counts.cemetery);
            updateStatusFilterCounts();

            var journalCount = activityListFull
                ? activityListFull.querySelectorAll('.mod-activity-item').length
                : activityItems.length;
            setStatValue('.mod-view-tab[data-panel="journal"] .mod-view-tab__count', journalCount);
            if (activityPreviewCount) {
                activityPreviewCount.textContent = 'Останні ' + PREVIEW_JOURNAL_LIMIT + ' дій';
            }
            if (activityTotalCount) {
                activityTotalCount.textContent = journalCount + ' записів';
            }
        }

        function matchesFilters(node) {
            var status = node.getAttribute('data-status') || '';
            var type = node.getAttribute('data-type') || '';
            var haystack = normalizeText(node.getAttribute('data-search') || '');
            var query = normalizeText(searchInput ? searchInput.value : '');
            var statusOk = !activeStatus || status === activeStatus;
            var typeOk = !activeType || type === activeType;
            var queryOk = !query || haystack.indexOf(query) !== -1;
            return statusOk && typeOk && queryOk;
        }

        function applyFilters() {
            var visible = 0;

            cards.forEach(function (card) {
                var show = matchesFilters(card);
                card.hidden = !show;
                if (show) {
                    visible += 1;
                }
            });

            if (emptyState) {
                emptyState.hidden = visible > 0;
            }

            activityItems.forEach(function (item) {
                item.hidden = false;
            });

            updateStatusFilterCounts();
        }

        function setActivePanel(nextPanel) {
            if (['moderation', 'journal', 'notify'].indexOf(nextPanel) !== -1) {
                activePanel = nextPanel;
            } else {
                activePanel = 'moderation';
            }
            viewTabs.forEach(function (tab) {
                tab.classList.toggle('is-active', tab.getAttribute('data-panel') === activePanel);
            });
            if (moderationView) {
                moderationView.hidden = activePanel !== 'moderation';
                moderationView.classList.toggle('is-active', activePanel === 'moderation');
            }
            if (journalView) {
                journalView.hidden = activePanel !== 'journal';
                journalView.classList.toggle('is-active', activePanel === 'journal');
            }
            if (notifyView) {
                notifyView.hidden = activePanel !== 'notify';
                notifyView.classList.toggle('is-active', activePanel === 'notify');
            }
            syncStateToUrl();
        }

        function syncFilterButtons() {
            statusTabs.forEach(function (tab) {
                tab.classList.toggle('is-active', (tab.getAttribute('data-status-filter') || '') === activeStatus);
            });
            typeTabs.forEach(function (tab) {
                tab.classList.toggle('is-active', (tab.getAttribute('data-type-filter') || '') === activeType);
            });
        }

        readStateFromUrl();
        syncFilterButtons();

        viewTabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                setActivePanel(tab.getAttribute('data-panel') || 'moderation');
            });
        });

        statusTabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                activeStatus = tab.getAttribute('data-status-filter') || 'pending';
                statusTabs.forEach(function (item) {
                    item.classList.toggle('is-active', item === tab);
                });
                applyFilters();
                syncStateToUrl();
            });
        });

        typeTabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                activeType = tab.getAttribute('data-type-filter') || activeType;
                typeTabs.forEach(function (item) {
                    item.classList.toggle('is-active', item === tab);
                });
                applyFilters();
                syncStateToUrl();
            });
        });

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                applyFilters();
                syncStateToUrl();
            });
        }

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function cardMetaIconMarkup(icon, size) {
            var iconSize = size || 18;
            var svgOpen = '<svg xmlns="http://www.w3.org/2000/svg" width="' + iconSize + '" height="' + iconSize + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
            var svgClose = '</svg>';
            if (icon === 'map-pin') {
                return svgOpen + '<path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M9 11a3 3 0 1 0 6 0a3 3 0 0 0 -6 0"></path><path d="M17.657 16.657l-4.243 4.243a2 2 0 0 1 -2.827 0l-4.244 -4.243a8 8 0 1 1 11.314 0"></path>' + svgClose;
            }
            if (icon === 'calendar') {
                return svgOpen + '<path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M4 7a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12"></path><path d="M16 3v4"></path><path d="M8 3v4"></path><path d="M4 11h16"></path><path d="M11 15h1"></path><path d="M12 15v3"></path>' + svgClose;
            }
            if (icon === 'user-circle') {
                return svgOpen + '<path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"></path><path d="M9 10a3 3 0 1 0 6 0a3 3 0 1 0 -6 0"></path><path d="M6.168 18.849a4 4 0 0 1 3.832 -2.849h4a4 4 0 0 1 3.834 2.855"></path>' + svgClose;
            }
            return svgOpen + '<path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M3 7l6 -3l6 3l6 -3v13l-6 3l-6 -3l-6 3v-13"></path><path d="M9 4v13"></path><path d="M15 7v13"></path>' + svgClose;
        }

        function cardInfoItemContentHtml(icon, text) {
            return '<span class="mod-entry-card__meta-icon" aria-hidden="true">' + cardMetaIconMarkup(icon) + '</span><span>' + escapeHtml(text || '-') + '</span>';
        }

        function cardTextLineContentHtml(icon, text) {
            return '<span class="mod-entry-card__meta-icon" aria-hidden="true">' + cardMetaIconMarkup(icon) + '</span><span>' + escapeHtml(text || '-') + '</span>';
        }

        function cardTextLineHtml(icon, text, extraClass) {
            var className = 'mod-entry-card__text-line';
            if (extraClass) {
                className += ' ' + extraClass;
            }
            return '<div class="' + className + '">' + cardTextLineContentHtml(icon, text) + '</div>';
        }

        function cardAuthorHtml(author) {
            return '<span class="mod-entry-card__meta-icon" aria-hidden="true">' + cardMetaIconMarkup('user-circle') + '</span><span class="mod-entry-card__author">' + escapeHtml(author || '-') + '</span>';
        }

        function alertIconMarkup(type) {
            if (type === 'success') {
                return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M20 6L9 17L4 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
            }
            return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
        }

        function showGlobalAlert(message, type) {
            var text = String(message || '').trim();
            if (!text) {
                return;
            }
            var normalizedType = (type === 'success' || type === 'alert-success') ? 'success' : 'error';
            var existing = document.getElementById('global-alert');
            if (existing && existing.parentNode) {
                existing.parentNode.removeChild(existing);
            }

            var alertNode = document.createElement('div');
            alertNode.id = 'global-alert';
            alertNode.className = 'notification ' + (normalizedType === 'success' ? 'notification-success' : 'notification-error');
            alertNode.innerHTML = '<div class="notification-content"><span class="notification-icon">' +
                alertIconMarkup(normalizedType) +
                '</span><span class="notification-message">' + escapeHtml(text) + '</span></div>';
            document.body.appendChild(alertNode);

            setTimeout(function () {
                window.requestAnimationFrame(function () {
                    alertNode.classList.add('show');
                });
            }, 1);

            setTimeout(function () {
                alertNode.classList.remove('show');
                setTimeout(function () {
                    if (alertNode.parentNode) {
                        alertNode.parentNode.removeChild(alertNode);
                    }
                }, 300);
            }, 3000);
        }

        function cardValue(card, name, fallback) {
            if (!card) {
                return fallback || '';
            }
            var value = card.getAttribute('data-' + name);
            if (value === null || value === undefined || value === '') {
                return fallback || '';
            }
            return value;
        }

        function buildSenderSection(card) {
            return '<section class="mod-entry-modal__section">' +
                '<h4>Відправник</h4>' +
                '<div class="mod-entry-modal__sender">' +
                '<div><strong>' + escapeHtml(cardValue(card, 'author', '-')) + '</strong>' +
                '<span>' + escapeHtml(cardValue(card, 'submitted', '-')) + '</span></div>' +
                '</div>' +
                '</section>';
        }

        function normalizePhotoSrc(value) {
            var src = String(value || '').trim();
            if (!src || src === '-' || src === '—') {
                return '';
            }
            return src;
        }

        function buildPhotoCards(card) {
            var type = cardValue(card, 'type', 'grave');
            var photos = type === 'grave'
                ? [
                    { src: normalizePhotoSrc(cardValue(card, 'photo1', '')) || normalizePhotoSrc(cardValue(card, 'preview', '')), title: 'Фото поховання' },
                    { src: normalizePhotoSrc(cardValue(card, 'photo2', '')), title: 'Фото таблички' },
                    { src: normalizePhotoSrc(cardValue(card, 'photo3', '')), title: 'Додаткове фото' }
                ]
                : [
                    { src: normalizePhotoSrc(cardValue(card, 'scheme', '')), title: 'Схема кладовища' }
                ];

            return photos.map(function (photo) {
                if (!photo.src) {
                    return '<div class="mod-entry-modal__photo-card is-empty">' +
                        '<strong>' + escapeHtml(photo.title) + '</strong>' +
                        '<span>Фото не встановлено</span>' +
                        '</div>';
                }
                return '<div class="mod-entry-modal__photo-card">' +
                    '<img src="' + escapeHtml(photo.src) + '" alt="' + escapeHtml(photo.title) + '">' +
                    '<div class="mod-entry-modal__photo-meta">' +
                    '<strong>' + escapeHtml(photo.title) + '</strong>' +
                    '<button type="button" class="mod-entry-modal__photo-btn" data-photo-src="' + escapeHtml(photo.src) + '" data-photo-title="' + escapeHtml(photo.title) + '">Переглянути</button>' +
                    '</div>' +
                    '</div>';
            }).join('');
        }

        function splitDateRange(value) {
            var clean = String(value || '').trim();
            if (!clean || clean === '-' || clean === 'Дати не вказані') {
                return { birth: '-', death: '-' };
            }
            var parts = clean.split(' - ');
            if (parts.length < 2) {
                return { birth: parts[0] || '-', death: '-' };
            }
            return { birth: parts[0] || '-', death: parts[1] || '-' };
        }

        function formatLocationLine(card) {
            var region = cardValue(card, 'region', '-');
            var district = cardValue(card, 'district', '-');
            var town = cardValue(card, 'town', '-');
            var parts = [];
            if (region && region !== '-') {
                parts.push(region + ' область');
            }
            if (district && district !== '-') {
                parts.push(district + ' район');
            }
            if (town && town !== '-') {
                parts.push(town);
            }
            return parts.length ? parts.join(', ') : '-';
        }

        function formatPlotLine(card) {
            var pos1 = cardValue(card, 'pos1', '-');
            var pos2 = cardValue(card, 'pos2', '-');
            var pos3 = cardValue(card, 'pos3', '-');
            var parts = [];

            if (pos1 && pos1 !== '-') {
                parts.push('Квартал ' + pos1);
            }
            if (pos2 && pos2 !== '-') {
                parts.push('ряд ' + pos2);
            }
            if (pos3 && pos3 !== '-') {
                parts.push('місце ' + pos3);
            }

            return parts.length ? parts.join(', ') : '-';
        }

        function renderEntryModalView(card) {
            var type = cardValue(card, 'type', 'grave');
            var rejectReason = cardValue(card, 'reject-reason', '');
            var reviewer = cardValue(card, 'reviewer', '');
            var dateRange = splitDateRange(cardValue(card, 'dates', ''));
            var locationLine = formatLocationLine(card);
            var plotLine = formatPlotLine(card);
            var gpsxRaw = cardValue(card, 'gpsx', '');
            var gpsyRaw = cardValue(card, 'gpsy', '');
            var gpsxVal = parseCoord(gpsxRaw);
            var gpsyVal = parseCoord(gpsyRaw);
            var hasCoords = gpsxVal !== null && gpsyVal !== null;
            var gpsXText = gpsxVal !== null ? formatCoord(gpsxVal) : '-';
            var gpsYText = gpsyVal !== null ? formatCoord(gpsyVal) : '-';
            var coordsButton = hasCoords
                ? '<div class="mod-map-picker-row"><button type="button" class="mod-entry-modal__photo-btn" data-map-x="' + escapeHtml(formatCoord(gpsxVal)) + '" data-map-y="' + escapeHtml(formatCoord(gpsyVal)) + '">Переглянути на карті</button></div>'
                : '';
            var isRejected = cardValue(card, 'status', '') === 'rejected';
            var rejectWho = reviewer && reviewer !== 'Невідомо'
                ? '<small>Відхилив: ' + escapeHtml(reviewer) + '</small>'
                : '';
            var rejectBanner = (isRejected && rejectReason)
                ? '<section class="mod-entry-modal__section mod-entry-modal__section--reject-banner"><div class="mod-entry-modal__reject-banner"><strong>Причина відхилення</strong><p>' + escapeHtml(rejectReason) + '</p>' + rejectWho + '</div></section>'
                : '';

            if (type === 'grave') {
                return '' +
                    rejectBanner +
                    '<section class="mod-entry-modal__section">' +
                    '<h4>Основна інформація</h4>' +
                    '<div class="mod-entry-modal__grid mod-entry-modal__grid--person">' +
                    '<div class="mod-entry-modal__field mod-entry-modal__field--wide"><span>ПІБ</span><strong>' + escapeHtml(cardValue(card, 'title', '-')) + '</strong></div>' +
                    '<div class="mod-entry-modal__field"><span>Дата народження</span><strong>' + escapeHtml(dateRange.birth) + '</strong></div>' +
                    '<div class="mod-entry-modal__field"><span>Дата смерті</span><strong>' + escapeHtml(dateRange.death) + '</strong></div>' +
                    '</div>' +
                    '</section>' +
                    '<section class="mod-entry-modal__section">' +
                    '<h4>Розташування</h4>' +
                    '<div class="mod-entry-modal__field"><span>Місце</span><strong>' + escapeHtml(locationLine) + '</strong></div>' +
                    '<div class="mod-entry-modal__placement-grid mod-entry-modal__grid--compact-top">' +
                    '<div class="mod-entry-modal__placement-card mod-entry-modal__placement-card--cemetery">' +
                    '<span class="mod-entry-modal__placement-icon" aria-hidden="true">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M5 21v-2a3 3 0 0 1 3 -3h8a3 3 0 0 1 3 3v2h-14"></path><path d="M10 16v-5h-4v-4h4v-4h4v4h4v4h-4v5"></path></svg>' +
                    '</span>' +
                    '<span class="mod-entry-modal__placement-copy"><span>Кладовище</span><strong>' + escapeHtml(cardValue(card, 'cemetery', '-')) + '</strong></span>' +
                    '</div>' +
                    '<div class="mod-entry-modal__placement-card mod-entry-modal__placement-card--plot">' +
                    '<span class="mod-entry-modal__placement-icon" aria-hidden="true">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M12 21s-6 -4.35 -6 -10a6 6 0 1 1 12 0c0 5.65 -6 10 -6 10"></path><circle cx="12" cy="11" r="2.5"></circle></svg>' +
                    '</span>' +
                    '<span class="mod-entry-modal__placement-copy"><span>Квартал / Місце</span><strong>' + escapeHtml(plotLine) + '</strong></span>' +
                    '</div>' +
                    '</div>' +
                    '</section>' +
                    '<section class="mod-entry-modal__section">' +
                    '<h4>Фотографії</h4>' +
                    '<div class="mod-entry-modal__photo-grid">' + buildPhotoCards(card) + '</div>' +
                    '</section>' +
                    buildSenderSection(card);
            }

            return '' +
                rejectBanner +
                '<section class="mod-entry-modal__section">' +
                '<h4>Основна інформація</h4>' +
                '<div class="mod-entry-modal__field"><span>Місце</span><strong>' + escapeHtml(locationLine) + '</strong></div>' +
                '<div class="mod-entry-modal__placement-grid mod-entry-modal__grid--compact-top">' +
                '<div class="mod-entry-modal__placement-card mod-entry-modal__placement-card--cemetery">' +
                '<span class="mod-entry-modal__placement-icon" aria-hidden="true">' +
                '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M5 21v-2a3 3 0 0 1 3 -3h8a3 3 0 0 1 3 3v2h-14"></path><path d="M10 16v-5h-4v-4h4v-4h4v4h4v4h-4v5"></path></svg>' +
                '</span>' +
                '<span class="mod-entry-modal__placement-copy"><span>Назва</span><strong>' + escapeHtml(cardValue(card, 'title', '-')) + '</strong></span>' +
                '</div>' +
                '<div class="mod-entry-modal__placement-card mod-entry-modal__placement-card--plot">' +
                '<span class="mod-entry-modal__placement-icon" aria-hidden="true">' +
                '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M12 21s-6 -4.35 -6 -10a6 6 0 1 1 12 0c0 5.65 -6 10 -6 10"></path><circle cx="12" cy="11" r="2.5"></circle></svg>' +
                '</span>' +
                '<span class="mod-entry-modal__placement-copy"><span>Адреса</span><strong>' + escapeHtml(cardValue(card, 'address', '-')) + '</strong></span>' +
                '</div>' +
                '</div>' +
                '</section>' +
                '<section class="mod-entry-modal__section">' +
                '<h4>Розташування</h4>' +
                '<div class="mod-entry-modal__placement-grid mod-entry-modal__grid--compact-top">' +
                '<div class="mod-entry-modal__placement-card mod-entry-modal__placement-card--cemetery">' +
                '<span class="mod-entry-modal__placement-icon" aria-hidden="true">' +
                '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"></path><path d="M12 17l-1 -4l-4 -1l9 -4l-4 9"></path></svg>' +
                '</span>' +
                '<span class="mod-entry-modal__placement-copy"><span>GPS X</span><strong>' + escapeHtml(gpsXText) + '</strong></span>' +
                '</div>' +
                '<div class="mod-entry-modal__placement-card mod-entry-modal__placement-card--plot">' +
                '<span class="mod-entry-modal__placement-icon" aria-hidden="true">' +
                '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"></path><path d="M12 17l-1 -4l-4 -1l9 -4l-4 9"></path></svg>' +
                '</span>' +
                '<span class="mod-entry-modal__placement-copy"><span>GPS Y</span><strong>' + escapeHtml(gpsYText) + '</strong></span>' +
                '</div>' +
                '</div>' +
                coordsButton +
                '</section>' +
                '<section class="mod-entry-modal__section">' +
                '<h4>Фотографії</h4>' +
                '<div class="mod-entry-modal__photo-grid mod-entry-modal__photo-grid--single">' + buildPhotoCards(card) + '</div>' +
                '</section>' +
                buildSenderSection(card);
        }

        function bindEntryModalPreviewButtons(scope) {
            if (!scope) {
                return;
            }
            Array.prototype.slice.call(scope.querySelectorAll('[data-photo-src]')).forEach(function (button) {
                button.addEventListener('click', function () {
                    openPhotoModal(button.getAttribute('data-photo-src') || '', button.getAttribute('data-photo-title') || 'Перегляд фото');
                });
            });
        }

        function bindEntryModalMapButtons(scope) {
            if (!scope) {
                return;
            }
            Array.prototype.slice.call(scope.querySelectorAll('[data-map-x][data-map-y]')).forEach(function (button) {
                button.addEventListener('click', function () {
                    openMapModalWithCoords(
                        button.getAttribute('data-map-x') || '',
                        button.getAttribute('data-map-y') || '',
                        true
                    );
                });
            });
        }

        function injectModalFormMeta(form, card) {
            if (!form || !card || form.querySelector('.mod-entry-modal__section--sender')) {
                return;
            }
            var actions = form.querySelector('.mod-form-actions');
            if (!actions) {
                return;
            }
            var section = document.createElement('section');
            section.className = 'mod-entry-modal__section mod-entry-modal__section--sender';
            section.innerHTML = '<h4>Відправник</h4><div class="mod-entry-modal__sender"><div><strong>' +
                escapeHtml(cardValue(card, 'author', '-')) +
                '</strong><span>' + escapeHtml(cardValue(card, 'submitted', '-')) + '</span></div></div>';
            if (actions && actions.parentNode) {
                actions.parentNode.insertBefore(section, actions);
                return;
            }
            form.appendChild(section);
        }

        function showModalFlash(html) {
            if (!entryModalFlash) {
                return;
            }
            entryModalFlash.innerHTML = html || '';
            entryModalFlash.hidden = !html;
        }

        function setEntryModalMode(mode) {
            var isEdit = mode === 'edit';
            if (entryModal) {
                entryModal.classList.toggle('is-editing', isEdit);
            }
            if (entryModalCard) {
                entryModalCard.classList.toggle('is-editing', isEdit);
            }
            if (!isEdit) {
                restoreEntryModalView();
            }
        }

        function getCardTypeIcon(type) {
            return type === 'grave' ? 'grave' : 'cemetery';
        }

        function getEditStateForCard(card) {
            var type = card ? (card.getAttribute('data-type') || 'grave') : 'grave';
            var cardStatus = card ? (card.getAttribute('data-status') || 'pending') : 'pending';
            return {
                type: type,
                status: activeStatus || cardStatus,
                tab: activeType ? activeType : type,
                id: card ? (card.getAttribute('data-id') || '') : ''
            };
        }

        function buildEditFormUrl(card) {
            var state = getEditStateForCard(card);
            return '/moderation-panel.php?ajax_edit_form=1&type=' + encodeURIComponent(state.type) +
                '&id=' + encodeURIComponent(state.id) +
                '&tab=' + encodeURIComponent(state.tab) +
                '&status=' + encodeURIComponent(state.status);
        }

        function fillCardElement(card, payload) {
            if (!card || !payload) {
                return;
            }

            card.setAttribute('data-id', payload.id || '');
            card.setAttribute('data-type', payload.type || '');
            card.setAttribute('data-title', payload.title || '');
            card.setAttribute('data-status', payload.status || 'pending');
            card.setAttribute('data-status-label', payload.status_label || '');
            card.setAttribute('data-type-label', payload.type_label || '');
            card.setAttribute('data-submitted', payload.submitted || '-');
            card.setAttribute('data-submitted-iso', payload.submitted_iso || '');
            card.setAttribute('data-author', payload.author || '-');
            card.setAttribute('data-reviewer', payload.reviewer || '');
            card.setAttribute('data-region', payload.region || '-');
            card.setAttribute('data-district', payload.district || '-');
            card.setAttribute('data-town', payload.town || '-');
            card.setAttribute('data-cemetery', payload.cemetery || '-');
            card.setAttribute('data-address', payload.address || '-');
            card.setAttribute('data-dates', payload.dates || '-');
            card.setAttribute('data-summary', payload.summary || '-');
            card.setAttribute('data-pos1', payload.pos1 || '-');
            card.setAttribute('data-pos2', payload.pos2 || '-');
            card.setAttribute('data-pos3', payload.pos3 || '-');
            card.setAttribute('data-note', payload.note || '');
            card.setAttribute('data-reject-reason', payload.reject_reason || '');
            card.setAttribute('data-edit-url', payload.edit_url || '#');
            card.setAttribute('data-preview', payload.preview || '');
            card.setAttribute('data-photo1', payload.photo1 || '');
            card.setAttribute('data-photo2', payload.photo2 || '');
            card.setAttribute('data-photo3', payload.photo3 || '');
            card.setAttribute('data-scheme', payload.scheme || '');
            card.setAttribute('data-gpsx', payload.gpsx || '');
            card.setAttribute('data-gpsy', payload.gpsy || '');
            card.setAttribute('data-action-iso', payload.action_iso || '');
            card.setAttribute('data-search', payload.search || '');

            var media = card.querySelector('.mod-entry-card__media');
            if (media) {
                if (payload.preview) {
                    media.className = 'mod-entry-card__media mod-entry-card__media--photo';
                    media.innerHTML = '<img src="' + escapeHtml(payload.preview) + '" alt="' + escapeHtml(payload.title || payload.type_label || '') + '" loading="lazy">';
                } else {
                    media.className = 'mod-entry-card__media mod-entry-card__media--icon mod-entry-card__media--' + getCardTypeIcon(payload.type);
                    media.innerHTML = iconMarkup(payload.type);
                }
            }

            var titleNode = card.querySelector('.mod-entry-card__titles h3');
            if (titleNode) {
                titleNode.textContent = payload.title || '-';
            }

            var subtitleNode = card.querySelector('.mod-entry-card__subtitle');
            if (subtitleNode) {
                subtitleNode.textContent = payload.type_label || '-';
            }

            var idNode = card.querySelector('.mod-entry-card__id');
            if (idNode) {
                idNode.textContent = 'ID ' + (payload.id || '-');
            }

            var statusNode = card.querySelector('.mod-entry-card__status');
            if (statusNode) {
                statusNode.className = payload.status_class || 'mod-entry-card__status';
                statusNode.textContent = payload.status_label || '-';
            }

            var infoItems = card.querySelectorAll('.mod-entry-card__info-item');
                if (payload.type === 'grave') {
                    if (infoItems[0]) {
                        infoItems[0].innerHTML = cardInfoItemContentHtml('map-pin', payload.info_primary || 'Місце поховання не вказано');
                    }
                    if (infoItems[1]) {
                        infoItems[1].innerHTML = cardInfoItemContentHtml('calendar', payload.info_secondary || 'Дати не вказані');
                    }
                    var graveTextLine = card.querySelector('.mod-entry-card__text-line');
                    if (graveTextLine) {
                        graveTextLine.innerHTML = cardTextLineContentHtml('map', payload.location_display || 'Локація не вказана');
                    }
                } else {
                    var textLines = card.querySelectorAll('.mod-entry-card__text-line');
                    if (textLines[0]) {
                        textLines[0].innerHTML = cardTextLineContentHtml('map', payload.info_primary || 'Локація не вказана');
                    }
                    if (textLines[1]) {
                        textLines[1].innerHTML = cardTextLineContentHtml('map-pin', payload.info_secondary || 'Адресу не вказано');
                    }
                }

            var authorNode = card.querySelector('.mod-entry-card__author');
            if (authorNode) {
                authorNode.textContent = payload.author || '-';
            }

            var authorWrap = card.querySelector('.mod-entry-card__author-wrap');
            if (!authorWrap && authorNode && authorNode.parentNode) {
                authorWrap = document.createElement('div');
                authorWrap.className = 'mod-entry-card__author-wrap';
                authorNode.parentNode.insertBefore(authorWrap, authorNode);
                var authorIconNode = document.createElement('span');
                authorIconNode.className = 'mod-entry-card__meta-icon';
                authorIconNode.setAttribute('aria-hidden', 'true');
                authorIconNode.innerHTML = cardMetaIconMarkup('user-circle');
                authorWrap.appendChild(authorIconNode);
                authorWrap.appendChild(authorNode);
            }

            if (authorWrap) {
                var rejectChip = authorWrap.querySelector('.mod-entry-card__reject-chip');
                var shouldShowReject = payload.status === 'rejected' && !!payload.reject_reason;
                if (shouldShowReject) {
                    var chipText = 'Причина: ' + String(payload.reject_reason || '');
                    if (!rejectChip) {
                        rejectChip = document.createElement('span');
                        rejectChip.className = 'mod-entry-card__reject-chip';
                        authorWrap.appendChild(rejectChip);
                    }
                    rejectChip.textContent = chipText;
                    rejectChip.setAttribute('title', chipText);
                } else if (rejectChip) {
                    rejectChip.remove();
                }
            }

            var dateNode = card.querySelector('.mod-entry-card__date');
            if (dateNode) {
                dateNode.textContent = payload.submitted || '-';
            }

        }

        function activityVerbByStatus(status) {
            if (status === 'approved') {
                return 'схвалено';
            }
            if (status === 'rejected') {
                return 'відхилено';
            }
            return 'подано';
        }

        function activityRoleByStatus(status) {
            return status === 'pending' ? 'Відправник' : 'Модератор';
        }

        function journalMarkerIconMarkup(status) {
            if (status === 'approved') {
                return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"></path><path d="M9 12l2 2l4 -4"></path></svg>';
            }
            if (status === 'rejected') {
                return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"></path><path d="M10 10l4 4m0 -4l-4 4"></path></svg>';
            }
            return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M10 14l11 -11"></path><path d="M21 3l-6.5 18a.55 .55 0 0 1 -1 0l-3.5 -7l-7 -3.5a.55 .55 0 0 1 0 -1l18 -6.5"></path></svg>';
        }

        function buildActivityItemHtml(payload, decisionIso, decisionDisplay) {
            var status = payload && payload.status ? payload.status : 'pending';
            var type = payload && payload.type ? payload.type : '';
            var search = payload && payload.search ? payload.search : '';
            var author = payload && payload.author ? payload.author : '-';
            var title = payload && payload.title ? payload.title : '-';
            var typeLabel = payload && payload.type_label ? payload.type_label : '';
            var meta = typeLabel || '-';
            var iso = decisionIso || '';
            var display = decisionDisplay || '-';

            return '<article class="mod-activity-item" data-id="' + escapeHtml(payload.id || '') + '" data-status="' + escapeHtml(status) + '" data-type="' + escapeHtml(type) + '" data-search="' + escapeHtml(search) + '">' +
                '<span class="mod-activity-item__marker mod-activity-item__marker--' + escapeHtml(status) + '">' + journalMarkerIconMarkup(status) + '</span>' +
                '<div class="mod-activity-item__body"><div class="mod-activity-item__row"><strong><span class="mod-activity-item__role">' + escapeHtml(activityRoleByStatus(status)) + ':</span> ' + escapeHtml(author) + '</strong>' +
                '<span class="mod-activity-item__verb mod-activity-item__verb--' + escapeHtml(status) + '">' + escapeHtml(activityVerbByStatus(status)) + '</span>' +
                '<time datetime="' + escapeHtml(iso) + '">' + escapeHtml(display) + '</time></div>' +
                '<div class="mod-activity-item__title">' + escapeHtml(title) + '</div>' +
                '<div class="mod-activity-item__meta">' + escapeHtml(meta) + '</div></div></article>';
        }

        function ensureActivityListNotEmpty(container) {
            if (!container) {
                return;
            }
            var emptyNode = container.querySelector('.mod-empty');
            if (emptyNode) {
                emptyNode.remove();
            }
        }

        function prependActivityNode(container, html) {
            if (!container || !html) {
                return;
            }
            ensureActivityListNotEmpty(container);
            container.insertAdjacentHTML('afterbegin', html);
        }

        function updateActivityNode(node, payload, decisionIso, decisionDisplay) {
            if (!node || !payload) {
                return;
            }
            var status = payload.status || 'pending';
            node.setAttribute('data-status', status);
            if (payload.search !== undefined) {
                node.setAttribute('data-search', payload.search || '');
            }
            if (payload.type) {
                node.setAttribute('data-type', payload.type);
            }
            if (payload.id) {
                node.setAttribute('data-id', payload.id);
            }

            var marker = node.querySelector('.mod-activity-item__marker');
            if (marker) {
                marker.className = 'mod-activity-item__marker mod-activity-item__marker--' + status;
                marker.innerHTML = journalMarkerIconMarkup(status);
            }

            var verb = node.querySelector('.mod-activity-item__verb');
            if (verb) {
                verb.className = 'mod-activity-item__verb mod-activity-item__verb--' + status;
                verb.textContent = activityVerbByStatus(status);
            }

            var timeNode = node.querySelector('time');
            if (timeNode) {
                if (decisionIso) {
                    timeNode.setAttribute('datetime', decisionIso);
                }
                if (decisionDisplay) {
                    timeNode.textContent = decisionDisplay;
                }
            }

            var authorNode = node.querySelector('.mod-activity-item__row strong');
            if (authorNode) {
                authorNode.innerHTML = '<span class="mod-activity-item__role">' + escapeHtml(activityRoleByStatus(status)) + ':</span> ' + escapeHtml(payload.author || '-');
            }

            var titleNode = node.querySelector('.mod-activity-item__title');
            if (titleNode) {
                titleNode.textContent = payload.title || '-';
            }

            var metaNode = node.querySelector('.mod-activity-item__meta');
            if (metaNode) {
                metaNode.textContent = payload.type_label || '-';
            }
        }

        function updateJournalRealtime(payload, decisionIso, decisionDisplay) {
            if (!payload || !payload.id || !payload.type) {
                return;
            }
            var html = buildActivityItemHtml(payload, decisionIso, decisionDisplay);
            prependActivityNode(activityList, html);
            prependActivityNode(activityListFull, html);

            if (activityList) {
                var previewItems = activityList.querySelectorAll('.mod-activity-item');
                while (previewItems.length > PREVIEW_JOURNAL_LIMIT) {
                    previewItems[previewItems.length - 1].remove();
                    previewItems = activityList.querySelectorAll('.mod-activity-item');
                }
            }
            refreshActivityItems();
        }

        function maxIso(leftIso, rightIso) {
            var leftTs = Date.parse(String(leftIso || '').replace(' ', 'T'));
            var rightTs = Date.parse(String(rightIso || '').replace(' ', 'T'));
            if (!Number.isFinite(leftTs)) {
                return rightIso || '';
            }
            if (!Number.isFinite(rightTs)) {
                return leftIso || '';
            }
            return leftTs >= rightTs ? leftIso : rightIso;
        }

        function getLatestPendingIso() {
            var latest = '';
            cards.forEach(function (card) {
                if ((card.getAttribute('data-status') || 'pending') !== 'pending') {
                    return;
                }
                latest = maxIso(latest, card.getAttribute('data-submitted-iso') || '');
            });
            return latest;
        }

        var pendingFeedSinceIso = getLatestPendingIso();
        var pendingFeedTimer = null;

        function fetchPendingFeed() {
            var url = '/moderation-panel.php?ajax_pending_feed=1';
            if (pendingFeedSinceIso) {
                url += '&since=' + encodeURIComponent(pendingFeedSinceIso);
            }

            fetch(url, { credentials: 'same-origin' })
                .then(function (res) { return res.json(); })
                .then(function (payload) {
                    if (!payload || !payload.success || !Array.isArray(payload.items) || !payload.items.length) {
                        if (payload && payload.server_time) {
                            pendingFeedSinceIso = maxIso(pendingFeedSinceIso, payload.server_time);
                        }
                        return;
                    }

                    payload.items.forEach(function (item) {
                        var exists = findCardByPayload(item);
                        upsertCardFromPayload(item, true);
                        pendingFeedSinceIso = maxIso(pendingFeedSinceIso, item.submitted_iso || '');

                        if (!exists) {
                            var actionIso = item.action_iso || item.submitted_iso || '';
                            updateJournalRealtime(item, actionIso, item.submitted || '');
                        }
                    });

                    if (payload.server_time) {
                        pendingFeedSinceIso = maxIso(pendingFeedSinceIso, payload.server_time);
                    }
                    updateBannerStats();
                    applyFilters();
                })
                .catch(function () {
                });
        }

        function startPendingFeedPolling() {
            if (pendingFeedTimer) {
                clearInterval(pendingFeedTimer);
            }
            pendingFeedTimer = setInterval(fetchPendingFeed, 20000);
        }

        function iconMarkup(type) {
            if (type === 'grave') {
                return '<svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" /><path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" /></svg>';
            }
            return cardMetaIconMarkup('map', 26);
        }

        function closeEntryModal() {
            if (!entryModal) {
                return;
            }
            closeRejectModal();
            currentCard = null;
            entryModal.dataset.entryStatus = '';
            showModalFlash('');
            setEntryModalMode('view');
            entryModal.classList.remove('is-open');
            entryModal.setAttribute('aria-hidden', 'true');
            updateBodyLock();
        }

        function openEntryModal(card) {
            if (!entryModal || !card) {
                return;
            }
            currentCard = card;
            showModalFlash('');
            setEntryModalMode('view');
            var type = card.getAttribute('data-type') || 'grave';
            var preview = card.getAttribute('data-preview') || '';
            var title = card.getAttribute('data-title') || '-';
            var status = card.getAttribute('data-status-label') || '-';
            var subtitle = (card.getAttribute('data-type-label') || '-') + ' | ID ' + (card.getAttribute('data-id') || '-') + ' | Подано: ' + (card.getAttribute('data-submitted') || '-');

            if (entryModalMedia) {
                if (preview) {
                    entryModalMedia.className = 'mod-entry-modal__media mod-entry-modal__media--photo';
                    entryModalMedia.innerHTML = '<img src="' + preview + '" alt="">';
                } else {
                    entryModalMedia.className = 'mod-entry-modal__media mod-entry-modal__media--icon mod-entry-modal__media--' + type;
                    entryModalMedia.innerHTML = iconMarkup(type);
                }
            }

            if (entryModalTitle) entryModalTitle.textContent = title;
            if (entryModalSubtitle) entryModalSubtitle.textContent = subtitle;
            var statusKey = String(card.getAttribute('data-status') || 'pending').toLowerCase().trim();
            if (statusKey !== 'pending' && statusKey !== 'approved' && statusKey !== 'rejected') {
                statusKey = 'pending';
            }
            entryModal.dataset.entryStatus = statusKey;
            if (entryModalStatus) {
                entryModalStatus.textContent = status;
                entryModalStatus.className = 'mod-entry-modal__status mod-entry-modal__status--' + statusKey;
            }
            var isPendingStatus = statusKey === 'pending';
            if (entryModalApproveBtn) {
                entryModalApproveBtn.hidden = !isPendingStatus;
                entryModalApproveBtn.disabled = !isPendingStatus;
            }
            if (entryModalRejectBtn) {
                entryModalRejectBtn.hidden = !isPendingStatus;
                entryModalRejectBtn.disabled = !isPendingStatus;
            }
            if (entryModalEdit) entryModalEdit.setAttribute('href', '#');
            if (entryModalView) {
                entryModalView.innerHTML = renderEntryModalView(card);
                bindEntryModalPreviewButtons(entryModalView);
                bindEntryModalMapButtons(entryModalView);
            }

            entryModal.classList.add('is-open');
            entryModal.setAttribute('aria-hidden', 'false');
            updateBodyLock();
        }

        function bindModalEditForm(form) {
            if (!form) {
                return;
            }

            initEditForm(form);
            if (typeof window.initDatepicker === 'function') {
                window.initDatepicker(form);
            }
            injectModalFormMeta(form, currentCard);

            Array.prototype.slice.call(form.querySelectorAll('[data-mod-cancel-edit]')).forEach(function (node) {
                node.addEventListener('click', function () {
                    setEntryModalMode('view');
                    if (currentCard) {
                        openEntryModal(currentCard);
                    }
                });
            });

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                var submitButton = form.querySelector('button[type="submit"]');
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.setAttribute('aria-disabled', 'true');
                }

                fetch(form.getAttribute('action') || '/moderation-panel.php', {
                    method: 'POST',
                    body: new FormData(form),
                    credentials: 'same-origin'
                })
                    .then(function (res) { return res.json(); })
                    .then(function (payload) {
                        if (payload.success && currentCard && payload.item) {
                            fillCardElement(currentCard, payload.item);
                            openEntryModal(currentCard);
                            if (payload.alertHtml) {
                                showModalFlash(payload.alertHtml);
                                setTimeout(function () {
                                    showModalFlash('');
                                }, 2200);
                            }
                            applyFilters();
                            return;
                        }

                        if (payload.formHtml && entryModalView) {
                            entryModalView.innerHTML = (payload.alertHtml || '') + payload.formHtml;
                            bindModalEditForm(entryModalView.querySelector('form'));
                        } else if (payload.alertHtml) {
                            showModalFlash(payload.alertHtml);
                        }
                    })
                    .catch(function () {
                        if (entryModalView) {
                            entryModalView.innerHTML = '<div class="mod-alert mod-alert--error">Не вдалося зберегти зміни. Спробуйте ще раз.</div>' + (form.outerHTML || '');
                            bindModalEditForm(entryModalView.querySelector('form'));
                        }
                    })
                    .finally(function () {
                        if (submitButton) {
                            submitButton.disabled = false;
                            submitButton.removeAttribute('aria-disabled');
                        }
                    });
            });
        }

        function setDecisionButtonsLoading(isLoading) {
            [entryModalApproveBtn, entryModalRejectBtn].forEach(function (btn) {
                if (!btn) {
                    return;
                }
                btn.disabled = !!isLoading;
                btn.setAttribute('aria-disabled', isLoading ? 'true' : 'false');
            });
        }

        function submitModerationDecision(decision, options) {
            options = options || {};
            if (!currentCard) {
                return;
            }
            var type = currentCard.getAttribute('data-type') || '';
            var id = currentCard.getAttribute('data-id') || '';
            var noteInput = entryModalView ? entryModalView.querySelector('[data-mod-note-input]') : null;
            var note = noteInput ? String(noteInput.value || '').trim() : '';
            var rejectReason = typeof options.rejectReason === 'string'
                ? String(options.rejectReason || '').trim()
                : '';
            var closeEntryOnSuccess = options.closeEntryOnSuccess !== false;
            var closeRejectOnSuccess = options.closeRejectOnSuccess !== false;

            if (!type || !id) {
                showModalFlash('<div class="mod-alert mod-alert--error">Не вдалося визначити запис для модерації.</div>');
                return;
            }
            if (decision === 'rejected' && !rejectReason) {
                if (rejectModal && rejectModal.classList.contains('is-open')) {
                    showRejectModalError('Вкажіть причину відхилення.');
                    if (rejectModalReason) {
                        rejectModalReason.focus();
                    }
                } else {
                    showModalFlash('<div class="mod-alert mod-alert--error">Вкажіть причину відхилення.</div>');
                }
                return;
            }

            setDecisionButtonsLoading(true);
            setRejectModalLoading(true);
            var formData = new FormData();
            formData.append('mod_action', 'moderation_decision');
            formData.append('ajax_modal', '1');
            formData.append('target_type', type);
            formData.append('target_id', id);
            formData.append('decision', decision);
            formData.append('note', note);
            formData.append('reject_reason', rejectReason);

            fetch('/moderation-panel.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
                .then(function (res) { return res.json(); })
                .then(function (payload) {
                    if (!payload || !payload.success) {
                        var errorText = payload && payload.messageText ? payload.messageText : 'Не вдалося виконати дію модерації.';
                        if (rejectModal && rejectModal.classList.contains('is-open')) {
                            showRejectModalError(errorText);
                        } else {
                            showModalFlash(payload && payload.alertHtml ? payload.alertHtml : '<div class="mod-alert mod-alert--error">' + escapeHtml(errorText) + '</div>');
                        }
                        return;
                    }
                    if (payload.item) {
                        fillCardElement(currentCard, payload.item);
                        var journalPayload = Object.assign({}, payload.item);
                        if (payload.journalActor) {
                            journalPayload.author = payload.journalActor;
                        }
                        updateJournalRealtime(journalPayload, payload.decisionAtIso || '', payload.decisionAtDisplay || '');
                    }
                    updateBannerStats();
                    applyFilters();

                    if (closeRejectOnSuccess) {
                        closeRejectModal();
                    }

                    if (closeEntryOnSuccess) {
                        closeEntryModal();
                    } else if (payload.item) {
                        openEntryModal(currentCard);
                    }

                    showGlobalAlert(
                        payload.messageText || (decision === 'approved' ? 'Запис схвалено.' : 'Запис відхилено.'),
                        payload.messageType || 'success'
                    );
                })
                .catch(function () {
                    var networkError = 'Помилка мережі під час модерації.';
                    if (rejectModal && rejectModal.classList.contains('is-open')) {
                        showRejectModalError(networkError);
                    } else {
                        showModalFlash('<div class="mod-alert mod-alert--error">' + networkError + '</div>');
                    }
                })
                .finally(function () {
                    setDecisionButtonsLoading(false);
                    setRejectModalLoading(false);
                });
        }

        function openEntryEditMode(card) {
            if (!card || !entryModalView) {
                return;
            }
            currentCard = card;
            setEntryModalMode('edit');
            showModalFlash('');
            entryModalView.innerHTML = '<div class="mod-entry-modal__loading">Завантаження форми...</div>';

            fetch(buildEditFormUrl(card), { credentials: 'same-origin' })
                .then(function (res) { return res.json(); })
                .then(function (payload) {
                    if (!payload.success) {
                        throw new Error(payload.message || 'Помилка завантаження форми');
                    }
                    entryModalView.innerHTML = (payload.alertHtml || '') + (payload.formHtml || '');
                    bindModalEditForm(entryModalView.querySelector('form'));
                })
                .catch(function (error) {
                    setEntryModalMode('view');
                    if (currentCard) {
                        openEntryModal(currentCard);
                    }
                    showModalFlash('<div class="mod-alert mod-alert--error">' + escapeHtml(error && error.message ? error.message : 'Не вдалося завантажити форму редагування.') + '</div>');
                });
        }

        function bindCardClick(card) {
            if (!card || card.dataset.modalBound === '1') {
                return;
            }
            card.addEventListener('click', function (event) {
                if (event.target.closest('a')) {
                    return;
                }
                openEntryModal(card);
            });
            card.dataset.modalBound = '1';
        }

        function createCardElement(payload) {
            var card = document.createElement('article');
            card.className = 'mod-entry-card';

            var bodyHtml = '';
            if (payload.type === 'grave') {
                bodyHtml = '<div class="mod-entry-card__info-row"><span class="mod-entry-card__info-item">' + cardInfoItemContentHtml('map-pin', '-') + '</span><span class="mod-entry-card__info-item">' + cardInfoItemContentHtml('calendar', '-') + '</span></div>' + cardTextLineHtml('map', '-');
            } else {
                bodyHtml = cardTextLineHtml('map', '-') + cardTextLineHtml('map-pin', '-', 'mod-entry-card__text-line--muted');
            }

            card.innerHTML = '<div class="mod-entry-card__head">' +
                '<span class="mod-entry-card__media mod-entry-card__media--icon mod-entry-card__media--' + escapeHtml(payload.type || 'grave') + '">' + iconMarkup(payload.type || 'grave') + '</span>' +
                '<div class="mod-entry-card__titles"><div class="mod-entry-card__title-row"><h3>-</h3><span class="mod-entry-card__id">ID -</span></div><span class="mod-entry-card__subtitle">-</span></div>' +
                '<span class="mod-entry-card__status">-</span></div>' +
                '<div class="mod-entry-card__body">' + bodyHtml + '</div>' +
                '<div class="mod-entry-card__footer"><div class="mod-entry-card__author-wrap">' + cardAuthorHtml('-') + '</div><span class="mod-entry-card__date">-</span></div>';

            fillCardElement(card, payload);
            bindCardClick(card);
            return card;
        }

        function findCardByPayload(payload) {
            var keyId = String(payload.id || '');
            var keyType = String(payload.type || '');
            return cards.find(function (card) {
                return (card.getAttribute('data-id') || '') === keyId && (card.getAttribute('data-type') || '') === keyType;
            }) || null;
        }

        function upsertCardFromPayload(payload, prepend) {
            if (!payload || !payload.id || !payload.type) {
                return null;
            }
            var card = findCardByPayload(payload);
            if (card) {
                fillCardElement(card, payload);
                return card;
            }
            if (!entryList) {
                return null;
            }

            card = createCardElement(payload);
            if (prepend) {
                entryList.insertBefore(card, entryList.firstChild);
                cards.unshift(card);
            } else {
                entryList.appendChild(card);
                cards.push(card);
            }

            var emptyNode = entryList.querySelector('.mod-empty');
            if (emptyNode) {
                emptyNode.remove();
            }
            return card;
        }

        cards.forEach(bindCardClick);

        closeEntryModalNodes.forEach(function (node) {
            node.addEventListener('click', closeEntryModal);
        });

        closePhotoModalNodes.forEach(function (node) {
            node.addEventListener('click', closePhotoModal);
        });

        closeRejectModalNodes.forEach(function (node) {
            node.addEventListener('click', closeRejectModal);
        });

        if (entryModalEdit) {
            entryModalEdit.addEventListener('click', function (event) {
                event.preventDefault();
                if (!currentCard) {
                    return;
                }
                openEntryEditMode(currentCard);
            });
        }

        if (entryModalCancelEdit) {
            entryModalCancelEdit.addEventListener('click', function () {
                setEntryModalMode('view');
                if (currentCard) {
                    openEntryModal(currentCard);
                }
            });
        }

        if (entryModalSaveEdit) {
            entryModalSaveEdit.addEventListener('click', function () {
                if (!entryModalView) {
                    return;
                }
                var form = entryModalView.querySelector('form');
                if (!form) {
                    return;
                }
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
                }
            });
        }

        if (entryModalApproveBtn) {
            entryModalApproveBtn.addEventListener('click', function () {
                submitModerationDecision('approved');
            });
        }

        if (entryModalRejectBtn) {
            entryModalRejectBtn.addEventListener('click', function () {
                openRejectModal();
            });
        }

        if (rejectModalConfirm) {
            rejectModalConfirm.addEventListener('click', function () {
                var selectedReason = getCheckedRejectReasonChoice();
                if (!selectedReason) {
                    showRejectModalError('Оберіть причину відхилення.');
                    return;
                }
                var rejectReason = selectedReason;
                if (selectedReason === '__other__') {
                    rejectReason = rejectModalReason ? String(rejectModalReason.value || '').trim() : '';
                    if (!rejectReason) {
                        showRejectModalError('Вкажіть іншу причину відхилення.');
                        if (rejectModalReason) {
                            rejectModalReason.focus();
                        }
                        return;
                    }
                }
                if (!rejectReason) {
                    showRejectModalError('Вкажіть причину відхилення.');
                    if (rejectModalReason) {
                        rejectModalReason.focus();
                    }
                    return;
                }
                showRejectModalError('');
                submitModerationDecision('rejected', {
                    rejectReason: rejectReason,
                    closeEntryOnSuccess: true,
                    closeRejectOnSuccess: true
                });
            });
        }

        if (rejectReasonOptions.length) {
            rejectReasonOptions.forEach(function (input) {
                input.addEventListener('change', function () {
                    toggleRejectOtherField();
                    showRejectModalError('');
                });
            });
        }

        if (rejectModalReason) {
            rejectModalReason.addEventListener('input', function () {
                showRejectModalError('');
            });
        }

        function initEditForm(form) {
            if (!form) {
                return;
            }

            var region = form.querySelector('[data-role="region"]');
            var district = form.querySelector('[data-role="district"]');
            var town = form.querySelector('[data-role="town"]');
            var cemetery = form.querySelector('[data-role="cemetery"]');
            var gpsxInput = form.querySelector('#mod-cemetery-gpsx');
            var gpsyInput = form.querySelector('#mod-cemetery-gpsy');
            var openMapBtn = form.querySelector('#mod-cemetery-open-map');
            var placeholderById = {
                'mod-grave-region': 'Оберіть область',
                'mod-grave-district': 'Оберіть район',
                'mod-grave-town': 'Оберіть населений пункт',
                'mod-grave-cemetery': 'Оберіть кладовище',
                'mod-cemetery-region': 'Оберіть область',
                'mod-cemetery-district': 'Оберіть район',
                'mod-cemetery-town': 'Оберіть населений пункт'
            };
            var initialDistrict = district ? (district.dataset.selected || district.value || '') : '';
            var initialTown = town ? (town.dataset.selected || town.value || '') : '';
            var initialCemetery = cemetery ? (cemetery.dataset.selected || cemetery.value || '') : '';

            function closeAllCustomSelects(exceptWrapper) {
                document.querySelectorAll('.mod-field .custom-select-wrapper.open').forEach(function (wrapper) {
                    if (exceptWrapper && wrapper === exceptWrapper) {
                        return;
                    }
                    wrapper.classList.remove('open');
                    var optionsBox = wrapper.querySelector('.custom-options');
                    if (optionsBox) {
                        optionsBox.style.display = 'none';
                    }
                });
            }

            function getCustomWrapper(selectEl) {
                return selectEl && selectEl.parentNode ? selectEl.parentNode.querySelector('.custom-select-wrapper[data-select-id="' + selectEl.id + '"]') : null;
            }

            function ensureCustomSelect(selectEl) {
                if (!selectEl || !selectEl.id) {
                    return null;
                }
                var wrapper = getCustomWrapper(selectEl);
                if (wrapper) {
                    return wrapper;
                }
                wrapper = document.createElement('div');
                wrapper.className = 'custom-select-wrapper';
                wrapper.dataset.selectId = selectEl.id;

                var trigger = document.createElement('div');
                trigger.className = 'custom-select-trigger';
                var optionsBox = document.createElement('div');
                optionsBox.className = 'custom-options';

                trigger.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                });

                trigger.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    if (selectEl.disabled) {
                        return;
                    }
                    var willOpen = !wrapper.classList.contains('open');
                    closeAllCustomSelects(wrapper);
                    wrapper.classList.toggle('open', willOpen);
                    optionsBox.style.display = willOpen ? 'flex' : 'none';
                });

                wrapper.appendChild(trigger);
                wrapper.appendChild(optionsBox);
                selectEl.style.display = 'none';
                if (selectEl.nextSibling) {
                    selectEl.parentNode.insertBefore(wrapper, selectEl.nextSibling);
                } else {
                    selectEl.parentNode.appendChild(wrapper);
                }
                return wrapper;
            }

            function syncCustomSelect(selectEl) {
                if (!selectEl) {
                    return;
                }
                var wrapper = ensureCustomSelect(selectEl);
                if (!wrapper) {
                    return;
                }
                var trigger = wrapper.querySelector('.custom-select-trigger');
                var optionsBox = wrapper.querySelector('.custom-options');
                var options = Array.prototype.slice.call(selectEl.options || []);
                var placeholder = placeholderById[selectEl.id] || 'Оберіть';
                optionsBox.innerHTML = '';

                var selectedOption = options.find(function (opt) {
                    return opt.value !== '' && opt.value === selectEl.value;
                });
                trigger.textContent = selectedOption ? selectedOption.textContent : ((options[0] && options[0].textContent) ? options[0].textContent : placeholder);

                options.forEach(function (opt) {
                    var optionNode = document.createElement('span');
                    optionNode.textContent = opt.textContent;
                    if (!opt.value) {
                        optionNode.className = 'custom-option disabled';
                        optionsBox.appendChild(optionNode);
                        return;
                    }
                    optionNode.className = 'custom-option';
                    if (opt.value === selectEl.value) {
                        optionNode.classList.add('is-selected');
                    }
                    optionNode.addEventListener('click', function (event) {
                        event.preventDefault();
                        event.stopPropagation();
                        if (selectEl.disabled) {
                            return;
                        }
                        selectEl.value = opt.value;
                        syncCustomSelect(selectEl);
                        closeAllCustomSelects();
                        selectEl.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                    optionsBox.appendChild(optionNode);
                });
                wrapper.classList.toggle('disabled', !!selectEl.disabled);
            }

            function setOptionsState(select, html, disabled) {
                if (!select) {
                    return;
                }
                select.innerHTML = html;
                select.disabled = !!disabled;
                syncCustomSelect(select);
            }

            function bindPositiveNumber(input) {
                if (!input) {
                    return;
                }
                function normalize() {
                    var next = String(input.value || '').replace(/\D+/g, '');
                    next = next.replace(/^0+/, '');
                    input.value = next;
                }
                input.addEventListener('input', normalize);
                input.addEventListener('blur', normalize);
                normalize();
            }

            function loadDistricts(selectedDistrict, callback) {
                if (!region || !district) {
                    return;
                }
                if (!region.value) {
                    setOptionsState(district, '<option value="">Спочатку оберіть область</option>', true);
                    if (town) setOptionsState(town, '<option value="">Оберіть район</option>', true);
                    if (cemetery) setOptionsState(cemetery, '<option value="">Спочатку оберіть район</option>', true);
                    return;
                }
                setOptionsState(district, '<option value="">Завантаження...</option>', true);
                fetch('/moderation-panel.php?ajax_districts=1&region_id=' + encodeURIComponent(region.value), { credentials: 'same-origin' })
                    .then(function (res) { return res.text(); })
                    .then(function (html) {
                        setOptionsState(district, html, false);
                        if (selectedDistrict) {
                            district.value = selectedDistrict;
                        }
                        syncCustomSelect(district);
                        if (typeof callback === 'function') {
                            callback();
                        }
                    })
                    .catch(function () {
                        setOptionsState(district, '<option value="">Помилка завантаження</option>', true);
                    });
            }

            function loadTowns(selectedTown, callback) {
                if (!region || !district || !town) {
                    return;
                }
                if (!region.value || !district.value) {
                    setOptionsState(town, '<option value="">Оберіть район</option>', true);
                    if (typeof callback === 'function') {
                        callback();
                    }
                    return;
                }
                setOptionsState(town, '<option value="">Завантаження...</option>', true);
                fetch('/moderation-panel.php?ajax_settlements=1&region_id=' + encodeURIComponent(region.value) + '&district_id=' + encodeURIComponent(district.value), { credentials: 'same-origin' })
                    .then(function (res) { return res.text(); })
                    .then(function (html) {
                        setOptionsState(town, html, false);
                        if (selectedTown) {
                            town.value = selectedTown;
                        }
                        syncCustomSelect(town);
                        if (typeof callback === 'function') {
                            callback();
                        }
                    })
                    .catch(function () {
                        setOptionsState(town, '<option value="">Помилка завантаження</option>', true);
                    });
            }

            function loadCemeteries(selectedCemetery) {
                if (!cemetery || !district) {
                    return;
                }
                if (!district.value) {
                    setOptionsState(cemetery, '<option value="">Спочатку оберіть район</option>', true);
                    return;
                }
                setOptionsState(cemetery, '<option value="">Завантаження...</option>', true);
                fetch('/moderation-panel.php?ajax_cemeteries=1&district_id=' + encodeURIComponent(district.value), { credentials: 'same-origin' })
                    .then(function (res) { return res.text(); })
                    .then(function (html) {
                        setOptionsState(cemetery, html, false);
                        if (selectedCemetery) {
                            cemetery.value = selectedCemetery;
                        }
                        syncCustomSelect(cemetery);
                    })
                    .catch(function () {
                        setOptionsState(cemetery, '<option value="">Помилка завантаження</option>', true);
                    });
            }

            if (region) {
                region.addEventListener('change', function () {
                    loadDistricts('', function () {
                        loadTowns('', function () {
                            loadCemeteries('');
                        });
                    });
                });
            }
            if (district) {
                district.addEventListener('change', function () {
                    loadTowns('', function () {
                        loadCemeteries('');
                    });
                });
            }

            Array.prototype.slice.call(form.querySelectorAll('[data-positive-number="1"]')).forEach(bindPositiveNumber);
            Array.prototype.slice.call(form.querySelectorAll('select[data-role]')).forEach(syncCustomSelect);

            if (region && region.value && district) {
                loadDistricts(initialDistrict, function () {
                    loadTowns(initialTown, function () {
                        loadCemeteries(initialCemetery);
                    });
                });
            }

            var filePairs = Array.prototype.slice.call(form.querySelectorAll('[data-upload-card]')).map(function (card) {
                var input = card.querySelector('input[type="file"]');
                if (!input) {
                    return null;
                }
                return {
                    input: input,
                    card: card,
                    image: card.querySelector('.agf-upload-preview img'),
                    trigger: card.querySelector('.acm-file-btn'),
                    viewButton: card.querySelector('.agf-view-btn'),
                    badge: card.querySelector('.agf-upload-badge'),
                    dropzone: card.querySelector('.agf-upload-dropzone'),
                    title: card.querySelector('.acm-file-title')
                };
            }).filter(Boolean);

            function setUploadEmpty(pair) {
                if (!pair.card.dataset.previewSrc) {
                    pair.image.removeAttribute('src');
                    pair.card.classList.remove('has-preview');
                    if (pair.badge) pair.badge.hidden = true;
                    if (pair.viewButton) {
                        pair.viewButton.hidden = true;
                        pair.viewButton.disabled = true;
                    }
                    if (pair.trigger) pair.trigger.textContent = 'Вибрати фото';
                }
            }

            function enableExistingPreview(pair) {
                if (!pair.card.dataset.previewSrc) {
                    setUploadEmpty(pair);
                    return;
                }
                pair.card.classList.add('has-preview');
                if (pair.badge) pair.badge.hidden = false;
                if (pair.viewButton) {
                    pair.viewButton.hidden = false;
                    pair.viewButton.disabled = false;
                }
                if (pair.trigger) pair.trigger.textContent = 'Змінити';
            }

            function setUploadPreview(pair, file) {
                if (!file) {
                    return;
                }
                if (pair.trigger) pair.trigger.textContent = 'Змінити';
                if (!file.type || file.type.toLowerCase().indexOf('image/') !== 0) {
                    pair.card.classList.remove('has-preview');
                    pair.card.dataset.previewSrc = '';
                    if (pair.badge) pair.badge.hidden = true;
                    if (pair.viewButton) {
                        pair.viewButton.hidden = true;
                        pair.viewButton.disabled = true;
                    }
                    return;
                }
                var reader = new FileReader();
                reader.onload = function (event) {
                    var result = event && event.target ? event.target.result : '';
                    if (!result) {
                        return;
                    }
                    pair.image.src = result;
                    pair.card.dataset.previewSrc = result;
                    pair.card.classList.add('has-preview');
                    if (pair.badge) pair.badge.hidden = false;
                    if (pair.viewButton) {
                        pair.viewButton.hidden = false;
                        pair.viewButton.disabled = false;
                    }
                };
                reader.readAsDataURL(file);
            }

            filePairs.forEach(function (pair) {
                enableExistingPreview(pair);
                if (pair.trigger) {
                    pair.trigger.addEventListener('click', function () {
                        pair.input.click();
                    });
                }
                if (pair.viewButton) {
                    pair.viewButton.addEventListener('click', function () {
                        var src = pair.card.dataset.previewSrc || '';
                        openPhotoModal(src, pair.title ? pair.title.textContent : 'Перегляд фото');
                    });
                }
                pair.input.addEventListener('change', function () {
                    var file = pair.input.files && pair.input.files[0] ? pair.input.files[0] : null;
                    if (!file) {
                        if (pair.card.dataset.previewSrc) {
                            enableExistingPreview(pair);
                        } else {
                            setUploadEmpty(pair);
                        }
                        return;
                    }
                    setUploadPreview(pair, file);
                });
                if (pair.dropzone) {
                    pair.dropzone.addEventListener('dragover', function (event) {
                        event.preventDefault();
                        pair.card.classList.add('dragover');
                    });
                    pair.dropzone.addEventListener('dragleave', function () {
                        pair.card.classList.remove('dragover');
                    });
                    pair.dropzone.addEventListener('drop', function (event) {
                        event.preventDefault();
                        pair.card.classList.remove('dragover');
                        var files = event.dataTransfer && event.dataTransfer.files ? event.dataTransfer.files : null;
                        if (!files || !files[0]) {
                            return;
                        }
                        var droppedFile = files[0];
                        try {
                            var dt = new DataTransfer();
                            dt.items.add(droppedFile);
                            pair.input.files = dt.files;
                            pair.input.dispatchEvent(new Event('change', { bubbles: true }));
                        } catch (error) {
                            setUploadPreview(pair, droppedFile);
                        }
                    });
                }
            });

            if (mapModal && !mapModal.dataset.bound) {
                closeMapModalNodes.forEach(function (node) {
                    node.addEventListener('click', closeMapModal);
                });
                if (applyMapBtn) {
                    applyMapBtn.addEventListener('click', function () {
                        if (typeof mapApplyHandler === 'function') {
                            mapApplyHandler();
                        }
                    });
                }
                mapModal.dataset.bound = '1';
            }

            if (openMapBtn) {
                openMapBtn.addEventListener('click', function () {
                    mapApplyHandler = function () {
                        if (!mapSelected || !gpsxInput || !gpsyInput) {
                            setMapHint('Спочатку оберіть точку на карті.', true);
                            return;
                        }
                        gpsxInput.value = formatCoord(mapSelected.lon);
                        gpsyInput.value = formatCoord(mapSelected.lat);
                        closeMapModal();
                    };
                    openMapModalWithCoords(gpsxInput ? gpsxInput.value : '', gpsyInput ? gpsyInput.value : '', false);
                });
            }
            if (!document.body.dataset.modGlobalUiBound) {
                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape') {
                        closeAllCustomSelects();
                        closeEntryModal();
                        closePhotoModal();
                        closeMapModal();
                    }
                });
                document.addEventListener('click', function (event) {
                    if (!event.target.closest('.mod-field .custom-select-wrapper')) {
                        closeAllCustomSelects();
                    }
                });
                document.body.dataset.modGlobalUiBound = '1';
            }
        }

        function initNotifyForm() {
            if (!notifyView) {
                return;
            }
            var form = notifyView.querySelector('.mod-notify-form');
            if (!form) {
                return;
            }
            var selects = Array.prototype.slice.call(form.querySelectorAll('select'));
            if (!selects.length) {
                return;
            }

            function closeNotifySelects(exceptWrapper) {
                form.querySelectorAll('.custom-select-wrapper.open').forEach(function (wrapper) {
                    if (exceptWrapper && wrapper === exceptWrapper) {
                        return;
                    }
                    wrapper.classList.remove('open');
                    var optionsBox = wrapper.querySelector('.custom-options');
                    if (optionsBox) {
                        optionsBox.style.display = 'none';
                    }
                });
            }

            function getNotifyWrapper(selectEl) {
                return selectEl && selectEl.parentNode
                    ? selectEl.parentNode.querySelector('.custom-select-wrapper[data-select-id="' + selectEl.id + '"]')
                    : null;
            }

            function ensureNotifySelect(selectEl) {
                if (!selectEl || !selectEl.id) {
                    return null;
                }
                var wrapper = getNotifyWrapper(selectEl);
                if (wrapper) {
                    return wrapper;
                }
                wrapper = document.createElement('div');
                wrapper.className = 'custom-select-wrapper';
                wrapper.dataset.selectId = selectEl.id;

                var trigger = document.createElement('div');
                trigger.className = 'custom-select-trigger';
                var optionsBox = document.createElement('div');
                optionsBox.className = 'custom-options';

                trigger.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                });

                trigger.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    if (selectEl.disabled) {
                        return;
                    }
                    var willOpen = !wrapper.classList.contains('open');
                    closeNotifySelects(wrapper);
                    wrapper.classList.toggle('open', willOpen);
                    optionsBox.style.display = willOpen ? 'flex' : 'none';
                });

                wrapper.appendChild(trigger);
                wrapper.appendChild(optionsBox);
                selectEl.style.display = 'none';
                if (selectEl.nextSibling) {
                    selectEl.parentNode.insertBefore(wrapper, selectEl.nextSibling);
                } else {
                    selectEl.parentNode.appendChild(wrapper);
                }
                return wrapper;
            }

            function syncNotifySelect(selectEl) {
                if (!selectEl) {
                    return;
                }
                var wrapper = ensureNotifySelect(selectEl);
                if (!wrapper) {
                    return;
                }
                var trigger = wrapper.querySelector('.custom-select-trigger');
                var optionsBox = wrapper.querySelector('.custom-options');
                var options = Array.prototype.slice.call(selectEl.options || []);
                optionsBox.innerHTML = '';

                var selectedOption = options.find(function (opt) {
                    return opt.value !== '' && opt.value === selectEl.value;
                });
                trigger.textContent = selectedOption ? selectedOption.textContent : ((options[0] && options[0].textContent) ? options[0].textContent : 'Оберіть');

                options.forEach(function (opt) {
                    var optionNode = document.createElement('span');
                    optionNode.textContent = opt.textContent;
                    if (!opt.value) {
                        optionNode.className = 'custom-option disabled';
                        optionsBox.appendChild(optionNode);
                        return;
                    }
                    optionNode.className = 'custom-option';
                    if (opt.value === selectEl.value) {
                        optionNode.classList.add('is-selected');
                    }
                    optionNode.addEventListener('click', function (event) {
                        event.preventDefault();
                        event.stopPropagation();
                        if (selectEl.disabled) {
                            return;
                        }
                        selectEl.value = opt.value;
                        syncNotifySelect(selectEl);
                        closeNotifySelects();
                        selectEl.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                    optionsBox.appendChild(optionNode);
                });
                wrapper.classList.toggle('disabled', !!selectEl.disabled);
            }

            selects.forEach(function (selectEl) {
                syncNotifySelect(selectEl);
            });

            if (!document.body.dataset.modNotifySelectBound) {
                document.addEventListener('click', function (event) {
                    if (!event.target.closest('.mod-notify-form .custom-select-wrapper')) {
                        closeNotifySelects();
                    }
                });
                document.body.dataset.modNotifySelectBound = '1';
            }
        }

        initEditForm(document.getElementById('modGraveForm'));
        initEditForm(document.getElementById('modCemeteryForm'));
        initNotifyForm();
        setActivePanel(activePanel || 'moderation');
        updateBannerStats();

        if (panelView === 'list') {
            applyFilters();
            startPendingFeedPolling();
            setTimeout(fetchPendingFeed, 3500);
        }
        syncStateToUrl();
    });
})();
