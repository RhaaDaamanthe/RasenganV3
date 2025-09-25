console.log("main.js chargé !");

// Gestion du menu hamburger
const menuHamburger = document.querySelector(".menu-burger");
const navLinks = document.querySelector(".nav-links");

if (menuHamburger && navLinks) {
    menuHamburger.addEventListener("click", () => {
        navLinks.classList.toggle("mobile-menu");
    });
}

// Liste animé / films / séries première lettre en gras
document.addEventListener("DOMContentLoaded", function () {
    function processList(listId) {
        const listElement = document.getElementById(listId);

        if (!listElement) {
            console.warn(` Aucun élément avec l'ID '${listId}' trouvé !`);
            return;
        }

        const items = listElement.getElementsByTagName("li");
        let seenLetters = new Set();

        for (let item of items) {
            let text = item.textContent.trim();
            let firstLetter = text.charAt(0).toUpperCase();

            if (!seenLetters.has(firstLetter) && !text.startsWith('Total des cartes')) {
                item.innerHTML = `<strong>${firstLetter}</strong>${text.slice(1)}`;
                seenLetters.add(firstLetter);
            }
        }
    }

    // Appliquer la fonction aux listes
    processList("anime-list");
    processList("film-list");

    // Gestion du clic sur les cartes pour redirection (main-catalogue)
    document.querySelectorAll(".main-catalogue .card").forEach((card) => {
        card.addEventListener("click", () => {
            const url = card.getAttribute("data-url");
            if (url) {
                window.location.href = url;
            }
        });
    });
});