document.addEventListener("DOMContentLoaded", () => {
    const overlay = document.getElementById("mythic-modal-overlay");
    if (!overlay) {
        return;
    }

    const type = overlay.dataset.type; // "anime" ou "film"
    const csrfToken = overlay.dataset.csrf;
    const titleEl = overlay.querySelector(".mythic-modal-title");
    const listEl = overlay.querySelector(".mythic-requirements-list");
    const statusEl = overlay.querySelector(".mythic-modal-status");
    const unlockBtn = overlay.querySelector(".mythic-unlock-btn");
    const closeBtn = overlay.querySelector(".mythic-modal-close");

    let currentCardId = null;
    let currentCardEl = null;

    function openModal(cardId, cardEl) {
        currentCardId = cardId;
        currentCardEl = cardEl;

        titleEl.textContent = "Chargement...";
        listEl.innerHTML = "";
        statusEl.textContent = "";
        unlockBtn.disabled = true;
        overlay.style.display = "flex";

        fetch(`/catalogue/${type}/mythic/${cardId}/status`)
            .then(response => response.json())
            .then(renderStatus)
            .catch(() => {
                titleEl.textContent = "Erreur";
                statusEl.textContent = "Impossible de charger la combinaison.";
            });
    }

    function renderStatus(data) {
        titleEl.textContent = data.nom;
        listEl.innerHTML = "";

        if (data.requirements.length === 0) {
            statusEl.textContent = "Cette carte ne possède pas de combinaison.";
            unlockBtn.disabled = true;
            return;
        }

        data.requirements.forEach(req => {
            const li = document.createElement("li");
            li.className = "requirement-row " + (req.ok ? "ok" : "missing");
            li.innerHTML = `
                <span class="requirement-status">${req.ok ? "✓" : "✗"}</span>
                <span class="requirement-nom">${req.nom}</span>
                <span class="requirement-count">${req.owned} / ${req.needed}</span>
            `;
            listEl.appendChild(li);
        });

        if (data.alreadyOwned) {
            statusEl.textContent = "Vous possédez déjà cette carte mythique.";
            unlockBtn.disabled = true;
        } else if (data.canUnlock) {
            statusEl.textContent = "Combinaison complète !";
            unlockBtn.disabled = false;
        } else {
            statusEl.textContent = "Il vous manque des cartes pour débloquer cette mythique.";
            unlockBtn.disabled = true;
        }
    }

    function closeModal() {
        overlay.style.display = "none";
        currentCardId = null;
        currentCardEl = null;
    }

    document.querySelectorAll(".card.mythic-card").forEach(card => {
        card.addEventListener("click", (e) => {
            e.preventDefault();
            e.stopPropagation();
            openModal(card.dataset.mythicId, card);
        });
    });

    closeBtn.addEventListener("click", closeModal);
    overlay.addEventListener("click", (e) => {
        if (e.target === overlay) {
            closeModal();
        }
    });

    unlockBtn.addEventListener("click", () => {
        if (!currentCardId) {
            return;
        }

        unlockBtn.disabled = true;
        statusEl.textContent = "Déblocage en cours...";

        const body = new URLSearchParams();
        body.set("_token", csrfToken);

        fetch(`/catalogue/${type}/mythic/${currentCardId}/unlock`, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: body.toString(),
        })
            .then(response => response.json())
            .then(data => {
                statusEl.textContent = data.message;

                if (data.success) {
                    if (currentCardEl) {
                        const titleNode = currentCardEl.querySelector(".card-title");
                        if (titleNode) {
                            titleNode.textContent = data.pseudo;
                        }
                    }
                    setTimeout(closeModal, 1200);
                } else {
                    unlockBtn.disabled = false;
                }
            })
            .catch(() => {
                statusEl.textContent = "Une erreur est survenue.";
                unlockBtn.disabled = false;
            });
    });
});
