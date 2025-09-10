#!/usr/bin/env php
<?php
/*
 * Project: SluInk (Markdown → HTML generator)
 * Copyright (C) 2025 Manuel Regamey
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/* SluInk build.php
 * ------------------------------------------------------------------
 * But : convertir les fichiers Markdown de /source en HTML statique
 * dans /out en utilisant le template Pandoc fourni (SluInk.html5).
 *
 * Repères :
 *  - Réglages : constantes/chemins (assets, template, source, out)
 *  - Préflight : vérifications de présence (pandoc, fichiers, dossiers)
 *  - Copie d'assets : style.css, script.js, icônes (sun/moon/file)
 *  - Conversion : génération de index.html puis des autres pages
 *  - Métadonnées transmises à Pandoc :
 *      css_href, js_href, sun_href, moon_href, file_href, home_href, footer
 *    + si navigation : prev_href, next_href, prev_title, next_title
 *  - Filtre Lua (si présent) pour réécrire les liens .md -> .html
 *
 * Usage :
 *    php build.php
 */

declare(strict_types=1);
error_reporting(E_ALL);

//
// ===== Réglages =====
//
$BASE_DIR   = realpath(__DIR__);
$SRC_DIR    = $BASE_DIR . '/source';   // répertoires contenant les .md et autres asstets
$OUT_DIR    = $BASE_DIR . '/out';  // site généré
$TEMPLATE   = $BASE_DIR . '/template/SluInk.html5';
$CSS_SOURCE = $BASE_DIR . '/assets/style/style.css';
$JS_SOURCE  = $BASE_DIR . '/assets/script/script.js';
$SUN_SOURCE = $BASE_DIR . '/assets/img/sun-regular-full.svg';
$MOON_SOURCE = $BASE_DIR . '/assets/img/moon-regular-full.svg';
$FILE_SOURCE = $BASE_DIR . '/assets/img/file-regular-full.svg';
$SITE_TITLE = 'MonSite';
$FOOTER_TXT = '© ' . date('Y') . ' ' . $SITE_TITLE . ' Généré avec [SluInk]';

$PANDOC = 'pandoc';  // chemin vers pandoc (ex: /usr/bin/pandoc)
$RSYNC  = 'rsync';   // chemin vers rsync (facultatif, mais recommandé)

//
// ===== Utilitaires =====
//
function fail(string $msg): void
{
    fwrite(STDERR, "[build.php] $msg\n");
    exit(1);
}

function join_paths(string ...$parts): string
{
    return preg_replace('#/+#', '/', join('/', $parts));
}

function ensure_dir(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        fail("Impossible de créer $dir");
    }
}

function relpath(string $path, string $from): string
{
    $from = rtrim($from, '/') . '/';
    return ltrim(str_replace($from, '', $path), '/');
}

function has_cmd(string $cmd): bool
{
    $out = @shell_exec("command -v " . escapeshellarg($cmd) . " 2>/dev/null");
    return is_string($out) && trim($out) !== '';
}

function copy_to($src, $dst)
{
    ensure_dir(dirname($dst));        // <-- garantit le dossier cible (ex: out/assets)
    if (!copy($src, $dst)) {
        fail("Copie échouée: $src -> $dst");
    }
}
/** Tri naturel insensible à la casse (in place) */
function naturalsort(array &$arr): void
{
    natcasesort($arr);
    $arr = array_values($arr);
}

/** Exécute une commande shell (stdin optionnel) -> [code, stdout, stderr] */
function run(string $cmd, ?string $stdin = null): array
{
    $des = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open($cmd, $des, $pipes);
    if (!\is_resource($proc)) fail("Impossible d’exécuter la commande: $cmd");
    if ($stdin !== null) {
        fwrite($pipes[0], $stdin);
    }
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    return [$code, $out, $err];
}

/** Liste récursive des fichiers correspondant à un pattern (fnmatch sur le nom) */
function rglob(string $base, string $pattern): array
{
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    $files = [];
    foreach ($it as $fi) {
        if ($fi->isFile() && fnmatch($pattern, $fi->getFilename())) $files[] = $fi->getPathname();
    }
    return $files;
}

/** Slugifie UNIQUEMENT le nom de fichier (pas les dossiers) : espaces -> "_", ponctuation -> "_", minuscules */
function slugify_leaf(string $filename): string
{
    if (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $filename);
        if ($t !== false) $filename = $t;
    }
    $filename = mb_strtolower($filename, 'UTF-8');
    $filename = preg_replace('/\s+/', '_', $filename);           // espaces -> _
    $filename = preg_replace('/[^a-z0-9._-]+/', '_', $filename);  // reste -> _
    $filename = preg_replace('/_+/', '_', $filename);
    $filename = preg_replace('/^_+|_+$/', '', $filename);
    return $filename;
}

//
// ===== Vérifs =====
//
if (!file_exists($TEMPLATE))   fail("Template manquant: $TEMPLATE");
if (!file_exists($CSS_SOURCE)) fail("CSS manquante: $CSS_SOURCE");
if (!file_exists($JS_SOURCE))  fail("CSS manquante: $JS_SOURCE");
if (!is_dir($SRC_DIR))         fail("Dossier source manquant: $SRC_DIR");
ensure_dir($OUT_DIR);


// pandoc dispo ?
[$code] = run(escapeshellcmd($PANDOC) . ' -v');
if ($code !== 0) fail("pandoc introuvable (mets le bon chemin dans \$PANDOC)");

// ===== Lua filter : liens *.md -> *.html (slug uniquement du nom de fichier) =====
$LUA_FILTER = join_paths($BASE_DIR, 'rewrite_md_links.lua');
file_put_contents($LUA_FILTER, <<<LUA
local function slug_leaf(name)
  name = string.lower(name)
  name = name:gsub("%s+", "_")
  name = name:gsub("[^%w%._%-]", "_")
  name = name:gsub("_+", "_"):gsub("^_+", ""):gsub("_+$", "")
  return name
end

function Link(el)
  if el.target:match("%.md$") then
    local tgt = el.target:gsub("%.md$", ".html")
    local dir, leaf = tgt:match("^(.-)([^/]+)$")
    if not dir then dir = "" ; leaf = tgt end
    leaf = slug_leaf(leaf)
    el.target = dir .. leaf
  end
  return el
end
LUA);

// ===== Sync assets (exclut .md + CSS de la source) + CSS canonique =====
if (has_cmd($RSYNC)) {
    ensure_dir($OUT_DIR);

    $cmd = sprintf(
        '%s -a --delete --exclude=%s --exclude=%s --exclude=%s %s %s',
        escapeshellcmd($RSYNC),
        escapeshellarg('*.md'),
        escapeshellarg('style.css'),
        escapeshellarg('Style.css'),
        escapeshellarg(rtrim($SRC_DIR, '/') . '/'),
        escapeshellarg(rtrim($OUT_DIR, '/') . '/')
    );
    [$c, $o, $e] = run($cmd);
    if ($c !== 0) fail("rsync a échoué:\n$e");
} else {
    // fallback PHP (sans delete)
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($SRC_DIR, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $fi) {
        if ($fi->isDir()) continue;
        $rel = relpath($fi->getPathname(), $SRC_DIR);
        $name = basename($rel);
        if (preg_match('/\.md$/i', $name)) continue;
        if (strcasecmp($name, 'style.css') === 0) continue;
        $dest = join_paths($OUT_DIR, $rel);
        ensure_dir(dirname($dest));
        copy($fi->getPathname(), $dest);
    }
}


// Copie des assets fixes
copy_to($CSS_SOURCE,  join_paths($OUT_DIR, 'assets/style.css'));
copy_to($JS_SOURCE,   join_paths($OUT_DIR, 'assets/script.js'));
copy_to($SUN_SOURCE,  join_paths($OUT_DIR, 'assets/sun-regular-full.svg'));
copy_to($MOON_SOURCE, join_paths($OUT_DIR, 'assets/moon-regular-full.svg'));
copy_to($FILE_SOURCE, join_paths($OUT_DIR, 'assets/file-regular-full.svg'));



// ===== Lister tous les .md =====
$allMdAbs = rglob($SRC_DIR, '*.md');
$REL = array_map(fn($p) => relpath($p, $SRC_DIR), $allMdAbs);
naturalsort($REL);

// ===== Conversion .md -> .html =====
foreach ($REL as $rel) {
    $inMd   = join_paths($SRC_DIR, $rel);
    $relDir = dirname($rel);
    $leaf   = basename($rel); // "Partie 1.md"
    $leafHtml = slugify_leaf(preg_replace('/\.md$/i', '.html', $leaf)); // "partie_1.html"

    // même dossier, nom de fichier sluggifié
    $outHtml = join_paths($OUT_DIR, ($relDir === '.' ? '' : $relDir), $leafHtml);
    ensure_dir(dirname($outHtml));

    // préfixe relatif (style.css + index.html)
    $depth  = ($relDir === '.' ? 0 : substr_count($relDir, '/') + 1);
    $relprefix = $depth > 0 ? str_repeat('../', $depth) : '';
    $css_href  = $relprefix . 'assets/style.css';
    $home_href = $relprefix . 'index.html';
    $js_href   = $relprefix . 'assets/script.js';
    $sun_href  = $relprefix . 'assets/sun-regular-full.svg';
    $moon_href = $relprefix . 'assets/moon-regular-full.svg';
    $file_href = $relprefix . 'assets/file-regular-full.svg';

    // Prev/Next dans le même dossier (href sluggifiés)
    $listDir = $relDir === '.' ? $SRC_DIR : join_paths($SRC_DIR, $relDir);
    $siblings = array_values(array_filter(scandir($listDir) ?: [], fn($n) => preg_match('/\.md$/i', $n)));
    naturalsort($siblings);

    $idx = array_search($leaf, $siblings, true);
    $prev_href = $prev_title = $next_href = $next_title = '';

    if ($idx !== false) {
        if ($idx > 0) {
            $prevBase   = $siblings[$idx - 1];
            $prev_href  = slugify_leaf(preg_replace('/\.md$/i', '.html', $prevBase));
            $prev_title = preg_replace('/\.md$/i', '', $prevBase);
        }
        if ($idx < count($siblings) - 1) {
            $nextBase   = $siblings[$idx + 1];
            $next_href  = slugify_leaf(preg_replace('/\.md$/i', '.html', $nextBase));
            $next_title = preg_replace('/\.md$/i', '', $nextBase);
        }
    }

    $baseNoExt  = preg_replace('/\.md$/i', '', $leaf);
    $page_title = $SITE_TITLE . '  ' . $baseNoExt;

    // pandoc
    $cmd = sprintf(
        '%s %s',
        escapeshellcmd($PANDOC),
        implode(' ', [
            escapeshellarg($inMd),
            '-f markdown -t html5 --standalone',
            '--toc --toc-depth=3',
            '--metadata ' . escapeshellarg('lang=fr'),
            '--metadata ' . escapeshellarg('title=' . $page_title),
            '--metadata ' . escapeshellarg('site_title=' . $SITE_TITLE),
            '--metadata ' . escapeshellarg('footer=' . $FOOTER_TXT),
            '--metadata ' . escapeshellarg('css_href=' . $css_href),
            '--metadata ' . escapeshellarg('home_href=' . $home_href),
            '--metadata ' . escapeshellarg('js_href=' . $js_href),
            '--metadata ' . escapeshellarg('sun_href='  . $sun_href),
            '--metadata ' . escapeshellarg('moon_href=' . $moon_href),
            '--metadata ' . escapeshellarg('file_href=' . $file_href),
            ($prev_href  ? '--metadata ' . escapeshellarg('prev_href='  . $prev_href)  : ''),
            ($prev_title ? '--metadata ' . escapeshellarg('prev_title=' . $prev_title) : ''),
            ($next_href  ? '--metadata ' . escapeshellarg('next_href='  . $next_href)  : ''),
            ($next_title ? '--metadata ' . escapeshellarg('next_title=' . $next_title) : ''),
            '--template ' . escapeshellarg($TEMPLATE),
            '--lua-filter ' . escapeshellarg($LUA_FILTER),
            '-o ' . escapeshellarg($outHtml),
        ])
    );
    [$c, $o, $e] = run($cmd);
    if ($c !== 0) fail("pandoc a échoué pour $rel:\n$e");
    echo "[build.php] -> " . relpath($outHtml, $OUT_DIR) . PHP_EOL;
}

// ===== Sommaire (index.html) =====
$groups = [];
foreach ($REL as $rel) {
    $top = (strpos($rel, '/') === false) ? 'Racine' : strtok($rel, '/');
    $groups[$top][] = $rel;
}
uksort($groups, 'strnatcasecmp');

$mdIndex = "# Sommaire\n";
foreach ($groups as $top => $items) {
    $mdIndex .= "\n## " . $top . "\n";
    natcasesort($items);
    foreach ($items as $item) {
        $relDir = dirname($item);
        $leaf   = basename($item);
        $href   = ($relDir === '.' ? '' : $relDir . '/') . slugify_leaf(preg_replace('/\.md$/i', '.html', $leaf));
        $label  = preg_replace('/\.md$/i', '', $item);
        $mdIndex .= "- [" . $label . "](<" . $href . ">)\n";
    }
}

$indexHtml = join_paths($OUT_DIR, 'index.html');
$cmd = sprintf(
    '%s %s',
    escapeshellcmd($PANDOC),
    implode(' ', [
        '-f markdown -t html5 --standalone',
        '--template ' . escapeshellarg($TEMPLATE),
        '--metadata ' . escapeshellarg('lang=fr'),
        '--metadata ' . escapeshellarg('title=' . $SITE_TITLE . '  Sommaire'),
        '--metadata ' . escapeshellarg('site_title=' . $SITE_TITLE),
        '--metadata ' . escapeshellarg('footer=' . $FOOTER_TXT),
        '--metadata ' . escapeshellarg('css_href=assets/style.css'),
        '--metadata ' . escapeshellarg('home_href=index.html'),
        '--metadata ' . escapeshellarg('js_href=assets/script.js'),
        '--metadata ' . escapeshellarg('sun_href=assets/sun-regular-full.svg'),
        '--metadata ' . escapeshellarg('moon_href=assets/moon-regular-full.svg'),
        '--metadata ' . escapeshellarg('file_href=assets/file-regular-full.svg'),
        '-o ' . escapeshellarg($indexHtml),
    ])
);
[$c, $o, $e] = run($cmd, $mdIndex);
if ($c !== 0) fail("pandoc a échoué pour l’index:\n$e");

echo "[build.php] Terminé. Ouvre: $indexHtml\n";
