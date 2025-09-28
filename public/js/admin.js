    // Barre de recherche admin
    function filterCards() {
        const input = document.getElementById('card-search');
        const filter = input.value.toLowerCase();
        const grid = document.getElementById('card-grid');
        const cards = grid.getElementsByClassName('admin-card');
        const noResultsMessage = document.getElementById('no-results-message');
        let visibleCount = 0;

        for (let i = 0; i < cards.length; i++) {
            const card = cards[i];
            const name = card.getAttribute('data-name');
            const description = card.getAttribute('data-description');
            const anime = card.getAttribute('data-anime');
            
            // Vérifie si le filtre correspond au nom, à la description ou à l'animé
            if (name.includes(filter) || description.includes(filter) || anime.includes(filter)) {
                card.style.display = "";
                visibleCount++;
            } else {
                card.style.display = "none";
            }
        }

        // Afficher/Masquer le message "Aucun résultat"
        if (visibleCount === 0 && cards.length > 0) {
            noResultsMessage.style.display = "block";
        } else {
            noResultsMessage.style.display = "none";
        }
        
        // Gère le cas où il n'y a aucune carte dans la base (message "Aucune carte d'animé trouvée.")
        const initialMessage = document.getElementById('no-cards-message');
        if (initialMessage) {
            initialMessage.style.display = (cards.length === 0) ? "block" : "none";
        }
    }