<?php
session_start();
require 'config.php';

// Vérification de connexion
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

/* ---------------------------
   Helpers
--------------------------- */
function extractYoutubeId($url)
{
    $url = trim((string)$url);
    if ($url === '') return '';

    if (preg_match('~youtu\.be/([a-zA-Z0-9_-]{6,})~', $url, $m)) return $m[1];
    if (preg_match('~[?&]v=([a-zA-Z0-9_-]{6,})~', $url, $m)) return $m[1];
    if (preg_match('~youtube\.com/shorts/([a-zA-Z0-9_-]{6,})~', $url, $m)) return $m[1];
    if (preg_match('~youtube\.com/embed/([a-zA-Z0-9_-]{6,})~', $url, $m)) return $m[1];

    return '';
}

function slugify($str)
{
    $str = trim((string)$str);
    if ($str === '') return 'categorie';

    $str = mb_strtolower($str, 'UTF-8');
    $str = preg_replace('~[\s\'"’`]+~u', '-', $str);
    $str = preg_replace('~[^\pL\pN\-]+~u', '', $str);

    if (function_exists('iconv')) {
        $tmp = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
        if ($tmp !== false) $str = $tmp;
    }

    $str = preg_replace('~[^a-z0-9\-]+~', '', $str);
    $str = preg_replace('~-+~', '-', $str);
    $str = trim($str, '-');

    return $str ?: 'categorie';
}

/* ---------------------------
   Catégories : ajout / suppression
--------------------------- */
if (isset($_POST['add_category'])) {
    $name = trim($_POST['category_name'] ?? '');
    if ($name === '') {
        header('Location: admin_gestion.php?msg=cat_empty');
        exit();
    }

    $slug = slugify($name);

    // slug unique
    $base = $slug;
    $i = 2;
    while (true) {
        $chk = $bdd->prepare('SELECT 1 FROM categories WHERE slug = ?');
        $chk->execute([$slug]);
        if (!$chk->fetch()) break;
        $slug = $base . '-' . $i;
        $i++;
    }

    try {
        $ins = $bdd->prepare('INSERT INTO categories (name, slug, created_at) VALUES (?, ?, NOW())');
        $ins->execute([$name, $slug]);
        header('Location: admin_gestion.php?msg=cat_added');
        exit();
    } catch (Throwable $e) {
        header('Location: admin_gestion.php?msg=cat_exists');
        exit();
    }
}

if (isset($_GET['del_cat'])) {
    $idc = (int)($_GET['del_cat'] ?? 0);
    if ($idc > 0) {
        $bdd->prepare('DELETE FROM categories WHERE id = ?')->execute([$idc]);
    }
    header('Location: admin_gestion.php?msg=cat_deleted');
    exit();
}

// Liste catégories
$cats = [];
try {
    $cats = $bdd->query('SELECT id, name, slug FROM categories ORDER BY name ASC')->fetchAll();
} catch (Throwable $e) {
    $cats = [];
}

/* ---------------------------
   Ajout article
--------------------------- */
if (isset($_POST['add'])) {
    $titre = trim($_POST['titre'] ?? '');
    $desc = trim($_POST['desc'] ?? '');
    $contenu = trim($_POST['contenu'] ?? '');

    // Police (une pour l'article)
    $font_family = trim($_POST['font_family'] ?? 'sans');
    $allowedFonts = ['sans', 'serif', 'mono'];
    if (!in_array($font_family, $allowedFonts, true)) {
        $font_family = 'sans';
    }

    // Catégories (multi)
    $category_ids = $_POST['category_ids'] ?? [];
    $category_ids = array_values(array_unique(array_filter(array_map('intval', (array)$category_ids))));

    // YouTube optionnel (ID)
    $youtube_input = trim($_POST['video_youtube'] ?? '');

    $image_name = "";
    $video_name = "";        // mp4 local
    $video_youtube_id = "";  // youtube id

    // Règle : MP4 OU YouTube
    $has_upload_video = (isset($_FILES['video']) && $_FILES['video']['error'] == 0);
    $has_youtube = ($youtube_input !== '');

    if ($has_upload_video && $has_youtube) {
        header('Location: admin_gestion.php?msg=video_conflict');
        exit();
    }

    if ($has_youtube) {
        $video_youtube_id = extractYoutubeId($youtube_input);
        if ($video_youtube_id === '') {
            header('Location: admin_gestion.php?msg=youtube_invalid');
            exit();
        }
    }

    // Image
    if (isset($_FILES['img']) && $_FILES['img']['error'] == 0) {
        $tmp_name = $_FILES['img']['tmp_name'];
        $name = basename($_FILES['img']['name']);
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $extensions_autorisees = array('jpg', 'jpeg', 'png', 'gif', 'webp');

        if (in_array($extension, $extensions_autorisees)) {
            $nouveau_nom = uniqid() . '_' . str_replace(' ', '_', $name);
            if (move_uploaded_file($tmp_name, 'images/' . $nouveau_nom)) {
                $image_name = $nouveau_nom;
            }
        }
    }

    // Vidéo locale MP4
    if ($has_upload_video) {
        $tmp_video = $_FILES['video']['tmp_name'];
        $name_video = basename($_FILES['video']['name']);
        $ext_video = strtolower(pathinfo($name_video, PATHINFO_EXTENSION));

        if ($ext_video !== 'mp4') {
            header('Location: admin_gestion.php?msg=video_invalid');
            exit();
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp_video);
        if ($mime !== 'video/mp4') {
            header('Location: admin_gestion.php?msg=video_invalid');
            exit();
        }

        $nouveau_nom_video = 'vid_' . uniqid() . '.mp4';
        if (move_uploaded_file($tmp_video, 'videos/' . $nouveau_nom_video)) {
            $video_name = $nouveau_nom_video;
        }
    }

    // Insert article (ajout font_family)
    $ins = $bdd->prepare('
        INSERT INTO articles (titre, categorie, description, contenu_complet, image_url, video, video_youtube, font_family, date_publication)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ');
    $ins->execute([$titre, '', $desc, $contenu, $image_name, $video_name, $video_youtube_id, $font_family]);

    $article_id = (int)$bdd->lastInsertId();

    // Insert pivot catégories
    if (!empty($category_ids)) {
        $insPivot = $bdd->prepare('INSERT IGNORE INTO article_categories (article_id, category_id) VALUES (?, ?)');
        foreach ($category_ids as $cid) {
            $insPivot->execute([$article_id, $cid]);
        }
    }

    header('Location: admin_gestion.php?msg=success');
    exit();
}

/* ---------------------------
   Suppression article
--------------------------- */
if (isset($_GET['del'])) {
    $id_del = (int)($_GET['del'] ?? 0);
    if ($id_del > 0) {
        $q = $bdd->prepare('SELECT image_url, video FROM articles WHERE id = ?');
        $q->execute([$id_del]);
        $row = $q->fetch();
        if ($row) {
            if (!empty($row['image_url'])) {
                $imgPath = __DIR__ . '/images/' . $row['image_url'];
                if (is_file($imgPath)) @unlink($imgPath);
            }
            if (!empty($row['video'])) {
                $vidPath = __DIR__ . '/videos/' . $row['video'];
                if (is_file($vidPath)) @unlink($vidPath);
            }
        }

        $bdd->prepare('DELETE FROM articles WHERE id = ?')->execute([$id_del]);
    }

    header('Location: admin_gestion.php');
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard Admin - Cabinet Carré</title>
    <link rel="shortcut icon" href="./images/favicon.png" type="image/x-icon">
    <meta charset="UTF-8">
    <style>
        body { font-family:'Segoe UI',sans-serif; background:#f4f7f6; margin:0; display:flex; }
        .sidebar { width:260px; background:#1a2b3c; color:#fff; min-height:100vh; padding:30px 20px; box-sizing:border-box; position:fixed; }
        .content { flex:1; padding:50px; margin-left:260px; }
        .sidebar-logo { width:100%; background:#fff; padding:15px; border-radius:8px; box-sizing:border-box; margin-bottom:20px; }
        .form-card { background:#fff; padding:30px; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.05); margin-bottom:40px; }
        input[type="text"], input[type="url"], textarea { width:100%; padding:12px; margin:10px 0; border:1px solid #ddd; border-radius:6px; box-sizing:border-box; font-size:14px; }
        input[type="file"] { margin:5px 0 20px 0; padding:10px; background:#f9f9f9; width:100%; border:1px dashed #ccc; border-radius:6px; }
        button { background:#1a2b3c; color:#fff; border:none; padding:12px 30px; border-radius:6px; cursor:pointer; font-weight:600; margin-top:10px; transition:background .3s; }
        button:hover { background:#1f7ea6; }
        table { width:100%; border-collapse:collapse; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 2px 10px rgba(0,0,0,0.05); }
        th { background:#eee; padding:15px; text-align:left; font-size:12px; color:#777; }
        td { padding:15px; border-top:1px solid #f0f0f0; vertical-align:middle; }
        .img-preview { width:50px; height:35px; object-fit:cover; border-radius:4px; border:1px solid #eee; }
        .edit-link { color:#1a2b3c; text-decoration:none; font-weight:bold; font-size:14px; margin-right:15px; }
        .edit-link:hover { color:#1f7ea6; }
        .del-link { color:#e74c3c; text-decoration:none; font-weight:bold; font-size:14px; }
        .msg-success { background:#d4edda; color:#155724; padding:15px; border-radius:6px; margin-bottom:20px; border:1px solid #c3e6cb; }
        .msg-error { background:#ffe9e9; border:1px solid #ffb3b3; color:#7a1d1d; padding:15px; border-radius:6px; margin-bottom:20px; }
        .badge-video { background:#e74c3c; color:#fff; padding:2px 6px; border-radius:4px; font-size:10px; margin-left:5px; vertical-align:middle; }
        .badge-cats { background:#1f7ea6; color:#fff; padding:2px 6px; border-radius:4px; font-size:10px; margin-left:8px; vertical-align:middle; }
        .cats-wrap { display:flex; flex-wrap:wrap; gap:10px; margin-top:10px; }
        .cat-chip { display:flex; gap:8px; align-items:center; background:#f6f6f6; padding:8px 10px; border-radius:999px; }
        .split { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
        @media (max-width: 980px) { .split { grid-template-columns:1fr; } }

        /* Toolbar */
        .cc-editor { width:100%; }
        .cc-toolbar{
            display:flex;
            align-items:center;
            gap:10px;
            padding:10px;
            border:1px solid #ddd;
            background:#0f141a;
            border-radius:8px;
            margin:10px 0 8px;
        }
        .cc-select{
            background:#0f141a;
            color:#fff;
            border:1px solid rgba(255,255,255,.18);
            border-radius:6px;
            padding:8px 10px;
            font-weight:600;
            font-size:13px;
            outline:none;
        }
        .cc-btn{
            background:#0f141a;
            color:#fff;
            border:1px solid rgba(255,255,255,.18);
            border-radius:6px;
            padding:8px 12px;
            cursor:pointer;
            font-size:13px;
        }
        .cc-btn:hover,
        .cc-select:hover{
            border-color: rgba(255,255,255,.35);
        }
        .cc-sep{
            width:1px;
            height:26px;
            background: rgba(255,255,255,.18);
            margin:0 2px;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <img src="images/logo.png" alt="Cabinet Carré" class="sidebar-logo">
    <h2>CABINET CARRÉ</h2>
    <p style="opacity:0.5;">Administration</p><br><br>
    <a href="news.php" target="_blank" style="color:white; text-decoration:none; display:block; margin-bottom:15px;">⬅ Voir le site public</a>
    <a href="logout.php" style="color:#ff7675; text-decoration:none;">Déconnexion</a>
</div>

<div class="content">
    <h1>Gestion des actualités</h1>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'success'): ?><div class="msg-success">Article publié avec succès.</div><?php endif; ?>
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'updated'): ?><div class="msg-success">Article modifié avec succès.</div><?php endif; ?>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'video_conflict'): ?><div class="msg-error">Choisis soit un MP4, soit un lien YouTube (pas les deux).</div><?php endif; ?>
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'youtube_invalid'): ?><div class="msg-error">Lien YouTube invalide.</div><?php endif; ?>
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'video_invalid'): ?><div class="msg-error">Vidéo invalide (MP4 uniquement).</div><?php endif; ?>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'cat_added'): ?><div class="msg-success">Catégorie ajoutée.</div><?php endif; ?>
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'cat_deleted'): ?><div class="msg-success">Catégorie supprimée.</div><?php endif; ?>
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'cat_empty'): ?><div class="msg-error">Nom de catégorie obligatoire.</div><?php endif; ?>
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'cat_exists'): ?><div class="msg-error">Cette catégorie existe déjà.</div><?php endif; ?>

    <div class="split">
        <div class="form-card">
            <h3>Gestion des catégories</h3>
            <form method="POST" style="display:flex; gap:10px; align-items:center;">
                <input type="text" name="category_name" placeholder="Nom (ex: Fiscalité)" required style="margin:0;">
                <button type="submit" name="add_category" style="margin:0;">Ajouter</button>
            </form>

            <div style="margin-top:15px;">
                <?php if (empty($cats)): ?>
                    <p style="color:#666; margin:0;">Aucune catégorie pour l’instant.</p>
                <?php else: ?>
                    <ul style="margin:0; padding-left:18px;">
                        <?php foreach ($cats as $c): ?>
                            <li style="margin:6px 0;">
                                <?= htmlspecialchars($c['name']) ?>
                                <span style="opacity:.6; font-size:12px;">(<?= htmlspecialchars($c['slug']) ?>)</span>
                                <a class="del-link" style="margin-left:10px;"
                                   href="admin_gestion.php?del_cat=<?= (int)$c['id'] ?>"
                                   onclick="return confirm('Supprimer cette catégorie ?')">Supprimer</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-card">
            <h3>Créer une nouvelle actualité</h3>

            <form method="POST" enctype="multipart/form-data">
                <input type="text" name="titre" placeholder="Titre de l'article" required>

                <div style="margin-top:10px;">
                    <label style="font-weight:bold; font-size:14px; display:block;">Catégories (multi)</label>
                    <div class="cats-wrap">
                        <?php if (empty($cats)): ?>
                            <div style="color:#666;">Crée au moins une catégorie.</div>
                        <?php else: ?>
                            <?php foreach ($cats as $c): ?>
                                <label class="cat-chip">
                                    <input type="checkbox" name="category_ids[]" value="<?= (int)$c['id'] ?>">
                                    <?= htmlspecialchars($c['name']) ?>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <textarea name="desc" placeholder="Résumé court pour la carte (max 2 lignes)" rows="3"></textarea>

                <div class="cc-editor">
                    <div class="cc-toolbar">
                        <select class="cc-select" id="ccFont">
                            <option value="sans" selected>Sans Serif</option>
                            <option value="serif">Serif</option>
                            <option value="mono">Monospace</option>
                        </select>

                        <select class="cc-select" id="ccBlock">
                            <option value="p" selected>Paragraphe</option>
                            <option value="h1">Titre 1</option>
                            <option value="h2">Titre 2</option>
                        </select>

                        <div class="cc-sep"></div>

                        <button type="button" class="cc-btn" data-action="bold"><strong>B</strong></button>
                        <button type="button" class="cc-btn" data-action="italic"><em>I</em></button>
                    </div>

                    <input type="hidden" name="font_family" id="font_family" value="sans">
                    <textarea id="contenu" name="contenu" placeholder="Texte complet (mets [[VIDEO]] là où tu veux la vidéo)" style="height:150px;"></textarea>
                </div>

                <label style="font-weight:bold; font-size:14px; display:block;">Image (facultatif) :</label>
                <input type="file" name="img" accept="image/png, image/jpeg, image/jpg, image/webp">

                <label style="font-weight:bold; font-size:14px; display:block;">Vidéo (1 seul choix) :</label>
                <input type="file" name="video" accept="video/mp4">
                <input type="url" name="video_youtube" placeholder="Ou lien YouTube (https://www.youtube.com/watch?v=...)" style="margin-top:-10px;">

                <button type="submit" name="add">Publier maintenant</button>
            </form>
        </div>
    </div>

    <h2>Articles déjà en ligne</h2>

    <table>
        <thead>
            <tr>
                <th width="60">Média</th>
                <th>Titre</th>
                <th>Date</th>
                <th style="text-align:right;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $req = $bdd->query('
                SELECT a.*,
                       GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ", ") AS cats_names
                FROM articles a
                LEFT JOIN article_categories ac ON ac.article_id = a.id
                LEFT JOIN categories c ON c.id = ac.category_id
                GROUP BY a.id
                ORDER BY a.id DESC
            ');

            while ($a = $req->fetch()):
                $hasVideo = (!empty($a['video']) || !empty($a['video_youtube']));
                $catsNames = trim((string)($a['cats_names'] ?? ''));
            ?>
                <tr>
                    <td>
                        <?php if (!empty($a['image_url'])): ?>
                            <img src="images/<?= htmlspecialchars($a['image_url']) ?>" class="img-preview">
                        <?php else: ?>
                            <span style="font-size:20px;">📄</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?= htmlspecialchars($a['titre']) ?></strong>

                        <?php if ($catsNames !== ''): ?>
                            <span class="badge-cats"><?= htmlspecialchars($catsNames) ?></span>
                        <?php endif; ?>

                        <?php if ($hasVideo): ?>
                            <span class="badge-video">Vidéo</span>
                        <?php endif; ?>
                    </td>
                    <td style="color:#888; font-size:12px;"><?= $a['date_publication'] ?></td>
                    <td style="text-align:right;">
                        <a href="admin_modifier.php?id=<?= (int)$a['id'] ?>" class="edit-link">Modifier</a>
                        <a href="admin_gestion.php?del=<?= (int)$a['id'] ?>" class="del-link"
                           onclick="return confirm('Supprimer cet article ?')">Supprimer</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

</div>

<script>
(function(){
    const ta = document.getElementById('contenu');
    const block = document.getElementById('ccBlock');
    const font = document.getElementById('ccFont');
    const fontHidden = document.getElementById('font_family');

    if(!ta) return;

    function wrapSelection(before, after){
        const start = ta.selectionStart;
        const end = ta.selectionEnd;
        const value = ta.value;

        const selected = value.slice(start, end) || '';
        const next = value.slice(0, start) + before + selected + after + value.slice(end);

        ta.value = next;
        const cursorStart = start + before.length;
        const cursorEnd = cursorStart + selected.length;
        ta.focus();
        ta.setSelectionRange(cursorStart, cursorEnd);
    }

    function setLinePrefix(prefix){
        const start = ta.selectionStart;
        const value = ta.value;

        const lineStart = value.lastIndexOf("\n", start - 1) + 1;
        const lineEnd = value.indexOf("\n", start);
        const realLineEnd = (lineEnd === -1) ? value.length : lineEnd;
        const line = value.slice(lineStart, realLineEnd);

        const cleaned = line.replace(/^#{1,2}\s+/, '');
        const newLine = (prefix ? (prefix + cleaned) : cleaned);

        ta.value = value.slice(0, lineStart) + newLine + value.slice(realLineEnd);

        const newPos = Math.min(lineStart + newLine.length, ta.value.length);
        ta.focus();
        ta.setSelectionRange(newPos, newPos);
    }

    document.querySelectorAll('.cc-btn').forEach(btn=>{
        btn.addEventListener('click', ()=>{
            const action = btn.dataset.action;
            if(action === 'bold') wrapSelection('**','**');
            if(action === 'italic') wrapSelection('*','*');
        });
    });

    block.addEventListener('change', ()=>{
        if(block.value === 'p') setLinePrefix('');
        if(block.value === 'h1') setLinePrefix('# ');
        if(block.value === 'h2') setLinePrefix('## ');
        block.value = 'p';
    });

    font.addEventListener('change', ()=>{
        if(fontHidden) fontHidden.value = font.value;
    });
})();
</script>

</body>
</html>
