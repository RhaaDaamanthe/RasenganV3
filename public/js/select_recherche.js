/**
 * Remplace un <select data-recherche="Placeholder..."> par un champ de recherche :
 * on tape quelques lettres (accents et majuscules ignorés) et la liste se filtre.
 *
 * Le <select> d'origine reste dans le formulaire (masqué) : c'est lui qui est envoyé,
 * et il reçoit un événement `change` à chaque choix (l'aperçu de l'éditeur jeu en dépend).
 */
(() => {
    const normaliser = (texte) => texte
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .trim();

    let compteur = 0;

    function ameliorer(select) {
        const id = `select-recherche-${++compteur}`;
        const options = Array.from(select.options)
            .filter((option) => option.value !== '')
            .map((option) => ({ value: option.value, label: option.textContent.trim(), cle: normaliser(option.textContent) }));

        const wrapper = document.createElement('div');
        wrapper.className = 'select-recherche';

        const input = document.createElement('input');
        input.type = 'text';
        input.className = select.className;
        input.placeholder = select.dataset.recherche || 'Rechercher...';
        input.autocomplete = 'off';
        input.spellcheck = false;
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-controls', id);

        // Le libellé du champ pointe désormais vers la zone de saisie.
        if (select.id) {
            const label = document.querySelector(`label[for="${select.id}"]`);
            if (label) {
                input.id = `${select.id}_recherche`;
                label.htmlFor = input.id;
            }
        }

        const liste = document.createElement('ul');
        liste.className = 'select-recherche-liste';
        liste.id = id;
        liste.setAttribute('role', 'listbox');
        liste.hidden = true;

        // Un <select> masqué ne peut pas afficher la bulle « champ obligatoire » :
        // la validation est reportée sur la zone de saisie.
        const obligatoire = select.required;
        select.required = false;
        select.hidden = true;
        select.tabIndex = -1;

        select.after(wrapper);
        wrapper.append(input, liste, select);

        let resultats = [];
        let actif = -1;

        const libelleChoisi = () => options.find((o) => o.value === select.value)?.label ?? '';

        function majValidite() {
            input.setCustomValidity(obligatoire && !select.value ? 'Choisissez un élément dans la liste.' : '');
        }

        function choisir(option) {
            const valeur = option ? option.value : '';
            const change = select.value !== valeur;
            select.value = valeur;
            input.value = option ? option.label : '';
            majValidite();
            fermer();
            if (change) {
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        function surligner(label, recherche) {
            const fragment = document.createDocumentFragment();
            const debut = recherche ? normaliser(label).indexOf(recherche) : -1;
            // La normalisation garde la longueur des lettres de base : les index restent valables
            // sauf caractères décomposés exotiques, auquel cas on affiche le libellé brut.
            if (debut < 0 || normaliser(label).length !== label.length) {
                fragment.append(label);
                return fragment;
            }
            const mark = document.createElement('mark');
            mark.textContent = label.slice(debut, debut + recherche.length);
            fragment.append(label.slice(0, debut), mark, label.slice(debut + recherche.length));
            return fragment;
        }

        function afficher(recherche) {
            const cle = normaliser(recherche);
            resultats = cle === ''
                ? options
                : options
                    .filter((o) => o.cle.includes(cle))
                    // Ceux qui commencent par la recherche d'abord, puis l'ordre alphabétique d'origine.
                    .sort((a, b) => Number(!a.cle.startsWith(cle)) - Number(!b.cle.startsWith(cle)));

            liste.replaceChildren();
            if (resultats.length === 0) {
                const vide = document.createElement('li');
                vide.className = 'select-recherche-vide';
                vide.textContent = 'Aucun résultat';
                liste.append(vide);
            }
            resultats.forEach((option, index) => {
                const li = document.createElement('li');
                li.id = `${id}-${index}`;
                li.setAttribute('role', 'option');
                li.dataset.index = String(index);
                if (option.value === select.value) {
                    li.setAttribute('aria-selected', 'true');
                }
                li.append(surligner(option.label, cle));
                liste.append(li);
            });

            const indexChoisi = resultats.findIndex((o) => o.value === select.value);
            activer(cle === '' ? indexChoisi : 0);
            liste.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }

        function activer(index) {
            const items = liste.querySelectorAll('[role="option"]');
            items.forEach((li) => li.classList.remove('actif'));
            actif = items.length ? Math.max(-1, Math.min(index, items.length - 1)) : -1;
            if (actif >= 0) {
                items[actif].classList.add('actif');
                items[actif].scrollIntoView({ block: 'nearest' });
                input.setAttribute('aria-activedescendant', items[actif].id);
            } else {
                input.removeAttribute('aria-activedescendant');
            }
        }

        function fermer() {
            liste.hidden = true;
            actif = -1;
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
        }

        // En quittant le champ : texte vide = aucun choix, texte exact = ce choix,
        // sinon on revient au choix précédent.
        function valider() {
            const cle = normaliser(input.value);
            if (cle === '') {
                choisir(null);
                return;
            }
            const exact = options.find((o) => o.cle === cle);
            if (exact) {
                choisir(exact);
                return;
            }
            input.value = libelleChoisi();
            fermer();
        }

        input.addEventListener('focus', () => {
            input.select();
            afficher('');
        });
        input.addEventListener('click', () => {
            if (liste.hidden) {
                afficher('');
            }
        });
        input.addEventListener('input', () => afficher(input.value));
        input.addEventListener('blur', valider);

        input.addEventListener('keydown', (e) => {
            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    if (liste.hidden) {
                        afficher(input.value === libelleChoisi() ? '' : input.value);
                    } else {
                        activer(actif + 1);
                    }
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    activer(actif - 1);
                    break;
                case 'Enter':
                    // Ne pas envoyer le formulaire tant que la liste est ouverte.
                    if (!liste.hidden) {
                        e.preventDefault();
                        if (actif >= 0) {
                            choisir(resultats[actif]);
                        } else {
                            valider();
                        }
                    }
                    break;
                case 'Escape':
                    if (!liste.hidden) {
                        e.preventDefault();
                        input.value = libelleChoisi();
                        fermer();
                    }
                    break;
            }
        });

        // mousedown plutôt que click : il passe avant le blur de la zone de saisie.
        liste.addEventListener('mousedown', (e) => {
            e.preventDefault();
            const li = e.target.closest('[role="option"]');
            if (li) {
                choisir(resultats[Number(li.dataset.index)]);
            }
        });

        input.value = libelleChoisi();
        majValidite();
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('select[data-recherche]').forEach(ameliorer);
    });
})();
