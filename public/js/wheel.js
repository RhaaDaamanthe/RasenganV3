document.addEventListener("DOMContentLoaded", () => {
    const page = document.querySelector(".wheel-page");
    if (!page) {
        return;
    }

    const csrfToken = page.dataset.csrf;
    const rarities = JSON.parse(page.dataset.rarities);
    const rarities333 = JSON.parse(page.dataset.rarities333);
    const spinRarityUrl = page.dataset.spinRarityUrl;
    const spinCardUrl = page.dataset.spinCardUrl;
    const confirmUrl = page.dataset.confirmUrl;
    const doubleChanceUrl = page.dataset.doubleChanceUrl;

    const modeSelect = document.getElementById("mode-select");
    const titleSelect = document.getElementById("title-select");
    const countSelect = document.getElementById("count-select");
    const countField = document.getElementById("count-field");
    const minRaritySelect = document.getElementById("min-rarity-select");
    const minRarityField = document.getElementById("min-rarity-field");

    const stepDoubleChoice = document.getElementById("step-double-choice");
    const doubleChoiceSafeBtn = document.getElementById("double-choice-safe-btn");
    const doubleChoiceGambleBtn = document.getElementById("double-choice-gamble-btn");
    const doubleChoiceError = document.getElementById("double-choice-error");

    const stepRarity = document.getElementById("step-rarity");
    const rarityStepTitle = document.getElementById("rarity-step-title");
    const rarityReels = document.getElementById("rarity-reels");
    const spinRarityBtn = document.getElementById("spin-rarity-btn");

    const stepCard = document.getElementById("step-card");
    const cardViewport = document.querySelector("#step-card .wheel-viewport");
    const cardStrip = document.getElementById("card-strip");
    const spinCardBtn = document.getElementById("spin-card-btn");
    const redoubleBtn = document.getElementById("step-card-redouble-btn");
    const drawNumberLabel = document.getElementById("draw-number-label");
    const rarityResultLabel = document.getElementById("rarity-result-label");
    const cardErrorMessage = document.getElementById("card-error-message");

    const wheelResult = document.getElementById("wheel-result");
    const resultImage = document.getElementById("result-image");
    const resultName = document.getElementById("result-name");
    const wishlistHit = document.getElementById("wheel-wishlist-hit");
    const decisionActions = document.getElementById("wheel-decision-actions");
    const afterActions = document.getElementById("wheel-after-actions");
    const cancelBtn = document.getElementById("cancel-btn");
    const confirmBtn = document.getElementById("confirm-btn");
    const nextDrawBtn = document.getElementById("next-draw-btn");
    const retryBtn = document.getElementById("retry-btn");
    const statusMessage = document.getElementById("wheel-status-message");

    const ITEM_WIDTH = 140;
    const REEL_LENGTH = 40;
    const WINNING_INDEX = 36;

    const RARITY_LABELS = { 1: "Commune", 2: "Rare", 3: "Épique" };

    // Tirages de rareté en cours : un par roue affichée à l'étape 1.
    // status : "pending" (carte à tirer), "done" (carte attribuée), "skipped" (annulé).
    let draws = [];
    let currentDrawIndex = null;
    let busy = false;

    let currentRarityId = null;
    let currentWinnerCardId = null;
    let currentWinnerName = null;

    function post(url, params) {
        const body = new URLSearchParams({ _token: csrfToken, ...params });

        return fetch(url, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: body.toString(),
        }).then(response => response.json());
    }

    function renderRarityItem(rarity) {
        const div = document.createElement("div");
        div.className = "wheel-item wheel-item-rarity rarity-" + rarity.id;
        const span = document.createElement("span");
        span.textContent = rarity.libelle;
        div.appendChild(span);
        return div;
    }

    function renderCardItem(card) {
        const div = document.createElement("div");
        div.className = "wheel-item wheel-item-card";
        if (card.imagePath) {
            const img = document.createElement("img");
            img.src = card.imagePath;
            img.alt = "";
            div.appendChild(img);
        }
        const span = document.createElement("span");
        span.textContent = card.nom;
        div.appendChild(span);
        return div;
    }

    // Reproduit côté client la même logique que WheelOdds::weightsForMinimumRarity() :
    // le taux de Légendaire (id 4) reste toujours à son poids de base, seules les
    // raretés en dessous se redistribuent le reste entre elles.
    function getWeightedRarities(baseTable, minRarityId) {
        if (!minRarityId) {
            return baseTable;
        }

        const floor = parseInt(minRarityId, 10);
        const filtered = baseTable.filter(r => r.id >= floor);

        if (filtered.length === 0) {
            return baseTable;
        }

        if (filtered.length === 1) {
            return [{ ...filtered[0], weight: 100 }];
        }

        const legendary = filtered.find(r => r.id === 4);
        if (legendary) {
            const others = filtered.filter(r => r.id !== 4);
            const othersTotal = others.reduce((sum, r) => sum + r.weight, 0);
            const remainingBudget = 100 - legendary.weight;

            const adjustedOthers = othersTotal > 0
                ? others.map(r => ({ ...r, weight: (r.weight / othersTotal) * remainingBudget }))
                : [];

            return [...adjustedOthers, { ...legendary, weight: legendary.weight }]
                .sort((a, b) => a.id - b.id);
        }

        const total = filtered.reduce((sum, r) => sum + r.weight, 0);
        return filtered.map(r => ({ ...r, weight: (r.weight / total) * 100 }));
    }

    function getBaseRarityTable() {
        return modeSelect.value === "333" ? rarities333 : rarities;
    }

    function drawCount() {
        return modeSelect.value === "double" ? 1 : parseInt(countSelect.value, 10) || 1;
    }

    // Affiche l'étape "1. Choix du risque" (Quitte ou double) ou "1. Rareté" (Classique/333).
    function updateModeVisibility() {
        const isDoubleMode = modeSelect.value === "double";
        stepDoubleChoice.style.display = isDoubleMode ? "" : "none";
        stepRarity.style.display = isDoubleMode ? "none" : "";
        minRarityField.style.display = isDoubleMode ? "none" : "";
        countField.style.display = isDoubleMode ? "none" : "";
        spinRarityBtn.textContent = drawCount() > 1
            ? `Lancer les ${drawCount()} roues des raretés`
            : "Lancer la roue des raretés";
    }

    function buildStripItems(pool, winner) {
        const effectivePool = pool.length > 0 ? pool : [winner];
        const items = [];
        for (let i = 0; i < REEL_LENGTH; i++) {
            items.push(i === WINNING_INDEX ? winner : effectivePool[Math.floor(Math.random() * effectivePool.length)]);
        }
        return items;
    }

    function spinReel(viewport, strip, items, renderItem) {
        return new Promise((resolve) => {
            strip.style.transition = "none";
            strip.style.transform = "translateX(0)";
            strip.innerHTML = "";
            items.forEach(item => strip.appendChild(renderItem(item)));

            // Force le recalcul du layout avant de (re)démarrer la transition
            void strip.offsetWidth;

            const viewportWidth = viewport.clientWidth;
            const target = viewportWidth / 2 - (WINNING_INDEX * ITEM_WIDTH + ITEM_WIDTH / 2);

            requestAnimationFrame(() => {
                strip.style.transition = "transform 2.5s cubic-bezier(0.15, 0.85, 0.2, 1)";
                strip.style.transform = `translateX(${target}px)`;
            });

            const onEnd = (e) => {
                if (e.propertyName !== "transform") {
                    return;
                }
                strip.removeEventListener("transitionend", onEnd);
                resolve();
            };
            strip.addEventListener("transitionend", onEnd);
        });
    }

    // Replie une section (bascule "expanded" à false) ; ne fait rien si déjà repliée.
    function collapse(section) {
        section.classList.remove("expanded");
    }

    // Déplie une section. Toujours rejouée : on force un reflow pour que la transition
    // se relance même si la section était déjà dépliée (ex: nouveau tirage de rareté).
    function expand(section) {
        section.classList.remove("expanded");
        void section.offsetWidth;
        requestAnimationFrame(() => {
            section.classList.add("expanded");
        });
    }

    // Le <select> du titre est remplacé par un champ de recherche (select_recherche.js) :
    // c'est ce champ qu'il faut désactiver.
    function titleInput() {
        return titleSelect.closest(".select-recherche")?.querySelector("input") ?? titleSelect;
    }

    function lockOptions() {
        modeSelect.disabled = true;
        titleSelect.disabled = true;
        titleInput().disabled = true;
        countSelect.disabled = true;
        minRaritySelect.disabled = true;
    }

    function unlockOptions() {
        modeSelect.disabled = false;
        titleSelect.disabled = false;
        titleInput().disabled = false;
        countSelect.disabled = false;
        minRaritySelect.disabled = false;
    }

    // --- Roues de rareté (une par tirage) ---

    // Construit `count` roues vides. Avec une seule roue, l'en-tête (numéro, relance) est masqué :
    // la page se comporte comme un tirage simple.
    function buildRarityRows(count) {
        rarityReels.innerHTML = "";
        rarityReels.classList.toggle("wheel-draws-multi", count > 1);

        const rows = [];
        for (let i = 0; i < count; i++) {
            const row = document.createElement("div");
            row.className = "wheel-draw";

            const header = document.createElement("div");
            header.className = "wheel-draw-header";

            const title = document.createElement("span");
            title.className = "wheel-draw-title";
            title.textContent = `Tirage n°${i + 1}`;

            const status = document.createElement("span");
            status.className = "wheel-draw-status";

            const actions = document.createElement("span");
            actions.className = "wheel-draw-actions";

            const selectBtn = document.createElement("button");
            selectBtn.type = "button";
            selectBtn.className = "btn btn-action btn-small";
            selectBtn.textContent = "🎴 Tirer la carte";
            selectBtn.disabled = true;
            selectBtn.addEventListener("click", () => selectDraw(i));

            const rerollBtn = document.createElement("button");
            rerollBtn.type = "button";
            rerollBtn.className = "btn btn-secondary btn-small";
            rerollBtn.textContent = "🔁 Relancer";
            rerollBtn.title = "Relancer la rareté de ce tirage";
            rerollBtn.disabled = true;
            rerollBtn.addEventListener("click", () => rerollDraw(i));

            actions.append(selectBtn, rerollBtn);
            header.append(title, status, actions);

            const viewport = document.createElement("div");
            viewport.className = "wheel-viewport" + (count > 1 ? " wheel-viewport-compact" : "");
            const strip = document.createElement("div");
            strip.className = "wheel-strip";
            viewport.appendChild(strip);

            row.append(header, viewport);
            rarityReels.appendChild(row);
            rows.push({ row, status, selectBtn, rerollBtn, viewport, strip });
        }

        return rows;
    }

    function spinRarityRow(rowEls, rarityId, libelle) {
        const baseTable = getBaseRarityTable();
        const winnerRarity = baseTable.find(r => r.id === rarityId) || { id: rarityId, libelle };
        const items = buildStripItems(getWeightedRarities(baseTable, minRaritySelect.value), winnerRarity);

        return spinReel(rowEls.viewport, rowEls.strip, items, renderRarityItem);
    }

    // Met à jour l'en-tête de chaque roue selon l'état du tirage.
    function refreshDrawRows() {
        draws.forEach((draw, i) => {
            const els = draw.els;
            els.row.classList.toggle("is-current", i === currentDrawIndex);
            els.row.classList.toggle("is-done", draw.status === "done");
            els.row.classList.toggle("is-skipped", draw.status === "skipped");

            els.status.textContent = draw.status === "done"
                ? `✔ ${draw.cardName}`
                : draw.status === "skipped" ? "✖ Annulé" : "";

            const locked = busy || draw.status !== "pending";
            els.selectBtn.disabled = locked || i === currentDrawIndex;
            els.rerollBtn.disabled = locked;
        });

        // On ne relance toutes les raretés que tant qu'aucune carte n'a été attribuée.
        spinRarityBtn.disabled = busy || draws.some(d => d.status === "done");
    }

    function nextPendingIndex() {
        const after = draws.findIndex((d, i) => d.status === "pending" && i > (currentDrawIndex ?? -1));
        return after !== -1 ? after : draws.findIndex(d => d.status === "pending");
    }

    // Prépare l'étape "2. Carte" pour le tirage n°index (sa rareté est connue).
    function selectDraw(index) {
        const draw = draws[index];
        if (!draw || draw.status !== "pending" || busy) {
            return;
        }

        currentDrawIndex = index;
        currentRarityId = draw.rarityId;
        currentWinnerCardId = null;
        drawNumberLabel.textContent = draws.length > 1 ? `— tirage n°${index + 1} ` : "";
        rarityResultLabel.textContent = draw.libelle;
        spinCardBtn.textContent = "Lancer la roue des cartes";
        spinCardBtn.disabled = false;
        redoubleBtn.style.display = "none";
        cardErrorMessage.style.display = "none";
        cardStrip.innerHTML = "";
        statusMessage.textContent = "";
        wishlistHit.hidden = true;
        decisionActions.style.display = "flex";
        afterActions.style.display = "none";
        nextDrawBtn.style.display = "none";
        cancelBtn.disabled = false;
        confirmBtn.disabled = false;

        refreshDrawRows();
        collapse(wheelResult);
        expand(stepCard);
    }

    function rerollDraw(index) {
        const draw = draws[index];
        if (!draw || draw.status !== "pending" || busy) {
            return;
        }

        busy = true;
        refreshDrawRows();
        if (index === currentDrawIndex) {
            collapse(stepCard);
            collapse(wheelResult);
        }

        post(spinRarityUrl, { mode: modeSelect.value, minRarityId: minRaritySelect.value, count: 1 })
            .then(data => {
                if (!data.success) {
                    statusMessage.textContent = data.message || "Impossible de relancer la rareté.";
                    return null;
                }

                const result = data.results[0];
                return spinRarityRow(draw.els, result.rarityId, result.libelle).then(() => {
                    draw.rarityId = result.rarityId;
                    draw.libelle = result.libelle;
                    return true;
                });
            })
            .catch(() => null)
            .then(ok => {
                busy = false;
                if (ok && index === currentDrawIndex) {
                    currentDrawIndex = null;
                    selectDraw(index);
                } else {
                    refreshDrawRows();
                }
            });
    }

    function resetFlow() {
        collapse(stepCard);
        collapse(wheelResult);
        rarityStepTitle.textContent = "1. Rareté";
        spinCardBtn.textContent = "Lancer la roue des cartes";
        redoubleBtn.style.display = "none";
        cardErrorMessage.style.display = "none";
        doubleChoiceError.style.display = "none";
        statusMessage.textContent = "";
        wishlistHit.hidden = true;
        drawNumberLabel.textContent = "";
        spinRarityBtn.style.display = "";
        spinRarityBtn.disabled = false;
        spinCardBtn.disabled = false;
        redoubleBtn.disabled = false;
        doubleChoiceSafeBtn.disabled = false;
        doubleChoiceGambleBtn.disabled = false;
        cancelBtn.disabled = false;
        confirmBtn.disabled = false;
        unlockOptions();
        decisionActions.style.display = "flex";
        afterActions.style.display = "none";
        nextDrawBtn.style.display = "none";
        cardStrip.innerHTML = "";
        draws = [];
        currentDrawIndex = null;
        busy = false;
        currentRarityId = null;
        currentWinnerCardId = null;
        currentWinnerName = null;
        updateModeVisibility();
        buildRarityRows(drawCount());
    }

    modeSelect.addEventListener("change", resetFlow);
    countSelect.addEventListener("change", resetFlow);

    // --- Tirage des raretés classique/333 (pondéré sur 4 raretés), 1 à 6 d'un coup ---

    spinRarityBtn.addEventListener("click", () => {
        const count = drawCount();

        busy = true;
        spinRarityBtn.disabled = true;
        lockOptions();
        statusMessage.textContent = "";

        // Chaque nouveau lancer replie la suite du parcours avant de la redéplier sur le résultat.
        collapse(stepCard);
        collapse(wheelResult);

        const rows = buildRarityRows(count);

        post(spinRarityUrl, { mode: modeSelect.value, minRarityId: minRaritySelect.value, count })
            .then(data => {
                if (!data.success) {
                    busy = false;
                    spinRarityBtn.disabled = false;
                    unlockOptions();
                    return;
                }

                draws = data.results.map((result, i) => ({
                    rarityId: result.rarityId,
                    libelle: result.libelle,
                    status: "pending",
                    cardName: null,
                    els: rows[i],
                }));
                currentDrawIndex = null;

                return Promise.all(draws.map(d => spinRarityRow(d.els, d.rarityId, d.libelle))).then(() => {
                    busy = false;
                    selectDraw(0);
                });
            })
            .catch(() => {
                busy = false;
                spinRarityBtn.disabled = false;
                unlockOptions();
            });
    });

    // --- Tirage de la carte, une fois une rareté déterminée (partagé par tous les modes) ---

    function fetchAndSpinCard(rarityId) {
        return post(spinCardUrl, { rarityId, titleId: titleSelect.value })
            .then(data => {
                if (!data.success) {
                    cardErrorMessage.textContent = data.message || "Impossible de lancer la roue des cartes.";
                    cardErrorMessage.style.display = "block";
                    return null;
                }

                const items = buildStripItems(data.pool, data.winner);

                return spinReel(cardViewport, cardStrip, items, renderCardItem).then(() => {
                    currentWinnerCardId = data.winner.id;
                    currentWinnerName = data.winner.nom;
                    resultName.textContent = data.winner.nom;
                    wishlistHit.hidden = !data.wishlisted;

                    if (data.winner.imagePath) {
                        resultImage.src = data.winner.imagePath;
                        resultImage.style.display = "block";
                    } else {
                        resultImage.style.display = "none";
                    }

                    return data;
                });
            })
            .catch(() => {
                cardErrorMessage.textContent = "Une erreur est survenue.";
                cardErrorMessage.style.display = "block";
                return null;
            });
    }

    // Relançable à volonté tant que la carte n'est pas attribuée.
    function performCardSpin() {
        if (!currentRarityId) {
            return;
        }

        busy = true;
        if (draws.length) {
            refreshDrawRows();
        }
        spinCardBtn.disabled = true;
        redoubleBtn.disabled = true;
        cardErrorMessage.style.display = "none";
        collapse(wheelResult);

        fetchAndSpinCard(currentRarityId)
            .then(data => {
                if (!data) {
                    return;
                }

                decisionActions.style.display = "flex";
                afterActions.style.display = "none";
                cancelBtn.disabled = false;
                confirmBtn.disabled = false;
                statusMessage.textContent = "";
                expand(wheelResult);
            })
            .finally(() => {
                busy = false;
                if (draws.length) {
                    refreshDrawRows();
                }
                spinCardBtn.disabled = false;
                redoubleBtn.disabled = false;
            });
    }

    spinCardBtn.addEventListener("click", performCardSpin);

    // --- Après une attribution ou une annulation dans un tirage multiple ---

    function finishCurrentDraw(status) {
        const draw = draws[currentDrawIndex];
        if (draw) {
            draw.status = status;
            draw.cardName = status === "done" ? currentWinnerName : null;
        }
        currentWinnerCardId = null;

        const next = nextPendingIndex();
        currentDrawIndex = null;
        refreshDrawRows();

        decisionActions.style.display = "none";
        afterActions.style.display = "flex";
        if (next !== -1) {
            nextDrawBtn.textContent = `➡ Tirage suivant (n°${next + 1} — ${draws[next].libelle})`;
            nextDrawBtn.dataset.index = String(next);
            nextDrawBtn.style.display = "inline-block";
        } else {
            nextDrawBtn.style.display = "none";
        }
    }

    nextDrawBtn.addEventListener("click", () => {
        selectDraw(parseInt(nextDrawBtn.dataset.index, 10));
    });

    // --- Quitte ou double : choix initial + relances ---

    // Affiche l'étape "2. Carte" prête à tirer à la rareté atteinte, avec le bouton
    // de relance uniquement disponible depuis Rare (vers Épique, palier maximum).
    function showCardDrawChoice(rarityId) {
        currentRarityId = rarityId;
        const label = RARITY_LABELS[rarityId] || "";
        rarityResultLabel.textContent = label;
        spinCardBtn.textContent = `🎴 Lancer la roue des cartes (${label})`;
        spinCardBtn.disabled = false;
        cardErrorMessage.style.display = "none";

        if (rarityId === 2) {
            redoubleBtn.style.display = "inline-block";
            redoubleBtn.disabled = false;
        } else {
            redoubleBtn.style.display = "none";
        }

        collapse(wheelResult);
        expand(stepCard);
    }

    function showLostState() {
        collapse(stepCard);
        resultName.textContent = "💥 Perdu ! La carte repart en fumée.";
        resultImage.style.display = "none";
        decisionActions.style.display = "none";
        afterActions.style.display = "flex";
        nextDrawBtn.style.display = "none";
        statusMessage.textContent = "";
        currentWinnerCardId = null;
        currentRarityId = null;
        expand(wheelResult);
    }

    // Anime une petite roue à 2 issues ("Rare"/"Épique" vs "Perdu") pondérée selon l'étape,
    // en réutilisant la roue de rareté, puis résout le tirage serveur (autorité).
    function runRarityGambleReel(step) {
        const chance = step === "to-rare" ? 50 : 25;
        const winLabel = step === "to-rare" ? "Rare" : "Épique";
        const winId = step === "to-rare" ? 2 : 3;

        rarityStepTitle.textContent = `🎲 Tirage au sort : ${winLabel} ou Perdu ?`;
        stepRarity.style.display = "";
        spinRarityBtn.style.display = "none";
        collapse(stepCard);
        collapse(wheelResult);
        const [row] = buildRarityRows(1);

        return post(doubleChanceUrl, { step })
            .then(data => {
                if (!data.success) {
                    return { error: data.message || "Impossible de tenter ce double." };
                }

                const winItem = { id: winId, libelle: winLabel };
                const loseItem = { id: 0, libelle: "Perdu" };
                const pseudoTable = [
                    { ...winItem, weight: chance },
                    { ...loseItem, weight: 100 - chance },
                ];
                const outcomeItem = data.won ? winItem : loseItem;
                const items = buildStripItems(pseudoTable, outcomeItem);

                return spinReel(row.viewport, row.strip, items, renderRarityItem).then(() => ({
                    won: data.won,
                    rarityId: winId,
                }));
            })
            .catch(() => ({ error: "Une erreur est survenue." }));
    }

    doubleChoiceSafeBtn.addEventListener("click", () => {
        doubleChoiceSafeBtn.disabled = true;
        doubleChoiceGambleBtn.disabled = true;
        doubleChoiceError.style.display = "none";
        lockOptions();

        showCardDrawChoice(1);
        performCardSpin();
    });

    doubleChoiceGambleBtn.addEventListener("click", () => {
        doubleChoiceSafeBtn.disabled = true;
        doubleChoiceGambleBtn.disabled = true;
        doubleChoiceError.style.display = "none";
        lockOptions();

        runRarityGambleReel("to-rare").then(result => {
            if (result.error) {
                doubleChoiceError.textContent = result.error;
                doubleChoiceError.style.display = "block";
                doubleChoiceSafeBtn.disabled = false;
                doubleChoiceGambleBtn.disabled = false;
                unlockOptions();
                return;
            }

            if (!result.won) {
                showLostState();
                return;
            }

            showCardDrawChoice(result.rarityId);
        });
    });

    redoubleBtn.addEventListener("click", () => {
        redoubleBtn.disabled = true;
        spinCardBtn.disabled = true;

        runRarityGambleReel("to-epique").then(result => {
            if (result.error) {
                statusMessage.textContent = result.error;
                redoubleBtn.disabled = false;
                spinCardBtn.disabled = false;
                return;
            }

            if (!result.won) {
                showLostState();
                return;
            }

            showCardDrawChoice(result.rarityId);
        });
    });

    // Rien n'a encore été attribué à ce stade : annuler oublie ce tirage. Dans un tirage
    // multiple, seul ce tirage est abandonné et on passe aux suivants.
    cancelBtn.addEventListener("click", () => {
        if (draws.length > 1) {
            statusMessage.textContent = "Tirage annulé.";
            finishCurrentDraw("skipped");
            collapse(stepCard);
            return;
        }

        resetFlow();
    });

    confirmBtn.addEventListener("click", () => {
        if (!currentWinnerCardId) {
            return;
        }

        cancelBtn.disabled = true;
        confirmBtn.disabled = true;

        post(confirmUrl, { cardId: currentWinnerCardId })
            .then(data => {
                statusMessage.textContent = data.message;

                if (!data.success) {
                    cancelBtn.disabled = false;
                    confirmBtn.disabled = false;
                    return;
                }

                if (draws.length) {
                    finishCurrentDraw("done");
                    collapse(stepCard);
                } else {
                    // Quitte ou double : un seul tirage.
                    decisionActions.style.display = "none";
                    afterActions.style.display = "flex";
                    nextDrawBtn.style.display = "none";
                    currentWinnerCardId = null;
                }
            })
            .catch(() => {
                cancelBtn.disabled = false;
                confirmBtn.disabled = false;
            });
    });

    retryBtn.addEventListener("click", resetFlow);

    updateModeVisibility();
    buildRarityRows(drawCount());
});
