<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: adminlogin.php');
    exit;
}

$successMessage = '';
$errorMessage = '';

try {
    $pdo = connectDB();

    $stmtGroupes = $pdo->query("SELECT id_groupe, nom_groupe FROM groupes ORDER BY nom_groupe");
    $groupes = $stmtGroupes->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $errorMessage = "Erreur base de données : " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $mot_de_passe = trim($_POST['mot_de_passe']);
    $id_groupe = $_POST['id_groupe'] ?? null;
    $annee_scolaire = trim($_POST['annee_scolaire'] ?? '');

    if (empty($nom) || empty($prenom) || empty($email) || empty($mot_de_passe) || empty($id_groupe) || empty($annee_scolaire)) {
        $errorMessage = "Tous les champs sont obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "Adresse email invalide.";
    } elseif (!is_numeric($annee_scolaire) || $annee_scolaire < 2000 || $annee_scolaire > date('Y') + 1) {//numéro fo9 2000 Kbar mn 2026
        $errorMessage = "Année scolaire invalide.";
    } else {
        try {
           
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM professeur WHERE nom = ? AND prenom = ? AND email = ?");
            $stmt->execute([$nom, $prenom, $email]);
            if ($stmt->fetchColumn() > 0) {
                $errorMessage = "Le professeur existe déjà dans la base de données.";
            } else {
                $hashedPassword = password_hash($mot_de_passe, PASSWORD_DEFAULT);
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO professeur (nom, prenom, email, mot_de_passe) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nom, $prenom, $email, $hashedPassword]);
                $id_professeur = $pdo->lastInsertId();
                $stmt = $pdo->prepare("INSERT INTO professeurs_groupes (id_professeur, id_groupe, annee_scolaire) VALUES (?, ?, ?)");
                $stmt->execute([$id_professeur, $id_groupe, $annee_scolaire]);
                $pdo->commit();
                header('Location: admin_dashboard.php?msg=prof_ajoute');
                exit;
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errorMessage = "Erreur : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter un professeur</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="ajouter_professeur.css">
</head>
<body>
    <div class="app-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-title">Tableau de bord Admin</div>
                <button class="toggle-btn" onclick="toggleSidebar()">←</button>
            </div>
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($_SESSION['admin_prenom'], 0, 1)) ?></div>
                <div class="user-details">
                    <h4><?= htmlspecialchars($_SESSION['admin_prenom']) ?> <?= htmlspecialchars($_SESSION['admin_nom']) ?></h4>
                    <p>Administrateur</p>
                </div>
            </div>
            <div class="nav-menu">
                <a href="admin_dashboard.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="ajouter_enfant.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-regular fa-user"></i></span>
                    <span class="nav-text">Ajouter un enfant</span>
                </a>
                <a href="ajouter_professeur.php" class="nav-item active">
                    <span class="nav-icon"><i class="fa-solid fa-user-tie"></i></span>
                    <span class="nav-text">Ajouter un professeur</span>
                </a>
                <a href="gestion_planning.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-regular fa-clock"></i></span>
                    <span class="nav-text">Gérer le planning</span>
                </a>
            </div>
            <div class="logout-section">
                <a href="logout.php" class="logout-btn">
                    <span class="nav-icon"><i class="fa-solid fa-right-from-bracket"></i></span>
                    <span>Déconnexion</span>
                </a>
            </div>
        </div>
        <button class="show-sidebar-btn" onclick="toggleSidebar()">☰</button>
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Ajouter un professeur</h1>
                <p class="page-subtitle">Remplissez les informations pour ajouter un nouveau professeur</p>
            </div>

            <?php if (!empty($errorMessage)): ?>
                <div class="notification error"><?= htmlspecialchars($errorMessage) ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Formulaire d'ajout</h2>
                </div>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="nom">Nom</label>
                        <input type="text" id="nom" name="nom" required value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="prenom">Prénom</label>
                        <input type="text" id="prenom" name="prenom" required value="<?= htmlspecialchars($_POST['prenom'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="mot_de_passe">Mot de passe</label>
                        <input type="password" id="mot_de_passe" name="mot_de_passe" required>
                    </div>
                    <div class="form-group">
                        <label for="id_groupe">Groupe assigné</label>
                        <select id="id_groupe" name="id_groupe" required>
                            <option value="">-- Choisir un groupe --</option>
                            <?php foreach ($groupes as $g): ?>
                                <option value="<?php echo $g['id_groupe'] ?>" <?php echo (isset($_POST['id_groupe']) && $_POST['id_groupe'] == $g['id_groupe']) ? 'selected' : '' ?>>
                                    <?php echo htmlspecialchars($g['nom_groupe']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="annee_scolaire">Année scolaire</label>
                        <input type="number" id="annee_scolaire" name="annee_scolaire" required value="<?php echo htmlspecialchars($_POST['annee_scolaire'] ?? date('Y')) ?>" min="2000" max="<?php echo date('Y') + 1 ?>">
                    </div>
                    <div class="form-actions">
                        <button type="submit">Ajouter</button>
                        <a href="admin_dashboard.php"><button type="button">Annuler</button></a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            const toggleBtn = document.querySelector('.toggle-btn');
            const showSidebarBtn = document.querySelector('.show-sidebar-btn');

            sidebar.classList.toggle('hidden');
            mainContent.classList.toggle('full-width');
            toggleBtn.classList.toggle('hidden');
            showSidebarBtn.classList.toggle('visible');
        }
    </script>
</body>
</html>