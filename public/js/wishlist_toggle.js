// Capture phase (dernier argument `true`) : se déclenche AVANT les listeners
// attachés directement sur .card (owners-list, modale mythique), qui sont en
// phase de bulle. stopPropagation() ici empêche donc ces listeners de
// s'exécuter du tout, au lieu d'arriver trop tard.
document.addEventListener("click", (e) => {
    const btn = e.target.closest(".wishlist-btn");
    if (!btn || btn.disabled) {
        return;
    }

    e.preventDefault();
    e.stopPropagation();

    const type = btn.dataset.type;
    const id = btn.dataset.id;
    const csrfToken = btn.dataset.csrf;

    btn.disabled = true;

    const body = new URLSearchParams();
    body.set("_token", csrfToken);

    fetch(`/catalogue/${type}/wishlist/${id}/toggle`, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: body.toString(),
    })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                btn.disabled = false;
                if (data.message) {
                    const originalTitle = btn.title;
                    btn.title = data.message;
                    btn.classList.add("error");
                    setTimeout(() => {
                        btn.classList.remove("error");
                        btn.title = originalTitle;
                    }, 1800);
                }
                return;
            }

            btn.classList.toggle("active", data.wishlisted);
            btn.textContent = data.wishlisted ? "♥" : "♡";
            btn.disabled = false;

            if (!data.wishlisted) {
                const removableCard = btn.closest(".wishlist-remove-card");
                if (removableCard) {
                    removableCard.style.transition = "opacity 0.3s ease";
                    removableCard.style.opacity = "0";
                    setTimeout(() => removableCard.remove(), 300);
                }
            }
        })
        .catch(() => {
            btn.disabled = false;
        });
}, true);
