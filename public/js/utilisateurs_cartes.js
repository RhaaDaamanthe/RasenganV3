document.addEventListener("DOMContentLoaded", () => {
    // ------------------------------------------------------------------
    // ATTENTION: Les fonctions de filtrage côté client sont retirées.
    // Le filtrage (searchbar et rarityFilter) est géré côté serveur par PHP/Symfony
    // via la soumission du formulaire GET dans animeCards.html.twig.
    // ------------------------------------------------------------------

    const cards = document.querySelectorAll(".card");

    // Gère le clic sur les cartes pour afficher/masquer la liste des propriétaires
    cards.forEach(card => {
        card.addEventListener("click", (e) => {
            
            const ownersList = card.querySelector(".owners-list");

            // On vérifie si la liste des propriétaires existe dans cette carte. 
            // Si elle existe, c'est que la carte est une carte "collectable" 
            // (les cartes rares/spéciales n'ont pas cette liste dans le Twig).
            if (ownersList) {
                // Empêche le comportement par défaut (comme la redirection si la carte est un lien)
                e.preventDefault(); 
                e.stopPropagation(); // Empêche le clic de se propager au document
                
                const isVisible = ownersList.style.display === "block";
                ownersList.style.display = isVisible ? "none" : "block";

                // Optionnel : si un bouton de bascule existe, mettez à jour son texte ici
            }
        });
    });

    // Fermer la liste de tous les propriétaires si on clique n'importe où ailleurs sur la page
    document.addEventListener("click", (e) => {
        cards.forEach(card => {
            const ownersList = card.querySelector(".owners-list");
            
            // Si la liste existe ET si le clic n'est PAS sur la carte elle-même
            if (ownersList && ownersList.style.display === "block" && !card.contains(e.target)) {
                ownersList.style.display = "none";
            }
        });
    });
});
