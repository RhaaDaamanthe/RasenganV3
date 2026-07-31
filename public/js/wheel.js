document.addEventListener("DOMContentLoaded", () => {
    const page = document.querySelector(".wheel-page");
    if (!page) {
        return;
    }

    const csrfToken = page.dataset.csrf;
    const rarities = JSON.parse(page.dataset.rarities);
    const spinRarityUrl = page.dataset.spinRarityUrl;
    const spinCardUrl = page.dataset.spinCardUrl;
    const confirmUrl = page.dataset.confirmUrl;

    const rarityViewport = document.querySelector("#step-rarity .wheel-viewport");
    const rarityStrip = document.getElementById("rarity-strip");
    const spinRarityBtn = document.getElementById("spin-rarity-btn");

    const stepCard = document.getElementById("step-card");
    const cardViewport = document.querySelector("#step-card .wheel-viewport");
    const cardStrip = document.getElementById("card-strip");
    const spinCardBtn = document.getElementById("spin-card-btn");
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

    let currentRarityId = null;
    let currentWinnerCardId = null;

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

    function resetFlow() {
        collapse(stepCard);
        collapse(wheelResult);
        cardErrorMessage.style.display = "none";
        statusMessage.textContent = "";
        spinRarityBtn.disabled = false;
        spinCardBtn.disabled = false;
        cancelBtn.disabled = false;
        confirmBtn.disabled = false;
        decisionActions.style.display = "flex";
        afterActions.style.display = "none";
        rarityStrip.innerHTML = "";
        cardStrip.innerHTML = "";
        currentRarityId = null;
        currentWinnerCardId = null;
    }

    spinRarityBtn.addEventListener("click", () => {
        spinRarityBtn.disabled = true;

        // Chaque nouveau lancer replie la suite du parcours avant de la redéplier sur le résultat.
        collapse(stepCard);
        collapse(wheelResult);

        const body = new URLSearchParams();
        body.set("_token", csrfToken);

        fetch(spinRarityUrl, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: body.toString(),
        })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    spinRarityBtn.disabled = false;
                    return;
                }

                const winnerRarity = rarities.find(r => r.id === data.rarityId);
                const items = buildStripItems(rarities, winnerRarity);

                spinReel(rarityViewport, rarityStrip, items, renderRarityItem).then(() => {
                    currentRarityId = data.rarityId;
                    rarityResultLabel.textContent = data.libelle;
                    cardErrorMessage.style.display = "none";
                    spinCardBtn.disabled = false;
                    spinRarityBtn.disabled = false;
                    expand(stepCard);
                });
            })
            .catch(() => {
                spinRarityBtn.disabled = false;
            });
    });

    spinCardBtn.addEventListener("click", () => {
        if (!currentRarityId) {
            return;
        }

        spinCardBtn.disabled = true;
        cardErrorMessage.style.display = "none";
        collapse(wheelResult);

        const body = new URLSearchParams();
        body.set("_token", csrfToken);
        body.set("rarityId", currentRarityId);

        fetch(spinCardUrl, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: body.toString(),
        })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    cardErrorMessage.textContent = data.message || "Impossible de lancer la roue des cartes.";
                    cardErrorMessage.style.display = "block";
                    spinCardBtn.disabled = false;
                    return;
                }

                const items = buildStripItems(data.pool, data.winner);

                spinReel(cardViewport, cardStrip, items, renderCardItem).then(() => {
                    currentWinnerCardId = data.winner.id;
                    resultName.textContent = data.winner.nom;

                    if (data.winner.imagePath) {
                        resultImage.src = data.winner.imagePath;
                        resultImage.style.display = "block";
                    } else {
                        resultImage.style.display = "none";
                    }

                    decisionActions.style.display = "flex";
                    afterActions.style.display = "none";
                    cancelBtn.disabled = false;
                    confirmBtn.disabled = false;
                    statusMessage.textContent = "";
                    spinCardBtn.disabled = false;
                    expand(wheelResult);
                });
            })
            .catch(() => {
                spinCardBtn.disabled = false;
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
});
