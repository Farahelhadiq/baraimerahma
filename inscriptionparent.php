<?php
require'config.php';
$errors = [];
$success = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nom = filter_input(INPUT_POST, 'nom', FILTER_SANITIZE_STRING);
    $prenom = filter_input(INPUT_POST, 'prenom', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $mot_de_passe = $_POST['mot_de_passe']; 
    $telephone = filter_input(INPUT_POST, 'telephone', FILTER_SANITIZE_STRING);
    $nom_enfant = filter_input(INPUT_POST, 'nom_enfant', FILTER_SANITIZE_STRING);
    $prenom_enfant = filter_input(INPUT_POST, 'prenom_enfant', FILTER_SANITIZE_STRING);
    $date_naissance_enfant = $_POST['date_naissance_enfant'];
    if (empty($nom)) $errors[] = "Le nom du parent est requis.";
    if (empty($prenom)) $errors[] = "Le prénom du parent est requis.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Adresse email invalide.";
    if (strlen($mot_de_passe) < 6) $errors[] = "Le mot de passe doit contenir au moins 6 caractères.";
    if (empty($telephone)) $errors[] = "Le numéro de téléphone est requis.";
    if (empty($nom_enfant)) $errors[] = "Le nom de l'enfant est requis.";
    if (empty($prenom_enfant)) $errors[] = "Le prénom de l'enfant est requis.";
    if (empty($date_naissance_enfant)) $errors[] = "La date de naissance de l'enfant est requise.";
    $pdo = connectDB();
    $stmt = $pdo->prepare("SELECT id_parent FROM parent WHERE email = :email");
    $stmt->execute(['email' => $email]);
    if ($stmt->rowCount() > 0) {
        $errors[] = "Cet email est déjà enregistré.";
    }
    $stmt = $pdo->prepare("SELECT id_enfant, id_parent FROM enfants WHERE nom = :nom AND prenom = :prenom AND date_naissance = :date_naissance");
    $stmt->execute([
        'nom' => $nom_enfant,
        'prenom' => $prenom_enfant,
        'date_naissance' => $date_naissance_enfant
    ]);
    if ($stmt->rowCount() === 0) {
        $errors[] = "Aucun enfant avec ce nom, prénom et date de naissance n'est enregistré.";
    } else {
        $child = $stmt->fetch(PDO::FETCH_ASSOC);
        $id_enfant = $child['id_enfant'];
        if ($child['id_parent'] !== null) {
            $errors[] = "Cet enfant est déjà associé à un parent.";
        }
    }
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();//bax ytnafad kolxi wla matnafad hta haja
            $hashed_password = password_hash($mot_de_passe, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("INSERT INTO parent (nom, prenom, email, mot_de_passe, telephone) VALUES (:nom, :prenom, :email, :mot_de_passe, :telephone)");
            $stmt->execute([
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email,
                'mot_de_passe' => $hashed_password,
                'telephone' => $telephone
            ]);
            $id_parent = $pdo->lastInsertId();//bax njibo id parent

            $stmt = $pdo->prepare("UPDATE enfants SET id_parent = :id_parent WHERE id_enfant = :id_enfant");
            $stmt->execute([
                'id_parent' => $id_parent,
                'id_enfant' => $id_enfant
            ]);

            $pdo->commit();//taekid jami3 l3amaliyat 
            $success = "Inscription réussie ! Vous êtes maintenant associé à votre enfant.";
        } catch (Exception $e) {
            $pdo->rollback();
            $errors[] = "Une erreur s'est produite lors de l'inscription : " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription Parent - Baraime El Rahma</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="inscriptionparent.css">
</head>
<body>
    <nav>
        <div class="logo">
            <img src="logo.png" alt="Logo" />
        </div>
        <div class="hamburger">
            <span></span>
            <span></span>
            <span></span>
        </div>
        <ul class="nav-links">
            <li><a href="index.html" class="active">Accueil</a></li>
            <li><a href="contact.html">Contact</a></li>
            <li><a href="Enfants.html">Enfants</a></li>
            <li><a href="A propos.html">À propos</a></li>
            <li class="dropdown">
                <button class="dropbtn">Connexion ▾</button>
                <div class="dropdown-content">
                    <a href="loginparent.php">Espace Parents</a>
                    <a href="espace_professeur.php">Espace Personnel</a>
                </div>
            </li>
        </ul>
    </nav>

    <main class="main">
        <div class="registration-container">
            <h1 class="title">Créer un compte parent</h1>
            <p class="subtitle">Inscrivez-vous pour accéder aux services de Baraime El Rahma</p>

            <?php if (!empty($errors)): ?>
                <div class="message error-message" id="errorMessage">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="message success-message" id="successMessage">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            <form method="POST" class="form" id="registrationForm" action="inscriptionparent.php">
                <div class="section-header">Informations du parent</div>
                <div class="form-group">
                    <input type="text" name="nom" class="form-input" placeholder="Nom du parent" required>
                </div>
                <div class="form-group">
                    <input type="text" name="prenom" class="form-input" placeholder="Prénom du parent" required>
                </div>
                <div class="form-group">
                    <input type="email" name="email" class="form-input" placeholder="Adresse email" required>
                </div>
                <div class="form-group password-field">
                    <input type="password" name="mot_de_passe" class="form-input" id="password" placeholder="Mot de passe (min. 6 caractères)" required>
                    <button type="button" class="password-toggle" onclick="togglePassword()"><i class="fa-solid fa-eye-slash"></i></button>
                </div>
                <div class="form-group">
                    <input type="tel" name="telephone" class="form-input" placeholder="Numéro de téléphone" required>
                </div>
                <div class="section-header">Informations de l'enfant</div>
                <div class="form-group">
                    <input type="text" name="nom_enfant" class="form-input" placeholder="Nom de l'enfant" required>
                </div>
                <div class="form-group">
                    <input type="text" name="prenom_enfant" class="form-input" placeholder="Prénom de l'enfant" required>
                </div>
                <div class="form-group">
                    <input type="date" name="date_naissance_enfant" class="form-input" required>
                </div>
                <button type="submit" class="registration-button">Créer mon compte</button>
            </form>
            <p style="margin-top: 1.5rem; color: #5f6368; font-size: 0.9rem;">
                Vous avez déjà un compte ? 
                <a href="loginparent.php" style="color: #4285f4; text-decoration: none; font-weight: 500;">Se connecter</a>
            </p>
        </div>
    </main>

    <footer class="footer">
        <div class="footer-container">
            <div class="footer-section footer-brand">
                <div class="footer-logo">
                    <h3>Baraime El Rahma</h3>
                </div>
                <p class="footer-description">
                    Un environnement sûr, nurturant et éducatif pour les enfants. Nous nous engageons à favoriser le développement et l'apprentissage des enfants.
                </p>
            </div>
            
            <div class="footer-section">
                <h3>Contact</h3>
                <div class="footer-contact">
                    <div class="contact-itemf">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4A90E2" stroke-width="2">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                            <circle cx="12" cy="10" r="3"/>
                        </svg>
                        <span>123 Rue de l'Exemple, Ville, Pays</span>
                    </div>
                    
                    <div class="contact-itemf">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4A90E2" stroke-width="2">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                        </svg>
                        <span>+212 5XX-XXXXXX</span>
                    </div>
                    
                    <div class="contact-itemf">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4A90E2" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                        <span>contact@baraime-elrahma.com</span>
                    </div>
                    
                    <div class="contact-itemf">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4A90E2" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12,6 12,12 16,14"/>
                        </svg>
                        <div>
                            <div>Lun-Ven: 9h00-17h00</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="footer-section">
                <h3>Liens rapides</h3>
                <ul class="footer-links">
                    <li><a href="index.html">Accueil</a></li>
                    <li><a href="#">À propos</a></li>
                    <li><a href="contact.html">Contact</a></li>
                    <li><a href="#">Espace Parents</a></li>
                    <li><a href="#">Espace Personnel</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h3>Restez informés</h3>
                <div class="social-links">
                    <a href="#" aria-label="Facebook">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
                        </svg>
                    </a>
                    <a href="#" aria-label="Instagram">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="2" y="2" width="20" height="20" rx="5" ry="5"/>
                            <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/>
                            <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="footer-container">
                <p>© 2025 Baraime El Rahma. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <script>
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleButton = document.querySelector('.password-toggle i');
            if (passwordField.type === 'password') {//type password ya"ny ...
                passwordField.type = 'text';
                toggleButton.classList.remove('fa-eye-slash');
                toggleButton.classList.add('fa-eye');
            } else {
                passwordField.type = 'password';
                toggleButton.classList.remove('fa-eye');
                toggleButton.classList.add('fa-eye-slash');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const hamburger = document.querySelector('.hamburger');
            const navLinks = document.querySelector('.nav-links');
            const body = document.body;

            if (hamburger && navLinks) {
                hamburger.addEventListener('click', function() {
                    hamburger.classList.toggle('active');
                    navLinks.classList.toggle('active');
                    
                    if (navLinks.classList.contains('active')) {
                        body.style.overflow = 'hidden';
                    } else {
                        body.style.overflow = 'auto';
                    }
                });

                const navLinksItems = navLinks.querySelectorAll('a');
                navLinksItems.forEach(link => {
                    link.addEventListener('click', function() { 
                        hamburger.classList.remove('active');
                        navLinks.classList.remove('active');
                        body.style.overflow = 'auto';
                    });
                });

                document.addEventListener('click', function(event) {
                    if (!hamburger.contains(event.target) && !navLinks.contains(event.target)) {
                        hamburger.classList.remove('active');
                        navLinks.classList.remove('active');
                        body.style.overflow = 'auto';
                    }
                });
            }

            document.querySelector('.registration-container').style.opacity = '0'; 
            document.querySelector('.registration-container').style.transform = 'translateY(20px)';
            document.querySelector('.registration-container').style.transition = 'all 0.6s ease';
            
            setTimeout(() => {
                document.querySelector('.registration-container').style.opacity = '1';
                document.querySelector('.registration-container').style.transform = 'translateY(0)';
            }, 100);
        });
    </script>
</body>
</html>