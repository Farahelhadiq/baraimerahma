<?php
session_start();
require_once 'config.php';

// Vérifier que l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header('Location: adminlogin.php');
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID enfant invalide.");
}

$id_enfant = (int)$_GET['id'];

try {
    $pdo = connectDB();

    // Récupérer groupes pour la liste déroulante
    $stmtGroupes = $pdo->query("SELECT id_groupe, nom_groupe FROM groupes ORDER BY nom_groupe");
    $groupes = $stmtGroupes->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer parents pour la liste déroulante
    $stmtParents = $pdo->query("SELECT id_parent, nom, prenom FROM parent ORDER BY nom, prenom");
    $parents = $stmtParents->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer l'enfant à modifier
    $stmtEnfant = $pdo->prepare("SELECT * FROM enfants WHERE id_enfant = ?");
    $stmtEnfant->execute([$id_enfant]);
    $enfant = $stmtEnfant->fetch(PDO::FETCH_ASSOC);
    if (!$enfant) {
        die("Enfant non trouvé.");
    }

    // Récupérer le groupe actuel pour l'enfant pour l'année scolaire courante
    $annee_scolaire = date('Y') . '-' . (date('Y') + 1); // Format AAAA-AAAA
    $stmtGroupeEnfant = $pdo->prepare("SELECT id_groupe FROM Enfant_groupe WHERE id_enfant = ? AND annee_scolaire = ?");
    $stmtGroupeEnfant->execute([$id_enfant, $annee_scolaire]);
    $enfant_groupe = $stmtGroupeEnfant->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erreur base de données : " . $e->getMessage());
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $genre = $_POST['genre'] ?? '';
    $date_naissance = $_POST['date_naissance'] ?? '';
    $photo = trim($_POST['photo'] ?? '');
    $id_groupe = $_POST['id_groupe'] ?? null;
    $id_parent = $_POST['id_parent'] ?? null;

    // Validate required fields, excluding id_parent
    if ($nom === '' || $prenom === '' || $genre === '' || $date_naissance === '' || !$id_groupe) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } else {
        try {
            // Validate id_parent if provided
            if ($id_parent !== null && $id_parent !== '') {
                $stmtCheckParent = $pdo->prepare("SELECT COUNT(*) FROM parent WHERE id_parent = ?");
                $stmtCheckParent->execute([$id_parent]);
                $parentExists = $stmtCheckParent->fetchColumn();
                if (!$parentExists) {
                    $error = "Le parent sélectionné n'existe pas dans la base de données.";
                }
            }

            if (!$error) {
                // Mise à jour de la table enfants (hors groupe)
                $stmtUpdate = $pdo->prepare("
                    UPDATE enfants SET nom = ?, prenom = ?, genre = ?, photo = ?, date_naissance = ?, id_parent = ?
                    WHERE id_enfant = ?
                ");
                $stmtUpdate->execute([$nom, $prenom, $genre, $photo, $date_naissance, $id_parent ?: null, $id_enfant]);

                // Mise à jour ou insertion dans Enfant_groupe pour l'année scolaire courante
                if ($enfant_groupe) {
                    // Update si déjà affecté à un groupe cette année
                    $stmtUpdateGroupe = $pdo->prepare("
                        UPDATE Enfant_groupe SET id_groupe = ?
                        WHERE id_enfant = ? AND annee_scolaire = ?
                    ");
                    $stmtUpdateGroupe->execute([$id_groupe, $id_enfant, $annee_scolaire]);
                } else {
                    // Insert nouvelle affectation
                    $stmtInsertGroupe = $pdo->prepare("
                        INSERT INTO Enfant_groupe (id_enfant, id_groupe, annee_scolaire) VALUES (?, ?, ?)
                    ");
                    $stmtInsertGroupe->execute([$id_enfant, $id_groupe, $annee_scolaire]);
                }

                header('Location: admin_dashboard.php');
                exit;
            }
        } catch (PDOException $e) {
            $error = "Erreur lors de la mise à jour : " . $e->getMessage();
        }
    }
} else {
    // Pré-remplir les valeurs du formulaire
    $nom = $enfant['nom'];
    $prenom = $enfant['prenom'];
    $genre = $enfant['genre'];
    $date_naissance = $enfant['date_naissance'];
    $photo = $enfant['photo'];
    $id_parent = $enfant['id_parent'];
    $id_groupe = $enfant_groupe['id_groupe'] ?? null;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier un enfant</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="modifier_enfant.css">
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
                <h1 class="page-title">Modifier un enfant</h1>
                <p class="page-subtitle">Mettez à jour les informations de l'enfant</p>
            </div>

            <?php if ($error): ?>
                <div class="notification error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Formulaire de modification</h2>
                </div>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="nom">Nom *</label>
                        <input type="text" name="nom" id="nom" required value="<?= htmlspecialchars($nom) ?>">
                    </div>
                    <div class="form-group">
                        <label for="prenom">Prénom *</label>
                        <input type="text" name="prenom" id="prenom" required value="<?= htmlspecialchars($prenom) ?>">
                    </div>
                    <div class="form-group">
                        <label for="genre">Genre *</label>
                        <select name="genre" id="genre" required>
                            <option value="">-- Choisir --</option>
                            <option value="M" <?= ($genre === 'M') ? 'selected' : '' ?>>Masculin</option>
                            <option value="F" <?= ($genre === 'F') ? 'selected' : '' ?>>Féminin</option>
                            <option value="Autre" <?= ($genre === 'Autre') ? 'selected' : '' ?>>Autre</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="date_naissance">Date de naissance *</label>
                        <input type="date" name="date_naissance" id="date_naissance" required value="<?= htmlspecialchars($date_naissance) ?>">
                    </div>
                    <div class="form-group">
                        <label for="photo">Photo (URL)</label>
                        <input type="text" name="photo" id="photo" value="<?= htmlspecialchars($photo) ?>">
                    </div>
                    <div class="form-group">
                        <label for="id_groupe">Groupe *</label>
                        <select name="id_groupe" id="id_groupe" required>
                            <option value="">-- Choisir un groupe --</option>
                            <?php foreach ($groupes as $g): ?>
                                <option value="<?= $g['id_groupe'] ?>" <?= ($id_groupe == $g['id_groupe']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($g['nom_groupe']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="id_parent">Parent (facultatif)</label>
                        <select name="id_parent" id="id_parent">
                            <option value="">-- Aucun parent --</option>
                            <?php foreach ($parents as $p): ?>
                                <option value="<?= $p['id_parent'] ?>" <?= ($id_parent == $p['id_parent']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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