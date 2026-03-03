<?php
require 'config.php';

function date_format_custom($date) {
    $mois_fr = [
        "January" => "JANVIER", "February" => "FÉVRIER", "March" => "MARS", "April" => "AVRIL",
        "May" => "MAI", "June" => "JUIN", "July" => "JUILLET", "August" => "AOÛT",
        "September" => "SEPTEMBRE", "October" => "OCTOBRE", "November" => "NOVEMBRE", "December" => "DÉCEMBRE"
    ];
    $timestamp = strtotime($date);
    $mois_anglais = date('F', $timestamp);
    return ($mois_fr[$mois_anglais] ?? $mois_anglais) . date(' j, Y', $timestamp);
}

// catégories (pour filtres)
$cats = $bdd->query('SELECT id, name, slug FROM categories ORDER BY name ASC')->fetchAll();

$catSlug = trim($_GET['cat'] ?? '');
$catId = null;

if ($catSlug !== '') {
    $q = $bdd->prepare('SELECT id FROM categories WHERE slug = ?');
    $q->execute([$catSlug]);
    $row = $q->fetch();
    if ($row) $catId = (int)$row['id'];
}

// requête articles (avec multi-catégories)
if ($catId !== null) {
    $req = $bdd->prepare('
        SELECT a.*,
               GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ", ") AS cats_names
        FROM articles a
        JOIN article_categories acf ON acf.article_id = a.id AND acf.category_id = ?
        LEFT JOIN article_categories ac ON ac.article_id = a.id
        LEFT JOIN categories c ON c.id = ac.category_id
        GROUP BY a.id
        ORDER BY a.date_publication DESC
    ');
    $req->execute([$catId]);
} else {
    $req = $bdd->query('
        SELECT a.*,
               GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ", ") AS cats_names
        FROM articles a
        LEFT JOIN article_categories ac ON ac.article_id = a.id
        LEFT JOIN categories c ON c.id = ac.category_id
        GROUP BY a.id
        ORDER BY a.date_publication DESC
    ');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actualités - Cabinet Carré</title>

    <link rel="stylesheet" href="styles/style2.css">
    <link rel="shortcut icon" href="./images/favicon.png" type="image/x-icon">
    <style>
        body {
            background-color: #fcfcfc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            margin-top: 160px;
            color: #2c3e50;
        }

        .filters {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 30px 20px 30px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-pill {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 999px;
            text-decoration: none;
            color: #1a2b3c;
            font-size: 13px;
            background: #fff;
        }

        .filter-pill.active {
            border-color: #1f7ea6;
            color: #1f7ea6;
            font-weight: 600;
        }

        .news-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            column-gap: 30px;
            row-gap: 50px;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 30px 100px 30px;
        }

        .card {
            background: white;
            display: flex;
            flex-direction: column;
            position: relative;
            padding: 40px;
        }

        .cat {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #7f8c8d;
            margin-bottom: 20px;
            font-weight: 400;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .badge-video {
            background: #e74c3c;
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            letter-spacing: .6px;
        }

        .card h2 {
            font-size: 24px;
            line-height: 1.2;
            margin: 0 0 25px 0;
            font-weight: 700;
            color: #1a2b3c;
        }

        .img-container {
            width: 100%;
            height: 240px;
            overflow: hidden;
            margin-bottom: 30px;
        }

        .img-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .desc {
            font-size: 15px;
            line-height: 1.6;
            color: #515a6e;
            margin-bottom: 35px;
            flex-grow: 1;
        }

        .footer-card {
            font-size: 11px;
            color: #95a5a6;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .footer-card span.brand {
            color: #34495e;
            font-weight: 600;
        }

        .full-link {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            z-index: 2;
        }

        @media (max-width: 420px) {
            .news-grid { grid-template-columns: 1fr; padding: 0 18px 80px 18px; }
            .filters { padding: 0 18px 18px 18px; }
            .card { padding: 28px; }
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

            <button class="hamburger" aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="mobileMenu">
                <span></span><span></span><span></span>
            </button>

            <ul class="nav-links" id="mobileMenu">
                <li><a href="index.html">Accueil</a></li>
                <li><a href="cabinet.html">Le cabinet</a></li>
                <li><a href="competences.html">Nos compétences</a></li>
                <li><a href="contact.php">Contact</a></li>
                <li><a href="news.php" class="active">Newsletter</a></li>
            </ul>
        </div>

        <div class="nav-overlay" hidden></div>
    </nav>
</header>

<?php if (!empty($cats)): ?>
    <div class="filters">
        <a class="filter-pill <?= ($catSlug === '') ? 'active' : '' ?>" href="news.php">Toutes</a>
        <?php foreach ($cats as $c): ?>
            <a class="filter-pill <?= ($catSlug === $c['slug']) ? 'active' : '' ?>" href="news.php?cat=<?= htmlspecialchars($c['slug']) ?>">
                <?= htmlspecialchars($c['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<main class="news-grid">
    <?php while ($art = $req->fetch()): ?>
        <?php
        $catsNames = trim((string)($art['cats_names'] ?? ''));
        $label = $catsNames !== '' ? $catsNames : 'DERNIÈRES ACTUALITÉS';
        $hasVideo = (!empty($art['video']) || !empty($art['video_youtube']));
        ?>
        <article class="card">
            <div class="cat">
                <?= htmlspecialchars($label) ?>
                <?php if ($hasVideo): ?><span class="badge-video">VIDÉO</span><?php endif; ?>
            </div>

            <h2><?= htmlspecialchars($art['titre']) ?></h2>

            <?php if (!empty($art['image_url'])): ?>
                <div class="img-container">
                    <img src="images/<?= htmlspecialchars($art['image_url']) ?>" alt="Illustration article">
                </div>
            <?php endif; ?>

            <div class="desc"><?= htmlspecialchars($art['description']) ?></div>

            <div class="footer-card">
                <span class="brand">CABINET CARRÉ</span> / <?= date_format_custom($art['date_publication']) ?>
            </div>

            <a href="article.php?id=<?= (int)$art['id'] ?>" class="full-link"></a>
        </article>
    <?php endwhile; ?>
</main>

<footer class="footer">
    <div class="footer-container">
        <div class="footer-col">
            <h4>Cabinet d’expert comptable</h4>
            <p><a href="politique.html">Politique de confidentialité</a></p>
            <p><a href="mention-legale.html">Mentions légales</a></p>
        </div>

        <div class="footer-col">
            <h4>Contact</h4>
            <p>Email : <a href="mailto:contact@carreexpertise.fr">contact@carreexpertise.fr</a></p>
            <p>Téléphone : 01 75 17 76 37</p>
            <p>Adresse : 13 Rue Jean-Pierre Plicque, 77124 Villenoy</p>
        </div>
    </div>

    <div class="footer-bottom">
        <p>© 2026 Cabinet Carré, tous droits réservés</p>
    </div>
</footer>

<script src="./script.js"></script>
</body>
</html>
