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
    const minRaritySelect = document.getElementById("min-rarity-select");
    const minRarityField = document.getElementById("min-rarity-field");

    const stepDoubleChoice = document.getElementById("step-double-choice");
    const doubleChoiceSafeBtn = document.getElementById("double-choice-safe-btn");
    const doubleChoiceGambleBtn = document.getElementById("double-choice-gamble-btn");
    const doubleChoiceError = document.getElementById("double-choice-error");

    const stepRarity = document.getElementById("step-rarity");
    const rarityStepTitle = document.getElementById("rarity-step-title");
    const rarityViewport = document.querySelector("#step-rarity .wheel-viewport");
    const rarityStrip = document.getElementById("rarity-strip");
    const spinRarityBtn = document.getElementById("spin-rarity-btn");

    const stepCard = document.getElementById("step-card");
    const cardViewport = document.querySelector("#step-card .wheel-viewport");
    const cardStrip = document.getElementById("card-strip");
    const spinCardBtn = document.getElementById("spin-card-btn");
    const redoubleBtn = document.getElementById("step-card-redouble-btn");
    const rarityResultLabel = document.getElementById("rarity-result-label");
    const cardErrorMessage = document.getElementById("card-error-message");

    const wheelResult = document.getElementById("wheel-result");
    const resultImage = document.getElementById("result-image");
    const resultName = document.getElementById("result-name");
    const decisionActions = document.getElementById("wheel-decision-actions");
    const afterActions = document.getElementById("wheel-after-actions");
    const cancelBtn = document.getElementById("cancel-btn");
    const confirmBtn = document.getElementById("confirm-btn");
    const retryBtn = document.getElementById("retry-btn");
    const statusMessage = document.getElementById("wheel-status-message");

    const ITEM_WIDTH = 140;
    const REEL_LENGTH = 40;
    const WINNING_INDEX = 36;

    const RARITY_LABELS = { 1: "Commune", 2: "Rare", 3: "Épique" };

    let currentRarityId = null;
    let currentWinnerCardId = null;
    let currentCardRarityId = null;

    function renderRarityItem(rarity) {
        const div = document.createElement("div");
        div.className = "wheel-item wheel-item-rarity rarity-" + rarity.id;
        div.innerHTML = `<span>${rarity.libelle}</span>`;
        return div;
    }

    function renderCardItem(card) {
        const div = document.createElement("div");
        div.className = "wheel-item wheel-item-card";
        div.innerHTML = `
            ${card.imagePath ? `<img src="${card.imagePath}" alt="">` : ""}
            <span>${card.nom}</span>
        `;
        return div;
    }

    // Reproduit côté client la même logique que WheelController::getWeightsForMinimumRarity() :
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

    // Affiche l'étape "1. Choix du risque" (Quitte ou double) ou "1. Rareté" (Classique/333).
    function updateModeVisibility() {
        const isDoubleMode = modeSelect.value === "double";
        stepDoubleChoice.style.display = isDoubleMode ? "" : "none";
        stepRarity.style.display = isDoubleMode ? "none" : "";
        minRarityField.style.display = isDoubleMode ? "none" : "";
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
                strip.style.transition = "transform 4.5s cubic-bezier(0.1, 0.8, 0.2, 1)";
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

    function lockOptions() {
        modeSelect.disabled = true;
        titleSelect.disabled = true;
        minRaritySelect.disabled = true;
    }

    function unlockOptions() {
        modeSelect.disabled = false;
        titleSelect.disabled = false;
        minRaritySelect.disabled = false;
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
        rarityStrip.innerHTML = "";
        cardStrip.innerHTML = "";
        currentRarityId = null;
        currentWinnerCardId = null;
        currentCardRarityId = null;
        updateModeVisibility();
    }

    modeSelect.addEventListener("change", resetFlow);

    // --- Tirage de rareté classique/333 (pondéré sur 4 raretés) ---

    spinRarityBtn.addEventListener("click", () => {
        spinRarityBtn.disabled = true;
        lockOptions();

        // Chaque nouveau lancer replie la suite du parcours avant de la redéplier sur le résultat.
        collapse(stepCard);
        collapse(wheelResult);

        const minRarityId = minRaritySelect.value;
        const baseTable = getBaseRarityTable();

        const body = new URLSearchParams();
        body.set("_token", csrfToken);
        body.set("mode", modeSelect.value);
        body.set("minRarityId", minRarityId);

        fetch(spinRarityUrl, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: body.toString(),
        })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    spinRarityBtn.disabled = false;
                    unlockOptions();
                    return;
                }

                const winnerRarity = baseTable.find(r => r.id === data.rarityId);
                const effectiveRarities = getWeightedRarities(baseTable, minRarityId);
                const items = buildStripItems(effectiveRarities, winnerRarity);

                spinReel(rarityViewport, rarityStrip, items, renderRarityItem).then(() => {
                    currentRarityId = data.rarityId;
                    rarityResultLabel.textContent = data.libelle;
                    spinCardBtn.textContent = "Lancer la roue des cartes";
                    redoubleBtn.style.display = "none";
                    cardErrorMessage.style.display = "none";
                    spinCardBtn.disabled = false;
                    spinRarityBtn.disabled = false;
                    expand(stepCard);
                });
            })
            .catch(() => {
                spinRarityBtn.disabled = false;
                unlockOptions();
            });
    });

    // --- Tirage de la carte, une fois une rareté déterminée (partagé par tous les modes) ---

    function fetchAndSpinCard(rarityId) {
        const body = new URLSearchParams();
        body.set("_token", csrfToken);
        body.set("rarityId", rarityId);
        body.set("titleId", titleSelect.value);

        return fetch(spinCardUrl, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: body.toString(),
        })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    cardErrorMessage.textContent = data.message || "Impossible de lancer la roue des cartes.";
                    cardErrorMessage.style.display = "block";
                    return null;
                }

                const items = buildStripItems(data.pool, data.winner);

                return spinReel(cardViewport, cardStrip, items, renderCardItem).then(() => {
                    currentWinnerCardId = data.winner.id;
                    currentCardRarityId = rarityId;
                    resultName.textContent = data.winner.nom;

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

    function performCardSpin() {
        if (!currentRarityId) {
            return;
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
                spinCardBtn.disabled = false;
                redoubleBtn.disabled = false;
            });
    }

    spinCardBtn.addEventListener("click", performCardSpin);

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
        statusMessage.textContent = "";
        currentWinnerCardId = null;
        currentCardRarityId = null;
        currentRarityId = null;
        expand(wheelResult);
    }

    // Anime une petite roue à 2 issues ("Rare"/"Épique" vs "Perdu") pondérée selon l'étape,
    // en réutilisant la roue de rareté existante, puis résout le tirage serveur (autorité).
    function runRarityGambleReel(step) {
        const chance = step === "to-rare" ? 50 : 25;
        const winLabel = step === "to-rare" ? "Rare" : "Épique";
        const winId = step === "to-rare" ? 2 : 3;

        rarityStepTitle.textContent = `🎲 Tirage au sort : ${winLabel} ou Perdu ?`;
        stepRarity.style.display = "";
        collapse(stepCard);
        collapse(wheelResult);
        rarityStrip.innerHTML = "";

        const body = new URLSearchParams();
        body.set("_token", csrfToken);
        body.set("step", step);

        return fetch(doubleChanceUrl, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: body.toString(),
        })
            .then(response => response.json())
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

                return spinReel(rarityViewport, rarityStrip, items, renderRarityItem).then(() => ({
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

    // Rien n'a encore été attribué à ce stade : annuler ne fait qu'oublier ce tirage.
    cancelBtn.addEventListener("click", () => {
        resetFlow();
    });

    confirmBtn.addEventListener("click", () => {
        if (!currentWinnerCardId) {
            return;
        }

        cancelBtn.disabled = true;
        confirmBtn.disabled = true;

        const body = new URLSearchParams();
        body.set("_token", csrfToken);
        body.set("cardId", currentWinnerCardId);

        fetch(confirmUrl, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: body.toString(),
        })
            .then(response => response.json())
            .then(data => {
                statusMessage.textContent = data.message;

                if (data.success) {
                    decisionActions.style.display = "none";
                    afterActions.style.display = "flex";
                    currentWinnerCardId = null;
                } else {
                    cancelBtn.disabled = false;
                    confirmBtn.disabled = false;
                }
            })
            .catch(() => {
                cancelBtn.disabled = false;
                confirmBtn.disabled = false;
            });
    });

    retryBtn.addEventListener("click", resetFlow);

    updateModeVisibility();
});
