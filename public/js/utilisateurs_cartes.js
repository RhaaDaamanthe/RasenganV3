// Fichier : public/js/utilisateurs_cartes.js

document.addEventListener("DOMContentLoaded", () => {
    const cards = document.querySelectorAll(".card");
    const rarityFilter = document.getElementById("rarityFilter");
    const searchbar = document.getElementById("searchbar");

    function filterCards() {
        const rarity = rarityFilter.value.toLowerCase();
        const search = searchbar.value.toLowerCase();

        cards.forEach((card) => {
            const anime = card.getAttribute("data-anime").toLowerCase();
            const title = card.querySelector(".card-content h2").textContent.toLowerCase();
            const rarete = card.getAttribute("data-rarete").toLowerCase();
            const show =
                (!rarity || title.includes(rarity) || rarete.includes(rarity)) &&
                (!search || anime.includes(search) || title.includes(search));
            card.style.display = show ? "block" : "none";
        });
    }

    rarityFilter.addEventListener("change", filterCards);
    searchbar.addEventListener("input", filterCards);

    // Gère le clic sur les cartes pour afficher/masquer la liste des propriétaires
    cards.forEach(card => {
        card.addEventListener("click", (e) => {
            // LIGNE CORRIGÉE : Utilisation de `data-rarete` et `parseInt`
            const rarete = parseInt(card.getAttribute("data-rarete"));

            if (rarete <= 3) {
                // Empêche le comportement par défaut (comme la redirection) uniquement pour ces raretés
                e.preventDefault(); 
                
                const ownersList = card.querySelector(".owners-list");
                if (ownersList) {
                    const isVisible = ownersList.style.display === "block";
                    ownersList.style.display = isVisible ? "none" : "block";
                }
            }
        });
    });

    // Fermer la liste de tous les propriétaires si on clique n'importe où ailleurs sur la page
    document.addEventListener("click", (e) => {
        cards.forEach(card => {
            const ownersList = card.querySelector(".owners-list");
            if (ownersList && !card.contains(e.target)) {
                ownersList.style.display = "none";
            }
        });
    });
});