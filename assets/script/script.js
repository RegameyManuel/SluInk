/*
SPDX-License-Identifier: GPL-3.0-or-later
Copyright (C) 2025 Manuel Regamey
This file is part of SluInk. See the LICENSE file or <https://www.gnu.org/licenses/>.
 */

(() => {
  const root = document.documentElement;
  const KEY = 'SluInk-theme';

  //  util 
  const isDarkLike = (t) => t === 'dark'; // "academic" reste color-scheme: light

  // Init: récupère un thème valide, sinon garde l'existant ou "light"
  (function initTheme() {
    try {
      const saved = localStorage.getItem(KEY);
      const valid = ['light', 'dark', 'academic'];
      const current = saved && valid.includes(saved) ? saved
                    : valid.includes(root.dataset.theme) ? root.dataset.theme
                    : 'light';
      root.dataset.theme = current;
      root.style.colorScheme = isDarkLike(current) ? 'dark' : 'light';
    } catch (e) {
      // fallback doux si storage indispo
      const t = root.dataset.theme || 'light';
      root.style.colorScheme = isDarkLike(t) ? 'dark' : 'light';
    }
  })();

  // Persistance à chaque changement de data-theme
  function persist() {
    try {
      const t = root.dataset.theme;
      const valid = ['light', 'dark', 'academic'];
      const safe = valid.includes(t) ? t : 'light';
      localStorage.setItem(KEY, safe);
      root.style.colorScheme = isDarkLike(safe) ? 'dark' : 'light';
    } catch (e) {}
  }
  new MutationObserver(persist).observe(root, { attributes: true, attributeFilter: ['data-theme'] });

  // Branche les boutons (data-theme-set="light|dark|academic")
  window.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-theme-set]').forEach(btn => {
      btn.addEventListener('click', () => {
        const t = btn.dataset.themeSet;
        if (t) root.dataset.theme = t;
      });
    });
  });
})();

