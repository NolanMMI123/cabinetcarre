<?php
session_start();
require 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

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

function saveMp4Upload($fieldName)
{
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) return '';
    if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) return '';

    $tmp = $_FILES[$fieldName]['tmp_name'];
    $name = basename($_FILES[$fieldName]['name']);
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($ext !== 'mp4') return '';

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);
    if ($mime !== 'video/mp4') return '';

    $newName = 'vid_' . uniqid() . '.mp4';
    if (move_uploaded_file($tmp, 'videos/' . $newName)) return $newName;

    return '';
}

// Catégories
$cats = $bdd->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();

// Article
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: admin_gestion.php');
    exit();
}

$req = $bdd->prepare('SELECT * FROM articles WHERE id = ?');
$req->execute([$id]);
$article = $req->fetch();

if (!$article) {
    header('Location: admin_gestion.php');
    exit();
}

// Police actuelle (une pour l’article)
$font_family = $article['font_family'] ?? 'sans';
$allowedFonts = ['sans', 'serif', 'mono'];
if (!in_array($font_family, $allowedFonts, true)) {
    $font_family = 'sans';
}

// Catégories sélectionnées
$q = $bdd->prepare('SELECT category_id FROM article_categories WHERE article_id = ?');
$q->execute([$id]);
$selectedCats = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
$selectedSet = array_flip($selectedCats);

// Update
if (isset($_POST['update'])) {
    $titre = trim($_POST['titre'] ?? '');
    $desc = trim($_POST['desc'] ?? '');
    $contenu = trim($_POST['contenu'] ?? '');

    // Police (une pour l'article)
    $font_family_post = trim($_POST['font_family'] ?? $font_family);
    if (!in_array($font_family_post, $allowedFonts, true)) {
        $font_family_post = 'sans';
    }

    // catégories (multi)
    $category_ids = $_POST['category_ids'] ?? [];
    $category_ids = array_values(array_unique(array_filter(array_map('intval', (array)$category_ids))));

    // vidéo
    $youtube_input = trim($_POST['video_youtube'] ?? '');
    $remove_video = isset($_POST['remove_video']) ? 1 : 0;

    $has_upload_video = (isset($_FILES['video']) && $_FILES['video']['error'] == 0);
    $has_youtube = ($youtube_input !== '');

    if ($has_upload_video && $has_youtube) {
        header('Location: admin_modifier.php?id=' . $id . '&msg=video_conflict');
        exit();
    }

    $new_youtube_id = '';
    if ($has_youtube) {
        $new_youtube_id = extractYoutubeId($youtube_input);
        if ($new_youtube_id === '') {
            header('Location: admin_modifier.php?id=' . $id . '&msg=youtube_invalid');
            exit();
        }
    }

    $current_video = $article['video'] ?? '';
    $current_youtube = $article['video_youtube'] ?? '';

    $video_to_save = $current_video;
    $youtube_to_save = $current_youtube;

    // Suppression explicite
    if ($remove_video) {
        if (!empty($current_video)) {
            $vidPath = __DIR__ . '/videos/' . $current_video;
            if (is_file($vidPath)) @unlink($vidPath);
        }
        $video_to_save = '';
        $youtube_to_save = '';
    }

    // Remplacement par YouTube
    if ($has_youtube) {
        if (!empty($current_video)) {
            $vidPath = __DIR__ . '/videos/' . $current_video;
            if (is_file($vidPath)) @unlink($vidPath);
        }
        $video_to_save = '';
        $youtube_to_save = $new_youtube_id;
    }

    // Remplacement par MP4
    if ($has_upload_video) {
        $new_video = saveMp4Upload('video');
        if ($new_video === '') {
            header('Location: admin_modifier.php?id=' . $id . '&msg=video_invalid');
            exit();
        }

        if (!empty($current_video)) {
            $vidPath = __DIR__ . '/videos/' . $current_video;
            if (is_file($vidPath)) @unlink($vidPath);
        }

        $video_to_save = $new_video;
        $youtube_to_save = '';
    }

    // Update article (ajout font_family)
    $upd = $bdd->prepare('
        UPDATE articles
        SET titre = ?, description = ?, contenu_complet = ?, video = ?, video_youtube = ?, font_family = ?
        WHERE id = ?
    ');
    $upd->execute([$titre, $desc, $contenu, $video_to_save, $youtube_to_save, $font_family_post, $id]);

    // Update pivot catégories (remplacement complet)
    $bdd->prepare('DELETE FROM article_categories WHERE article_id = ?')->execute([$id]);

    if (!empty($category_ids)) {
        $insPivot = $bdd->prepare('INSERT IGNORE INTO article_categories (article_id, category_id) VALUES (?, ?)');
        foreach ($category_ids as $cid) {
            $insPivot->execute([$id, $cid]);
        }
    }

    header('Location: admin_gestion.php?msg=updated');
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Modifier l'article - Cabinet Carré</title>
    <link rel="shortcut icon" href="./images/favicon.png" type="image/x-icon">
    <meta charset="UTF-8">
    <style>
        body { font-family:'Segoe UI',sans-serif; background:#f4f7f6; padding:50px; }
        .form-card { background:#fff; padding:30px; border-radius:12px; max-width:900px; margin:auto; box-shadow:0 4px 20px rgba(0,0,0,0.05); }
        input, textarea { width:100%; padding:12px; margin:10px 0; border:1px solid #ddd; border-radius:6px; box-sizing:border-box; }
        input[type="file"] { margin:5px 0 10px 0; padding:10px; background:#f9f9f9; width:100%; border:1px dashed #ccc; border-radius:6px; }
        button { background:#1a2b3c; color:#fff; border:none; padding:12px 30px; border-radius:6px; cursor:pointer; font-weight:600; }
        .back { display:block; margin-bottom:20px; text-decoration:none; color:#666; }
        .msg-error { background:#ffe9e9; border:1px solid #ffb3b3; color:#7a1d1d; padding:12px; border-radius:8px; margin-bottom:15px; }
        .box { background:#f6f6f6; padding:12px; border-radius:10px; margin:10px 0 18px 0; }
        .hint { font-size:13px; color:#555; margin-top:6px; }
        .cats-wrap { display:flex; flex-wrap:wrap; gap:10px; margin-top:10px; }
        .cat-chip { display:flex; gap:8px; align-items:center; background:#fff; padding:8px 10px; border-radius:999px; border:1px solid #ddd; }
        label.inline { display:flex; gap:8px; align-items:center; margin-top:10px; }

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
<div class="form-card">
    <a href="admin_gestion.php" class="back">← Retour à la gestion</a>
    <h3>Modifier l'article : <?= htmlspecialchars($article['titre']) ?></h3>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'video_conflict'): ?><div class="msg-error">Choisis soit un MP4, soit un lien YouTube (pas les deux).</div><?php endif; ?>
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'youtube_invalid'): ?><div class="msg-error">Lien YouTube invalide.</div><?php endif; ?>
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'video_invalid'): ?><div class="msg-error">Vidéo invalide (MP4 uniquement).</div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <input type="text" name="titre" value="<?= htmlspecialchars($article['titre']) ?>" required>

        <div style="margin-top:10px;">
            <label style="font-weight:700; display:block;">Catégories (multi)</label>
            <div class="cats-wrap">
                <?php foreach ($cats as $c): ?>
                    <?php $checked = isset($selectedSet[(int)$c['id']]) ? 'checked' : ''; ?>
                    <label class="cat-chip">
                        <input type="checkbox" name="category_ids[]" value="<?= (int)$c['id'] ?>" <?= $checked ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <textarea name="desc" rows="3"><?= htmlspecialchars($article['description']) ?></textarea>

        <div class="cc-editor">
            <div class="cc-toolbar">
                <select class="cc-select" id="ccFont">
                    <option value="sans" <?= $font_family === 'sans' ? 'selected' : '' ?>>Sans Serif</option>
                    <option value="serif" <?= $font_family === 'serif' ? 'selected' : '' ?>>Serif</option>
                    <option value="mono" <?= $font_family === 'mono' ? 'selected' : '' ?>>Monospace</option>
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

            <input type="hidden" name="font_family" id="font_family" value="<?= htmlspecialchars($font_family) ?>">
            <textarea id="contenu" name="contenu" rows="10"><?= htmlspecialchars($article['contenu_complet']) ?></textarea>
        </div>

        <div class="box">
            <strong>Vidéo (optionnel)</strong>
            <div class="hint">Mets la balise <strong>[[VIDEO]]</strong> dans le texte là où tu veux la vidéo. Si la balise n'existe pas, la vidéo s'affiche en haut.</div>

            <div style="margin-top:12px;">
                <label style="font-weight:600; display:block; margin-bottom:6px;">Upload MP4</label>
                <input type="file" name="video" accept="video/mp4">
            </div>

            <div style="margin-top:10px;">
                <label style="font-weight:600; display:block; margin-bottom:6px;">Lien YouTube</label>
                <input type="url" name="video_youtube" placeholder="https://www.youtube.com/watch?v=...">
            </div>

            <label class="inline">
                <input type="checkbox" name="remove_video" value="1">
                Supprimer la vidéo actuelle
            </label>

            <div class="hint">
                <strong>Actuel :</strong>
                <?php if (!empty($article['video'])): ?>
                    MP4 local (videos/<?= htmlspecialchars($article['video']) ?>)
                <?php elseif (!empty($article['video_youtube'])): ?>
                    YouTube (ID: <?= htmlspecialchars($article['video_youtube']) ?>)
                <?php else: ?>
                    Aucun
                <?php endif; ?>
            </div>
        </div>

        <button type="submit" name="update">Enregistrer les modifications</button>
    </form>
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
