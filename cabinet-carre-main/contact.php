<?php
// Traitement du formulaire (POST) + message de retour (GET)
$feedback = null;
$feedback_type = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Nettoyage des données
    $name    = htmlspecialchars(trim($_POST["name"] ?? ""));
    $email   = filter_var(trim($_POST["email"] ?? ""), FILTER_SANITIZE_EMAIL);
    $message = htmlspecialchars(trim($_POST["message"] ?? ""));

    // Vérifications
    if ($name === "" || $email === "" || $message === "") {
        $feedback = "Tous les champs sont obligatoires.";
        $feedback_type = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $feedback = "Adresse e-mail invalide.";
        $feedback_type = "error";
    } else {
        // Paramètres du mail
        $to      = "contact@carreexpertise.fr";
        $subject = "Nouveau message depuis le formulaire de contact";
        $body    = "Nom : $name\nEmail : $email\n\nMessage :\n$message";
        $headers = "From: $email\r\n";
        $headers .= "Reply-To: $email\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        if (@mail($to, $subject, $body, $headers)) {
            // Redirect pour éviter le renvoi au refresh
            header("Location: contact.php?sent=1");
            exit;
        } else {
            $feedback = "Erreur lors de l'envoi du message. Réessaie plus tard ou contacte-nous directement par e-mail.";
            $feedback_type = "error";
        }
    }
} else {
    // Message de succès après redirection
    if (isset($_GET["sent"]) && $_GET["sent"] === "1") {
        $feedback = "Message envoyé avec succès !";
        $feedback_type = "success";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contact - Cabinet Carré</title>
  <link rel="stylesheet" href="./styles/style2.css">
  <link rel="shortcut icon" href="./images/favicon.png" type="image/x-icon">
</head>
<body>

<!-- ===== NAVBAR ===== -->
<nav class="navbar">
  <div class="navbar-container">
    <div class="logo">
      <img src="./images/logo.png" alt="Cabinet Carré">
    </div>

  <!-- Bouton hamburger -->
  <button class="hamburger" aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="mobileMenu">
    <span></span><span></span><span></span>
  </button>

  <!-- Donne un id pour aria-controls -->
  <ul class="nav-links" id="mobileMenu">
    <li><a href="index.html">Accueil</a></li>
    <li><a href="cabinet.html">Le cabinet</a></li>
    <li><a href="competences.html">Nos compétences</a></li>
    <li><a href="contact.php" class="active">Contact</a></li>
    <li><a href="news.php">Newsletter</a></li>
  </ul>
  </div>
  <div class="nav-overlay"></div>
</nav>


<section class="contact" id="contact">
  <!-- Titre centré globalement -->
  <h2 class="contact-title">Contact</h2>

  <?php if ($feedback): ?>
    <div class="contact-feedback <?php echo $feedback_type === "success" ? "is-success" : "is-error"; ?>">
      <?php echo htmlspecialchars($feedback, ENT_QUOTES, "UTF-8"); ?>
    </div>
  <?php endif; ?>

  <div class="contact-inner">
    <div class="contact-left">
      <div class="contact-watermark"></div>
      <div class="contact-info">
        <h3>Cabinet Carré</h3>
        <p><strong>E-mail :</strong> <a href="mailto:contact@carreexpertise.fr">contact@carreexpertise.fr</a></p>
        <p><strong>Tél :</strong> <a href="tel:+33175177637">01 75 17 76 37</a></p>
        <p>13, rue Jean-Pierre Plicque <br /> 77120 Villenoy, France</p>
      </div>
    </div>

    <!-- Colonne droite : formulaire + carte -->
    <div class="contact-right">
        <form class="contact-form" action="contact.php" method="post">
          <input type="text" name="name" placeholder="Nom" required>
          <input type="email" name="email" placeholder="E-mail" required>
          <textarea name="message" rows="7" placeholder="Rédigez votre message ici..." required></textarea>
          <button type="submit">Envoyer</button>
        </form>


      <div class="contact-map">
        <!-- remplace la src par ta clé/URL si besoin -->
        <iframe
          loading="lazy"
          allowfullscreen
          referrerpolicy="no-referrer-when-downgrade"
          src="https://www.google.com/maps?q=13%20rue%20Jean%20Pierre%20Plicque%2C%2077120%20Villenoy&output=embed">
        </iframe>
      </div>
    </div>
  </div>
</section>

  <!-- Footer -->
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
