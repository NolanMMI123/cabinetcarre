<?php
require 'config.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: news.php');
    exit();
}

$req = $bdd->prepare('SELECT * FROM articles WHERE id = ?');
$req->execute([$id]);
$article = $req->fetch();

if (!$article) {
    header('Location: news.php');
    exit();
}

// Police de l'article (une seule pour tout le contenu)
$font_family = $article['font_family'] ?? 'sans';
$allowedFonts = ['sans', 'serif', 'mono'];
if (!in_array($font_family, $allowedFonts, true)) {
    $font_family = 'sans';
}

// Catégories de l’article
$q = $bdd->prepare('
    SELECT c.name, c.slug
    FROM categories c
    JOIN article_categories ac ON ac.category_id = c.id
    WHERE ac.article_id = ?
    ORDER BY c.name ASC
');
$q->execute([$id]);
$articleCats = $q->fetchAll();

function date_fr_full($date)
{
    $mois_fr = [
        "January" => "JANVIER", "February" => "FÉVRIER", "March" => "MARS", "April" => "AVRIL",
        "May" => "MAI", "June" => "JUIN", "July" => "JUILLET", "August" => "AOÛT",
        "September" => "SEPTEMBRE", "October" => "OCTOBRE", "November" => "NOVEMBRE", "December" => "DÉCEMBRE"
    ];
    $timestamp = strtotime($date);
    $mois_anglais = date('F', $timestamp);
    return ($mois_fr[$mois_anglais] ?? $mois_anglais) . date(' j, Y', $timestamp);
}

/**
 * Transforme les URLs http/https et www. (déjà échappées) en liens cliquables.
 * Important : on l'appelle APRÈS htmlspecialchars pour éviter tout HTML user.
 */
function linkify($text)
{
    // 1) Liens avec http/https
    $patternHttp = '~(https?://[^\s<]+)~i';

    $text = preg_replace_callback($patternHttp, function ($m) {
        $url = $m[1];

        $trimmed = rtrim($url, '.,;:!?)"]}');
        $tail = substr($url, strlen($trimmed));

        $safeUrl = htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8');

        return '<a href="' . $safeUrl . '" target="_blank" rel="noopener noreferrer">'
            . $safeUrl .
            '</a>' . $tail;
    }, $text);

    // 2) Liens commençant par www.
    $patternWww = '~\b(www\.[^\s<]+)~i';

    $text = preg_replace_callback($patternWww, function ($m) {
        $url = $m[1];

        $trimmed = rtrim($url, '.,;:!?)"]}');
        $tail = substr($url, strlen($trimmed));

        $safeUrl = htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8');

        return '<a href="https://' . $safeUrl . '" target="_blank" rel="noopener noreferrer">'
            . $safeUrl .
            '</a>' . $tail;
    }, $text);

    return $text;
}

/**
 * Convertit un balisage simple (safe) en HTML contrôlé :
 * - "# Titre" => h1
 * - "## Titre" => h2
 * - "**gras**" => strong
 * - "*italique*" => em
 *
 * IMPORTANT : on attend une chaîne déjà échappée (htmlspecialchars).
 */
function format_basic_markup($escapedText)
{
    // Titres (début de ligne)
    $escapedText = preg_replace('/^##\s*(.+)$/m', '<h2 class="md-h2">$1</h2>', $escapedText);
    $escapedText = preg_replace('/^#\s*(.+)$/m',  '<h1 class="md-h1">$1</h1>', $escapedText);

    // Gras **texte**
    $escapedText = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escapedText);

    // Italique *texte* (évite de casser le gras)
    $escapedText = preg_replace('/(?<!\*)\*(?!\s)(.+?)(?<!\s)\*(?!\*)/s', '<em>$1</em>', $escapedText);

    return $escapedText;
}

function render_video_html($article)
{
    $video_local = $article['video'] ?? '';
    $youtube_id = $article['video_youtube'] ?? '';

    if (!empty($youtube_id)) {
        $id = htmlspecialchars($youtube_id, ENT_QUOTES, 'UTF-8');
        return '<div class="video-container video-container--embed">
            <iframe src="https://www.youtube-nocookie.com/embed/' . $id . '"
                title="YouTube video" frameborder="0"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen></iframe>
        </div>';
    }

    if (!empty($video_local)) {
        $poster = !empty($article['image_url']) ? 'images/' . htmlspecialchars($article['image_url'], ENT_QUOTES, 'UTF-8') : '';
        $src = 'videos/' . htmlspecialchars($video_local, ENT_QUOTES, 'UTF-8');
        $posterAttr = $poster ? ' poster="' . $poster . '"' : '';
        return '<div class="video-container">
            <video controls preload="metadata"' . $posterAttr . '>
                <source src="' . $src . '" type="video/mp4">
                Votre navigateur ne supporte pas la lecture de vidéos.
            </video>
        </div>';
    }

    return '';
}

$video_html = render_video_html($article);

// Contenu + balise [[VIDEO]]
$raw_content = $article['contenu_complet'] ?? '';

// 1) on échappe tout (sécurité)
// 2) on applique le formatage simple (titres/gras/italique)
// 3) on linkify les URL (http/https + www.)
// 4) on garde les retours à la ligne
$safe = htmlspecialchars($raw_content, ENT_QUOTES, 'UTF-8');
$safe = format_basic_markup($safe);
$safe = linkify($safe);
$content_html = nl2br($safe);

if ($video_html !== '') {
    if (strpos($raw_content, '[[VIDEO]]') !== false) {
        $content_html = str_replace('[[VIDEO]]', $video_html, $content_html);
    } else {
        $content_html = $video_html . $content_html;
    }
}

// URL courante pour partage
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$currentUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$safeUrl = htmlspecialchars($currentUrl, ENT_QUOTES, 'UTF-8');
$encodedUrl = urlencode($currentUrl);
$encodedTitle = urlencode($article['titre'] ?? 'Article');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($article['titre']) ?> - Cabinet Carré</title>
    <link rel="stylesheet" href="styles/style2.css">
    <link rel="shortcut icon" href="./images/favicon.png" type="image/x-icon">
    <style>
        body { background:#fff; font-family:'Helvetica Neue', Helvetica, Arial, sans-serif; margin:0; padding-top:140px; color:#1a2b3c; }
        .container-article { max-width:900px; margin:0 auto; padding:0 20px 100px 20px; }
        .main-image-container { width:100%; margin-bottom:50px; text-align:center; }
        .main-image-container img { max-width:100%; height:auto; }

        .pills { display:flex; flex-wrap:wrap; gap:10px; margin:0 0 14px 0; }
        .pill { display:inline-flex; align-items:center; padding:6px 10px; border:1px solid #ddd; border-radius:999px; font-size:12px; color:#1a2b3c; text-decoration:none; background:#fff; }

        .video-container { width:100%; margin-bottom:40px; background:#000; border-radius:8px; overflow:hidden; line-height:0; }
        .video-container video { width:100%; max-height:500px; display:block; }
        .video-container--embed { background:transparent; }
        .video-container iframe { width:100%; aspect-ratio:16/9; height:auto; display:block; border:0; }

        .article-header { margin-bottom:40px; }
        .article-title { font-size:32px; font-weight:700; line-height:1.2; margin-bottom:15px; color:#1a2b3c; }
        .article-date { font-size:11px; text-transform:uppercase; letter-spacing:1px; color:#999; margin-bottom:30px; display:block; }

        .article-content { font-size:17px; line-height:1.8; color:#444; text-align:justify; }

        /* Polices (1 police par article) */
        .article-content.ff-sans { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; }
        .article-content.ff-serif { font-family: "EB Garamond", Georgia, "Times New Roman", serif; }
        .article-content.ff-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace; }

        /* Optionnel : titre suit aussi la police */
        .article-title.ff-sans { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; }
        .article-title.ff-serif { font-family: "EB Garamond", Georgia, "Times New Roman", serif; }
        .article-title.ff-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace; }

        /* Titres générés par le balisage */
        .md-h1{ font-size:28px; margin:26px 0 12px; color:#1a2b3c; }
        .md-h2{ font-size:22px; margin:22px 0 10px; color:#1a2b3c; }

        .article-content a { color:#1f7ea6; text-decoration:underline; }
        .article-content a:hover { text-decoration:none; }

        /* CTA */
        .article-cta {
            margin-top:60px;
            display:flex;
            gap:20px;
            flex-wrap:wrap;
            align-items:center;
        }

        .cta-btn {
            padding:12px 22px;
            border-radius:6px;
            font-weight:600;
            font-size:14px;
            cursor:pointer;
            text-decoration:none;
            border:none;
            transition:0.3s ease;
            display:inline-flex;
            align-items:center;
            justify-content:center;
        }

        .cta-btn.primary { background:#1f7ea6; color:white; }
        .cta-btn.primary:hover { background:#166d90; }

        .cta-btn.secondary { background:#f0f2f5; color:#1a2b3c; }
        .cta-btn.secondary:hover { background:#e2e6ea; }

        .share-wrapper { position:relative; }

        .share-menu {
            position:absolute;
            top:45px;
            left:0;
            background:white;
            border:1px solid #ddd;
            border-radius:6px;
            display:none;
            flex-direction:column;
            min-width:220px;
            box-shadow:0 4px 12px rgba(0,0,0,0.08);
            z-index:100;
        }

        .share-menu a,
        .share-menu button {
            padding:10px 14px;
            text-align:left;
            background:none;
            border:none;
            font-size:13px;
            cursor:pointer;
            text-decoration:none;
            color:#1a2b3c;
        }

        .share-menu a:hover,
        .share-menu button:hover { background:#f6f6f6; }

        .btn-back { display:inline-block; margin-top:50px; color:#1a2b3c; text-decoration:none; font-size:14px; font-weight:600; }
        .btn-back:hover { text-decoration:underline; }

        @media (max-width:768px){
            body { padding-top:100px; }
            .article-title { font-size:26px; }
            .article-content { text-align:left; }
        }
    </style>
</head>
<body>

<header>
    <nav class="navbar">
        <div class="navbar-container">
            <div class="logo"><img src="./images/logo.png" alt="Cabinet Carré" /></div>
            <div class="location">
                <a href="./contact.php"><img src="./images/localisation.png" alt="Localisation"><span>Villenoy</span></a>
            </div>
            <ul class="nav-links">
                <li><a href="index.html">Accueil</a></li>
                <li><a href="cabinet.html">Le cabinet</a></li>
                <li><a href="competences.html">Nos compétences</a></li>
                <li><a href="contact.php">Contact</a></li>
                <li><a href="news.php" class="active">Newsletter</a></li>
            </ul>
        </div>
    </nav>
</header>

<main class="container-article">

    <?php if (!empty($article['image_url'])): ?>
        <div class="main-image-container">
            <img src="images/<?= htmlspecialchars($article['image_url']) ?>" alt="Illustration">
        </div>
    <?php endif; ?>

    <div class="article-header">
        <?php if (!empty($articleCats)): ?>
            <div class="pills">
                <?php foreach ($articleCats as $c): ?>
                    <a class="pill" href="news.php?cat=<?= htmlspecialchars($c['slug']) ?>"><?= htmlspecialchars($c['name']) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <h1 class="article-title ff-<?= htmlspecialchars($font_family, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($article['titre']) ?></h1>
        <span class="article-date"><?= date_fr_full($article['date_publication']) ?></span>
    </div>

    <div class="article-content ff-<?= htmlspecialchars($font_family, ENT_QUOTES, 'UTF-8') ?>"><?= $content_html ?></div>

    <div class="article-cta">
        <a href="contact.php" class="cta-btn primary">Nous contacter</a>

        <div class="share-wrapper">
            <button class="cta-btn secondary" type="button" onclick="toggleShare()">Partager l’article</button>

            <div class="share-menu" id="shareMenu">
                <a href="mailto:?subject=<?= $encodedTitle ?>&body=<?= $encodedUrl ?>">
                    Partager par email
                </a>
                <a href="https://wa.me/?text=<?= $encodedTitle ?>%20<?= $encodedUrl ?>" target="_blank" rel="noopener noreferrer">
                    WhatsApp
                </a>
                <button type="button" onclick="copyLink('<?= $safeUrl ?>')">
                    Copier le lien
                </button>
            </div>
        </div>
    </div>

    <a href="news.php" class="btn-back">← Retour aux actualités</a>
</main>

<script>
function toggleShare() {
    const menu = document.getElementById("shareMenu");
    menu.style.display = (menu.style.display === "flex") ? "none" : "flex";
}

function copyLink(url) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(function () {
            alert("Lien copié !");
        }).catch(function () {
            fallbackCopy(url);
        });
    } else {
        fallbackCopy(url);
    }
}

function fallbackCopy(url) {
    const tmp = document.createElement("textarea");
    tmp.value = url;
    document.body.appendChild(tmp);
    tmp.select();
    document.execCommand("copy");
    document.body.removeChild(tmp);
    alert("Lien copié !");
}

// Ferme le menu si on clique ailleurs
document.addEventListener("click", function (e) {
    const menu = document.getElementById("shareMenu");
    const wrapper = document.querySelector(".share-wrapper");
    if (!wrapper.contains(e.target)) {
        menu.style.display = "none";
    }
});
</script>

</body>
</html>
