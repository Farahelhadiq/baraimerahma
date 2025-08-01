<?php
session_start();
require_once 'config.php';

// Vérifier que l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header('Location: adminlogin.php');
    exit;
}

// Vérifier si un ID est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID professeur invalide.");
}

$id_professeur = (int)$_GET['id'];
$errorMessage = '';
$successMessage = '';

try {
    $pdo = connectDB();

    // Récupérer les infos du professeur
    $stmt = $pdo->prepare("SELECT * FROM professeur WHERE id_professeur = ?");
    $stmt->execute([$id_professeur]);
    $professeur = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$professeur) {
        die("Professeur non trouvé.");
    }

    // Récupérer les groupes pour la liste déroulante
    $stmtGroupes = $pdo->query("SELECT id_groupe, nom_groupe FROM groupes ORDER BY nom_groupe");
    $groupes = $stmtGroupes->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer l'assignation de groupe actuelle
    $stmtGroupeProf = $pdo->prepare("SELECT id_groupe, annee_scolaire FROM professeurs_groupes WHERE id_professeur = ? ORDER BY annee_scolaire DESC LIMIT 1");
    $stmtGroupeProf->execute([$id_professeur]);
    $groupe_prof = $stmtGroupeProf->fetch(PDO::FETCH_ASSOC);

    // Pré-remplir les valeurs du formulaire
    $nom = $professeur['nom'];
    $prenom = $professeur['prenom'];
    $email = $professeur['email'];
    $id_groupe = $groupe_prof['id_groupe'] ?? null;
    $annee_scolaire = $groupe_prof['annee_scolaire'] ?? date('Y');

    // Si le formulaire est soumis
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nom = trim($_POST['nom']);
        $prenom = trim($_POST['prenom']);
        $email = trim($_POST['email']);
        $mot_de_passe = trim($_POST['mot_de_passe']);
        $id_groupe = $_POST['id_groupe'] ?? null;
        $annee_scolaire = trim($_POST['annee_scolaire']);

        // Validation
        if (empty($nom) || empty($prenom) || empty($email) || empty($id_groupe) || empty($annee_scolaire)) {
            $errorMessage = "Tous les champs obligatoires doivent être remplis.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = "Adresse email invalide.";
        } elseif (!is_numeric($annee_scolaire) || $annee_scolaire < 2000 || $annee_scolaire > date('Y') + 1) {
            $errorMessage = "Année scolaire invalide.";
        } else {
            try {
                // Vérifier si l'email est déjà utilisé par un autre professeur
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM professeur WHERE email = ? AND id_professeur != ?");
                $stmt->execute([$email, $id_professeur]);
                if ($stmt->fetchColumn() > 0) {
                    $errorMessage = "Cet email est déjà utilisé par un autre professeur.";
                } else {
                    // Débuter une transaction
                    $pdo->beginTransaction();

                    // Mise à jour des données du professeur
                    $sql = "UPDATE professeur SET nom = ?, prenom = ?, email = ?";
                    $params = [$nom, $prenom, $email];

                    if (!empty($mot_de_passe)) {
                        $sql .= ", mot_de_passe = ?";
                        $params[] = password_hash($mot_de_passe, PASSWORD_DEFAULT);
                    }

                    $sql .= " WHERE id_professeur = ?";
                    $params[] = $id_professeur;

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);

                    // Mise à jour ou insertion dans professeurs_groupes
                    if ($groupe_prof) {
                        // Mise à jour si une assignation existe pour cette année
                        $stmt = $pdo->prepare("UPDATE professeurs_groupes SET id_groupe = ?, annee_scolaire = ? WHERE id_professeur = ? AND annee_scolaire = ?");
                        $stmt->execute([$id_groupe, $annee_scolaire, $id_professeur, $groupe_prof['annee_scolaire']]);
                    } else {
                        // Insertion d'une nouvelle assignation
                        $stmt = $pdo->prepare("INSERT INTO professeurs_groupes (id_professeur, id_groupe, annee_scolaire) VALUES (?, ?, ?)");
                        $stmt->execute([$id_professeur, $id_groupe, $annee_scolaire]);
                    }

                    // Valider la transaction
                    $pdo->commit();

                    // Redirection après succès
                    header('Location: admin_dashboard.php?msg=prof_modifie');
                    exit;
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                $errorMessage = "Erreur base de données : " . $e->getMessage();
            }
        }
    }
} catch (PDOException $e) {
    $errorMessage = "Erreur base de données : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier un professeur</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="modifier_professeur.css">
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
                <a href="ajouter_professeur.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-user-tie"></i></span>
                    <span class="nav-text">Ajouter un professeur</span>
                </a>
                <a href="gestion_planning.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-regular fa-clock"></i></span>
                    <span class="nav-text">Gérer le planning</span>
                </a>
                <a href="ajouter_activite.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-plus"></i></span>
                    <span class="nav-text">Ajouter une activité</span>
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
                <h1 class="page-title">Modifier un professeur</h1>
                <p class="page-subtitle">Mettez à jour les informations du professeur</p>
            </div>

            <?php if (!empty($errorMessage)): ?>
                <div class="notification error"><?= htmlspecialchars($errorMessage) ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Formulaire de modification</h2>
                </div>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="nom">Nom *</label>
                        <input type="text" id="nom" name="nom" required value="<?= htmlspecialchars($nom) ?>">
                    </div>
                    <div class="form-group">
                        <label for="prenom">Prénom *</label>
                        <input type="text" id="prenom" name="prenom" required value="<?= htmlspecialchars($prenom) ?>">
                    </div>
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required value="<?= htmlspecialchars($email) ?>">
                    </div>
                    <div class="form-group">
                        <label for="mot_de_passe">Nouveau mot de passe (laisser vide si inchangé)</label>
                        <input type="password" id="mot_de_passe" name="mot_de_passe">
                    </div>
                    <div class="form-group">
                        <label for="id_groupe">Groupe assigné *</label>
                        <select id="id_groupe" name="id_groupe" required>
                            <option value="">-- Choisir un groupe --</option>
                            <?php foreach ($groupes as $g): ?>
                                <option value="<?= htmlspecialchars($g['id_groupe']) ?>" <?= ($id_groupe == $g['id_groupe']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($g['nom_groupe']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="annee_scolaire">Année scolaire *</label>
                        <input type="number" id="annee_scolaire" name="annee_scolaire" required value="<?= htmlspecialchars($annee_scolaire) ?>" min="2000" max="<?= date('Y') + 1 ?>">
                    </div>
                    <div class="form-actions">
                        <button type="submit">Enregistrer</button>
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