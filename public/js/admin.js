// admin.js – Recherche instantanée admin (animés, films, cartes, etc.)
function filterCards() {
    const input = document.getElementById('card-search');
    if (!input) return; // Sécurité si la page n'a pas de barre

    const filter = input.value.toLowerCase().trim();
    const cards = document.querySelectorAll('.admin-card');
    const noResults = document.getElementById('no-results-message');
    const noCardsMessage = document.getElementById('no-cards-message');

    let visibleCount = 0;

    cards.forEach(card => {
        // On récupère TOUS les data-attributes possibles
        const text = [
            card.getAttribute('data-name'),
            card.getAttribute('data-description'),
            card.getAttribute('data-anime'),  // pour les pages animés
            card.getAttribute('data-film'),   // pour les pages films
            card.getAttribute('data-card'),   // bonus futur
            card.getAttribute('data-user')    // bonus futur
        ]
        .filter(Boolean) // enlève les null/undefined
        .join(' ')
        .toLowerCase();

        if (filter === '' || text.includes(filter)) {
            card.style.display = '';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    // Gestion propre des messages
    if (noResults) {
        noResults.style.display = (filter !== '' && visibleCount === 0) ? 'block' : 'none';
    }

    if (noCardsMessage) {
        noCardsMessage.style.display = (cards.length === 0 && filter === '') ? 'block' : 'none';
    }
}

// Optionnel : déclencher la recherche au chargement si un terme est déjà dans l'URL
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('card-search');
    if (searchInput && searchInput.value) {
        filterCards();
    }
});