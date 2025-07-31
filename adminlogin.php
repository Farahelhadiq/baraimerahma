<?php
session_start();
require 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';

    if (!empty($email) && !empty($mot_de_passe)) {
        try {
            $pdo = connectDB();

           
            $stmt = $pdo->prepare("SELECT * FROM directeur WHERE email = ?");
            $stmt->execute([$email]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            
            $hashed = hash('sha256', $mot_de_passe);
            if ($admin && $hashed === $admin['mot_de_passe']) {
                $_SESSION['admin_id'] = $admin['id_directeur'];
                $_SESSION['admin_nom'] = $admin['nom'];
                $_SESSION['admin_prenom'] = $admin['prenom'];
                header('Location: admin_dashboard.php');
                exit;
            } else {
                $error = "❌ Email ou mot de passe incorrect.";
            }
        } catch (PDOException $e) {
            $error = "❗ Erreur de base de données : " . $e->getMessage();
        }
    } else {
        $error = "❗ Veuillez remplir tous les champs.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Administrateur</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="adminlogin.css">
</head>
<body>
    <div class="main">
        <div class="login-container">
            <form method="POST" action="">
                <h2 class="title">Connexion </h2>
                
                <div class="form-group">
                    <input type="email" name="email" class="form-input" placeholder="Email" required>
                </div>
                
                <div class="form-group">
                    <div class="password-field">
                        <input type="password" name="mot_de_passe" id="password" class="form-input" placeholder="Mot de passe" required>
                        <button type="button" class="password-toggle" onclick="togglePassword()"><i class="fa-solid fa-eye-slash"></i></button>
                    </div>
                </div>
                
                <button type="submit" class="login-button">Se connecter</button>
                
                <?php if (!empty($error)): ?>
                    <p class="message"><?= htmlspecialchars($error) ?></p>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleButton = document.querySelector('.password-toggle i');
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleButton.classList.remove('fa-eye-slash');
                toggleButton.classList.add('fa-eye');
            } else {
                passwordField.type = 'password';
                toggleButton.classList.remove('fa-eye');
                toggleButton.classList.add('fa-eye-slash');
            }
        }
    </script>
</body>
</html>