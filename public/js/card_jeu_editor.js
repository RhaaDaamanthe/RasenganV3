/**
 * Éditeur de carte jeu vidéo : aperçu en direct sur un <canvas>.
 *
 * La mise en page (fenêtre, textes, halo, couleurs, bornes du cadrage) vient de
 * App\Service\CardJeuComposer via l'attribut data-config du formulaire : l'aperçu est
 * dessiné avec les mêmes valeurs que l'image générée par le serveur.
 *
 * Le cadrage est exprimé en pixels de la carte finale : coin haut-gauche de l'image (x, y)
 * et échelle appliquée à l'image source. Il est recopié dans les champs cachés du formulaire.
 */
(function () {
    'use strict';

    const form = document.querySelector('form.editeur-carte');
    if (!form) {
        return;
    }

    const cfg = JSON.parse(form.dataset.config);
    const champ = (nom) => form.querySelector(`[data-editeur="${nom}"]`);
    const F = cfg.fenetre;
    const W = cfg.largeur;
    const H = cfg.hauteur;

    const canvas = form.querySelector('.editeur-canvas');
    const ctx = canvas.getContext('2d');
    const ECHELLE_CANVAS = canvas.width / W; // l'aperçu est dessiné en demi-résolution

    const el = {
        nom: champ('nom'),
        jeu: champ('jeu'),
        rarete: champ('rarete'),
        numero: champ('numero'),
        image: champ('image'),
        x: champ('cadrage-x'),
        y: champ('cadrage-y'),
        scale: champ('cadrage-scale'),
        zoom: champ('zoom'),
        recentrer: champ('recentrer'),
        controles: champ('controles'),
        aide: champ('aide'),
        alerte: champ('alerte'),
        vide: champ('vide'),
    };

    let image = null;          // HTMLImageElement du perso
    let cadrage = null;        // { x, y, scale }
    let policePrete = false;
    const cadres = {};         // id de rareté => HTMLImageElement

    // ------------------------------------------------------------------ Cadrage

    const echelleMin = () => Math.max(F.w / image.naturalWidth, F.h / image.naturalHeight);

    function cadrageParDefaut() {
        const scale = echelleMin();
        return { x: F.x + (F.w - image.naturalWidth * scale) / 2, y: F.y, scale };
    }

    /** Même règle que CardJeuComposer::borner() : jamais de vide dans la fenêtre. */
    function borner(c) {
        const min = echelleMin();
        const scale = Math.min(Math.max(c.scale, min), min * cfg.zoomMax);
        const largeur = image.naturalWidth * scale;
        const hauteur = image.naturalHeight * scale;
        return {
            x: Math.min(Math.max(c.x, F.x + F.w - largeur), F.x),
            y: Math.min(Math.max(c.y, F.y + F.h - hauteur), F.y),
            scale,
        };
    }

    /** Zoom vers une échelle donnée en gardant le point (px, py) de la carte immobile. */
    function zoomerVers(scale, px, py) {
        const ratio = scale / cadrage.scale;
        appliquer({ x: px - (px - cadrage.x) * ratio, y: py - (py - cadrage.y) * ratio, scale });
    }

    function appliquer(c) {
        cadrage = borner(c);
        el.x.value = cadrage.x.toFixed(2);
        el.y.value = cadrage.y.toFixed(2);
        el.scale.value = cadrage.scale.toFixed(5);
        el.zoom.value = (cadrage.scale / echelleMin()).toFixed(2);
        majAlerte();
        redessiner();
    }

    function majAlerte() {
        if (cadrage.scale > cfg.agrandissementFlou) {
            const largeurConseillee = Math.ceil(image.naturalWidth * cadrage.scale / cfg.agrandissementFlou);
            el.alerte.textContent = `L'image est agrandie ×${cadrage.scale.toFixed(1)} : elle risque d'être floue. `
                + `Pour ce cadrage, une image d'au moins ${largeurConseillee} px de large serait nette `
                + `(actuelle : ${image.naturalWidth} px).`;
            el.alerte.hidden = false;
        } else {
            el.alerte.hidden = true;
        }
    }

    // ------------------------------------------------------------------ Dessin

    let dessinPrevu = false;
    function redessiner() {
        if (!dessinPrevu) {
            dessinPrevu = true;
            requestAnimationFrame(() => {
                dessinPrevu = false;
                dessiner();
            });
        }
    }

    /** Rareté choisie, ou la première disponible pour que l'aperçu ait un cadre. */
    function rareteCourante() {
        const id = el.rarete.value;
        if (id && cfg.raretes[id]) {
            return id;
        }
        return Object.keys(cfg.raretes)[0];
    }

    function cadre(id) {
        if (!cadres[id]) {
            const img = new Image();
            img.onload = redessiner;
            img.src = cfg.raretes[id].cadre;
            cadres[id] = img;
        }
        return cadres[id].complete && cadres[id].naturalWidth ? cadres[id] : null;
    }

    function dessiner() {
        ctx.setTransform(ECHELLE_CANVAS, 0, 0, ECHELLE_CANVAS, 0, 0);
        ctx.clearRect(0, 0, W, H);

        if (image && cadrage) {
            ctx.save();
            ctx.beginPath();
            ctx.rect(F.x, F.y, F.w, F.h);
            ctx.clip();
            ctx.imageSmoothingQuality = 'high';
            ctx.drawImage(image, cadrage.x, cadrage.y, image.naturalWidth * cadrage.scale, image.naturalHeight * cadrage.scale);
            ctx.restore();
        } else {
            ctx.fillStyle = '#15181c';
            ctx.fillRect(F.x, F.y, F.w, F.h);
        }

        const id = rareteCourante();
        const imgCadre = cadre(id);
        if (imgCadre) {
            ctx.drawImage(imgCadre, 0, 0, W, H);
        }

        if (!policePrete) {
            return;
        }
        const couleurs = cfg.raretes[id];
        ecrire(el.nom.value.trim(), cfg.textes.nom, couleurs);
        const optionJeu = el.jeu.selectedOptions[0];
        if (el.jeu.value && optionJeu) {
            ecrire(optionJeu.textContent.trim(), cfg.textes.jeu, couleurs);
        }
        if (el.numero.value !== '') {
            ecrire(el.numero.value, cfg.textes.numero, couleurs);
        }
    }

    const rgba = ([r, g, b], a = 1) => `rgba(${r}, ${g}, ${b}, ${a})`;

    /**
     * Même algorithme que CardJeuComposer::ecrire() : taille réduite par pas de 2 points
     * tant que le texte dépasse, centrage sur la boîte englobante, halo en couches.
     * GD exprime les tailles en points à 96 dpi : 1 pt = 4/3 px.
     */
    function ecrire(texte, zone, couleurs) {
        if (texte === '') {
            return;
        }
        let taille = zone.taille;
        let m;
        let largeur;
        do {
            ctx.font = `${taille * 4 / 3}px "CarteJeu"`;
            m = ctx.measureText(texte);
            largeur = m.actualBoundingBoxLeft + m.actualBoundingBoxRight;
        } while (largeur > zone.largeurMax && (taille -= 2) > 10);

        const x = Math.round(zone.cx - largeur / 2 + m.actualBoundingBoxLeft);
        const y = Math.round(zone.cy + (m.actualBoundingBoxAscent - m.actualBoundingBoxDescent) / 2);

        for (const [rayon, opacite, cle] of cfg.couchesHalo) {
            ctx.fillStyle = rgba(couleurs[cle], opacite);
            for (let angle = 0; angle < 360; angle += cfg.pasHalo) {
                const rad = angle * Math.PI / 180;
                ctx.fillText(texte, x + Math.round(rayon * Math.cos(rad)), y + Math.round(rayon * Math.sin(rad)));
            }
        }
        ctx.fillStyle = rgba(couleurs.remplissage);
        ctx.fillText(texte, x, y);
    }

    // ------------------------------------------------------------------ Chargement

    function chargerImage(url, cadrageInitial) {
        const img = new Image();
        img.onload = () => {
            image = img;
            el.vide.hidden = true;
            el.controles.hidden = false;
            el.aide.hidden = false;
            el.zoom.max = String(cfg.zoomMax);
            appliquer(cadrageInitial || cadrageParDefaut());
        };
        img.onerror = () => {
            el.alerte.textContent = 'Impossible de lire cette image.';
            el.alerte.hidden = false;
        };
        img.src = url;
    }

    const police = new FontFace('CarteJeu', `url(${cfg.police})`);
    police.load().then((f) => {
        document.fonts.add(f);
        policePrete = true;
        redessiner();
    }).catch(() => {
        // Sans la police, l'aperçu reste utilisable (image + cadre) ; le serveur l'a de toute façon.
    });

    el.image.addEventListener('change', () => {
        const fichier = el.image.files[0];
        if (fichier) {
            chargerImage(URL.createObjectURL(fichier), null);
        }
    });

    if (cfg.initial) {
        chargerImage(cfg.initial.source, cfg.initial);
    }

    // ------------------------------------------------------------------ Textes

    let numeroModifie = el.numero.value !== '';
    el.numero.addEventListener('input', () => {
        numeroModifie = true;
        redessiner();
    });

    function proposerNumero() {
        if (!cfg.prochainsNumeros || numeroModifie) {
            return;
        }
        el.numero.value = el.jeu.value ? (cfg.prochainsNumeros[el.jeu.value] || 1) : '';
    }

    el.nom.addEventListener('input', redessiner);
    el.rarete.addEventListener('change', redessiner);
    el.jeu.addEventListener('change', () => {
        proposerNumero();
        redessiner();
    });
    proposerNumero();

    // ------------------------------------------------------------------ Souris, doigt, clavier

    /** Pixels de la carte par pixel affiché à l'écran. */
    const pixelsCarte = () => W / canvas.getBoundingClientRect().width;

    function pointCarte(clientX, clientY) {
        const r = canvas.getBoundingClientRect();
        return { x: (clientX - r.left) * W / r.width, y: (clientY - r.top) * H / r.height };
    }

    const pointeurs = new Map();
    let pincement = null; // { distance, scale }

    canvas.addEventListener('pointerdown', (e) => {
        if (!image) {
            return;
        }
        canvas.setPointerCapture(e.pointerId);
        pointeurs.set(e.pointerId, { x: e.clientX, y: e.clientY });
        canvas.classList.add('en-deplacement');
        if (pointeurs.size === 2) {
            const [a, b] = [...pointeurs.values()];
            pincement = { distance: Math.hypot(a.x - b.x, a.y - b.y), scale: cadrage.scale };
        }
    });

    canvas.addEventListener('pointermove', (e) => {
        const precedent = pointeurs.get(e.pointerId);
        if (!precedent) {
            return;
        }
        pointeurs.set(e.pointerId, { x: e.clientX, y: e.clientY });

        if (pointeurs.size === 2 && pincement) {
            const [a, b] = [...pointeurs.values()];
            const centre = pointCarte((a.x + b.x) / 2, (a.y + b.y) / 2);
            zoomerVers(pincement.scale * Math.hypot(a.x - b.x, a.y - b.y) / pincement.distance, centre.x, centre.y);
        } else if (pointeurs.size === 1) {
            const k = pixelsCarte();
            appliquer({
                x: cadrage.x + (e.clientX - precedent.x) * k,
                y: cadrage.y + (e.clientY - precedent.y) * k,
                scale: cadrage.scale,
            });
        }
    });

    function relacher(e) {
        pointeurs.delete(e.pointerId);
        if (pointeurs.size < 2) {
            pincement = null;
        }
        if (pointeurs.size === 0) {
            canvas.classList.remove('en-deplacement');
        }
    }
    canvas.addEventListener('pointerup', relacher);
    canvas.addEventListener('pointercancel', relacher);

    canvas.addEventListener('wheel', (e) => {
        if (!image) {
            return;
        }
        e.preventDefault();
        const p = pointCarte(e.clientX, e.clientY);
        zoomerVers(cadrage.scale * Math.exp(-e.deltaY * 0.0015), p.x, p.y);
    }, { passive: false });

    el.zoom.addEventListener('input', () => {
        zoomerVers(echelleMin() * parseFloat(el.zoom.value), F.x + F.w / 2, F.y + F.h / 2);
    });

    el.recentrer.addEventListener('click', () => appliquer(cadrageParDefaut()));

    // Flèches : déplacer (Maj = plus vite), + / - : zoomer.
    canvas.tabIndex = 0;
    canvas.addEventListener('keydown', (e) => {
        if (!image) {
            return;
        }
        const pas = e.shiftKey ? 60 : 12;
        const deplacements = { ArrowLeft: [-pas, 0], ArrowRight: [pas, 0], ArrowUp: [0, -pas], ArrowDown: [0, pas] };
        if (deplacements[e.key]) {
            const [dx, dy] = deplacements[e.key];
            appliquer({ x: cadrage.x + dx, y: cadrage.y + dy, scale: cadrage.scale });
        } else if (e.key === '+' || e.key === '-') {
            zoomerVers(cadrage.scale * (e.key === '+' ? 1.05 : 1 / 1.05), F.x + F.w / 2, F.y + F.h / 2);
        } else {
            return;
        }
        e.preventDefault();
    });

    dessiner();
})();
