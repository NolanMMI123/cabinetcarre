<?php
session_start();
require 'config.php';

if(isset($_POST['connexion'])) {
    $user = htmlspecialchars($_POST['username']);
    $pass = $_POST['password'];

    $req = $bdd->prepare('SELECT * FROM admins WHERE username = ?');
    $req->execute([$user]);
    $admin = $req->fetch();

    if($admin && $pass == $admin['password']) {
        $_SESSION['admin_id'] = $admin['id'];
        header('Location: admin_gestion.php');
        exit();
    } else {
        $erreur = "Identifiants incorrects !";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion - Cabinet Carré</title>
  <link rel="shortcut icon" href="./images/favicon.png" type="image/x-icon">
    <style>
        body { background: #f4f7f6; font-family: 'Segoe UI', sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-box { width: 350px; padding: 40px; background: white; border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); text-align: center; }
        
        /* Style du logo */
        .logo-admin { width: 180px; margin-bottom: 20px; }
        
        h2 { color: #1a2b3c; margin-bottom: 30px; font-size: 22px; }
        input { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        button { background: #1a2b3c; color: white; border: none; padding: 12px; cursor: pointer; width: 100%; border-radius: 5px; font-size: 16px; margin-top: 10px; transition: 0.3s; }
        button:hover { background: #1f7ea6; }
        .error { color: red; font-size: 14px; margin-bottom: 15px; }
        .back-link { display: block; margin-top: 20px; font-size: 12px; color: #888; text-decoration: none; }
    </style>
</head>
<body>
    <div class="login-box">
        <img src="images/logo.png" alt="Cabinet Carré" class="logo-admin">
        
        <h2>Administration</h2>
        
        <?php if(isset($erreur)) echo "<p class='error'>$erreur</p>"; ?>
        
        <form method="POST">
            <input type="text" name="username" placeholder="Nom d'utilisateur" required>
            <input type="password" name="password" placeholder="Mot de passe" required>
            <button type="submit" name="connexion">Se connecter</button>
        </form>
        
        <a href="news.php" class="back-link">← Retour au site</a>
    </div>
</body>
</html>