# SluInk

Ce dépôt propose une architecture simple pour **générer des pages HTML à partir de fichiers Markdown** en utilisant Pandoc.  
Il est pensé comme un support pédagogique et comme une base pour créer rapidement un site statique avec du contenu écrit en `.md`.

---

## Dépendances

Avant utilisation, il faut installer les outils suivants :

- [Pandoc](https://pandoc.org/) → conversion des fichiers `.md` en `.html`.  
- [PHP 8.3](https://www.php.net/) (ou version plus récente) → scripts de gestion et génération.    

### Installation rapide sous Linux (exemple Debian/LMDE)

```bash
sudo apt update
sudo apt install pandoc php8.3-cli make
```

---

## Architecture du projet

* `source/` → contient les fichiers Markdown (`.md`).
* `templates/` → modèles HTML de mise en page.
* `out/` → pages HTML générées automatiquement.
* `build.php` → script en PHP pour lancer la génération. Dans ce script on peut personnaliser le nom du site à générer.
* `assets/` → contient les svg pour icones le style.css et script.js qui seront utilisés dans les pages html générées.

---

## Utilisation

1. Placez vos fichiers `.md` et assets (fichiers images ou autres) dans le dossier `source/`. Le site statique généré reprendra l'arborescence du répertoire contenant les fichiers `.md`.
2. Lancez la génération avec Pandoc  via le script PHP :

```bash
php build.php
```

---

## Objectif du projet

* Fournir une **base légère** pour écrire des cours, articles ou documentations en Markdown.
* Générer facilement des **pages HTML lisibles** sans avoir à coder en HTML/CSS directement.

---

## License

This project is licensed under the GNU General Public License v3.0 (GPLv3) – see the [LICENSE](LICENSE) file for details.
