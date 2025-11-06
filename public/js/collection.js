document.addEventListener("DOMContentLoaded", () => {
    // Animation des compteurs (effet "progressif")
    const animateCounter = (element) => {
        const target = parseInt(element.dataset.value, 10);
        let current = 0;
        const step = Math.max(1, Math.ceil(target / 80)); // vitesse dynamique
        const interval = setInterval(() => {
            current += step;
            if (current >= target) {
                current = target;
                clearInterval(interval);
            }
            element.textContent = current.toLocaleString('fr-FR');
        }, 20);
    };

    document.querySelectorAll(".count").forEach(el => animateCounter(el));
});