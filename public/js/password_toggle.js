// Bouton « œil » des champs mot de passe (connexion, inscription) :
// bascule le champ entre masqué et en clair.
document.querySelectorAll('.toggle-password').forEach((bouton) => {
    const champ = bouton.closest('.input-box')?.querySelector('input');
    const icone = bouton.querySelector('i');
    if (!champ) {
        return;
    }

    bouton.addEventListener('click', () => {
        const afficher = champ.type === 'password';
        champ.type = afficher ? 'text' : 'password';
        icone.classList.toggle('bx-show', !afficher);
        icone.classList.toggle('bx-hide', afficher);
        bouton.setAttribute('aria-label', afficher ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        bouton.setAttribute('aria-pressed', afficher ? 'true' : 'false');
    });
});
