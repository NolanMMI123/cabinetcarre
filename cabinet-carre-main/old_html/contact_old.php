<?php
// Configuration
$destinataire = "contact@carreexpertise.fr"; // Mets ici l'adresse qui doit recevoir les messages
$subject = "Nouveau message depuis le formulaire de contact du site";

// Initialisation du message d'état
$message_envoye = "";
$message_erreur = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sécurisation des champs
    $prenom  = htmlspecialchars(trim($_POST['prenom'] ?? ''));
    $nom     = htmlspecialchars(trim($_POST['nom'] ?? ''));
    $email   = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $message = htmlspecialchars(trim($_POST['message'] ?? ''));

    // Vérification des champs obligatoires
    if (!$prenom || !$nom || !$email || !$message) {
        $message_erreur = "Merci de remplir tous les champs correctement.";
    } else {
        // Construction du mail
        $mail_content = "Message envoyé depuis le site Cabinet Carré :\n\n";
        $mail_content .= "Prénom : $prenom\n";
        $mail_content .= "Nom : $nom\n";
        $mail_content .= "Email : $email\n";
        $mail_content .= "Message :\n$message\n";
        $headers = "From: $prenom $nom <$email>\r\n";
        $headers .= "Reply-To: $email\r\n";

        // Envoi du mail
        if (mail($destinataire, $subject, $mail_content, $headers)) {
            $message_envoye = "Merci pour votre message, nous vous répondrons rapidement !";
        } else {
            $message_erreur = "Une erreur technique est survenue. Merci de réessayer plus tard.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Contactez-nous — Cabinet Carré</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <!-- NAVBAR -->
  <nav class="navbar">
    <div class="navbar-container">
      <div class="logo">
        <img src="logo.png" alt="Logo Cabinet Carré">
      </div>
      <ul class="nav-links">
        <li><a href="index.html">Accueil</a></li>
        <li><a href="cabinet.html">Le cabinet</a></li>
        <li><a href="equipe.html">Notre équipe</a></li>
        <li><a href="competences.html">Nos compétences</a></li>
        <li><a href="contact.php" class="active">Contact</a></li>
        <li><a href="newsletter.html">Newsletter</a></li>
      </ul>
    </div>
  </nav>

  <main class="contact-page">
    <h1>Contactez-nous</h1>
    <div class="contact-coords">
  <div class="coord-gauche">
    Au : <strong>01 75 17 76 37</strong>
  </div>
  <div class="contact-bar"></div>
  <div class="coord-droite">
    Accueil téléphonique du lundi au vendredi :
    <strong>9h –13h / 14h-18h</strong>
  </div>
</div>
    <h2>Ou envoyez nous un message</h2>
    <form class="contact-form" method="POST" action="">
      <div class="input-row">
        <input type="text" name="prenom" placeholder="Prénom" required>
        <input type="text" name="nom" placeholder="Nom" required>
      </div>
      <input type="email" name="email" placeholder="Email" required>
      <textarea name="message" rows="6" placeholder="Message" required></textarea>
      <button type="submit">Envoyer</button>
    </form>

    <?php if ($message_envoye): ?>
  <div class="contact-success"><?= $message_envoye ?></div>
<?php endif; ?>
<?php if ($message_erreur): ?>
  <div class="contact-error"><?= $message_erreur ?></div>
<?php endif; ?>

  </main>

<!-- Footer -->
  <footer class="footer">
    <div class="footer-container">
      <div class="footer-col">
        <h4>Cabinet d’expert comptable</h4>
        <p><a href="#">Politique de confidentialité</a></p>
        <p><a href="#">Mention légale</a></p>
      </div>

      <div class="footer-col">
        <h4>Contact</h4>
        <p>Email : <a href="mailto:contact@carreexpertise.fr">contact@carreexpertise.fr</a></p>
        <p>Téléphone : 01 75 17 76 37</p>
        <p>Adresse : 13 Rue Jean-Pierre Plicque, 77124 Villenoy</p>
      </div>

      <div class="footer-col">
        <h4><a href="#">Réseaux sociaux</a></h4>
      </div>
    </div>

    <div class="footer-bottom">
      <p>© 2025 Cabinet Carré, tous droits réservés</p>
    </div>
  </footer>

</body>
</html>
