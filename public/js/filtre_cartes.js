// Fonction filterCards définie globalement
function filterCards() {
    const searchInput = document.getElementById('searchbar');
    const rarityFilter = document.getElementById("rarityFilter");
    const sectionFilter = document.getElementById("sectionFilter");
    const cards = document.querySelectorAll(".catalogue2 .card");

    if (!searchInput || !rarityFilter) return; // Les deux filtres de base doivent exister

    const searchValue = searchInput.value.toLowerCase().trim().normalize("NFD").replace(/[\u0300-\u036f]/g, "") || "";
    const selectedRarity = rarityFilter.value.toLowerCase().trim().normalize("NFD").replace(/[\u0300-\u036f]/g, "") || "";
    
    // Vérifie si le filtre de section existe avant de récupérer sa valeur
    const selectedSection = sectionFilter ? sectionFilter.value.toLowerCase() : 'all';

    cards.forEach((card) => {
        const characterName = card.querySelector("img")?.getAttribute("alt")?.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "") || "";
        const animeName = card.getAttribute("data-anime")?.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "") || "";
        const filmName = card.getAttribute("data-film")?.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "") || "";
        
        // Vérifie si l'attribut data-section existe
        const cardSection = card.getAttribute("data-section") || 'all'; 
        
        const cardRareteId = card.getAttribute("data-rarete");

        let cardRarityName = '';
        switch(parseInt(cardRareteId)) {
            case 1: cardRarityName = 'communes'; break;
            case 2: cardRarityName = 'rares'; break;
            case 3: cardRarityName = 'epiques'; break;
            case 4: cardRarityName = 'legendaires'; break;
            case 5: cardRarityName = 'mythiques'; break;
            case 6: cardRarityName = 'events'; break;
        }

        const matchesSearch =
            searchValue === "" ||
            characterName.includes(searchValue) ||
            animeName.includes(searchValue) ||
            filmName.includes(searchValue);

        const matchesRarity =
            selectedRarity === "" || cardRarityName === selectedRarity;
            
        // Condition pour le filtre de section, qui sera toujours vraie si le filtre de section n'existe pas
        const matchesSection = selectedSection === 'all' || cardSection === selectedSection;

        card.style.display = matchesSearch && matchesRarity && matchesSection ? "block" : "none";
    });
}

// Ajouter les écouteurs d'événements pour le filtrage
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('searchbar');
    const rarityFilter = document.getElementById("rarityFilter");
    const sectionFilter = document.getElementById("sectionFilter");

    if (searchInput && rarityFilter) {
        searchInput.addEventListener("input", filterCards);
        rarityFilter.addEventListener("change", filterCards);
        
        // Ajout de l'écouteur d'événement pour le filtre de section, seulement s'il existe
        if (sectionFilter) {
            sectionFilter.addEventListener("change", filterCards);
        }
        
        filterCards();
    }
});