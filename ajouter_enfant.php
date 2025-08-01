<?php
session_start();
require_once 'config.php';
if (!isset($_SESSION['admin_id'])) {
    header('Location: adminlogin.php');
    exit;
}

try {
    $pdo = connectDB();
    $stmtGroupes = $pdo->query("SELECT id_groupe, nom_groupe FROM groupes ORDER BY nom_groupe");
    $groupes = $stmtGroupes->fetchAll(PDO::FETCH_ASSOC);
    $stmtParents = $pdo->query("SELECT id_parent, nom, prenom FROM parent ORDER BY nom, prenom");
    $parents = $stmtParents->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur base de données : " . $e->getMessage());
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $genre = $_POST['genre'] ?? '';
    $date_naissance = $_POST['date_naissance'] ?? '';
    $photo = trim($_POST['photo'] ?? '');
    $id_groupe = $_POST['id_groupe'] ?? null;
    $id_parent = $_POST['id_parent'] ?? null;
    $annee_scolaire = trim($_POST['annee_scolaire'] ?? '');

    // Validate required fields, excluding id_parent
    if ($nom === '' || $prenom === '' || $genre === '' || $date_naissance === '' || strtotime($date_naissance) > strtotime(date('Y-m-d')) || !$id_groupe || $annee_scolaire === '') {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } elseif (!preg_match('/^\d{4}-\d{4}$/', $annee_scolaire)) {
        $error = "L'année scolaire doit être au format AAAA-AAAA (ex. 2024-2025).";
    } else {
        try {
            // Verify if the child already exists
            $stmtCheckEnfant = $pdo->prepare("
                SELECT COUNT(*) FROM enfants 
                WHERE nom = ? AND prenom = ? AND date_naissance = ?
            ");
            $stmtCheckEnfant->execute([$nom, $prenom, $date_naissance]);
            $count = $stmtCheckEnfant->fetchColumn();

            if ($count > 0) {
                $error = "L’enfant existe déjà .";
            } else {
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
                    // Start a transaction
                    $pdo->beginTransaction();

                    // Insert into enfants table, allowing NULL for id_parent
                    $stmtInsertEnfant = $pdo->prepare("
                        INSERT INTO enfants (nom, prenom, genre, photo, date_naissance, id_parent)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmtInsertEnfant->execute([$nom, $prenom, $genre, $photo, $date_naissance, $id_parent ?: null]);

                    // Get the ID of the newly inserted child
                    $id_enfant = $pdo->lastInsertId();

                    // Insert into Enfant_groupe table
                    $stmtInsertGroupeEnfant = $pdo->prepare("
                        INSERT INTO Enfant_groupe (id_groupe, id_enfant, annee_scolaire)
                        VALUES (?, ?, ?)
                    ");
                    $stmtInsertGroupeEnfant->execute([$id_groupe, $id_enfant, $annee_scolaire]);

                    // Commit the transaction
                    $pdo->commit();

                    $success = "Enfant ajouté et associé au groupe avec succès.";
                    // Redirect to dashboard after successful addition
                    header('Location: admin_dashboard.php');
                    exit;
                }
            }
        } catch (PDOException $e) {
            // Roll back the transaction on error
            $pdo->rollBack();
            $error = "Erreur lors de l'ajout : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter un enfant</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="ajouter_enfant.css">
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
                <a href="ajouter_enfant.php" class="nav-item active">
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
                <h1 class="page-title">Ajouter un enfant</h1>
                <p class="page-subtitle">Remplissez les informations pour ajouter un nouvel enfant</p>
            </div>

            <?php if ($error): ?>
                <div class="notification error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="notification success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Formulaire d'ajout</h2>
                </div>
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Nom *</label>
                        <input type="text" name="nom" required value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Prénom *</label>
                        <input type="text" name="prenom" required value="<?= htmlspecialchars($_POST['prenom'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Genre *</label>
                        <select name="genre" required>
                            <option value="">-- Choisir --</option>
                            <option value="M" <?= (isset($_POST['genre']) && $_POST['genre'] === 'M') ? 'selected' : '' ?>>Masculin</option>
                            <option value="F" <?= (isset($_POST['genre']) && $_POST['genre'] === 'F') ? 'selected' : '' ?>>Féminin</option>
                            <option value="Autre" <?= (isset($_POST['genre']) && $_POST['genre'] === 'Autre') ? 'selected' : '' ?>>Autre</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date de naissance *</label>
                        <input type="date" name="date_naissance" required value="<?= htmlspecialchars($_POST['date_naissance'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Photo (URL)</label>
                        <input type="text" name="photo" value="<?= htmlspecialchars($_POST['photo'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Groupe *</label>
                        <select name="id_groupe" required>
                            <option value="">-- Choisir un groupe --</option>
                            <?php foreach ($groupes as $g): ?>
                                <option value="<?= $g['id_groupe'] ?>" <?= (isset($_POST['id_groupe']) && $_POST['id_groupe'] == $g['id_groupe']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($g['nom_groupe']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Année scolaire * (ex. 2024-2025)</label>
                        <input type="text" name="annee_scolaire" required value="<?= htmlspecialchars($_POST['annee_scolaire'] ?? '') ?>" pattern="\d{4}-\d{4}" placeholder="AAAA-AAAA">
                    </div>
                    <div class="form-group">
                        <label>Parent (facultatif)</label>
                        <select name="id_parent">
                            <option value="">-- Aucun parent --</option>
                            <?php foreach ($parents as $p): ?>
                                <option value="<?= $p['id_parent'] ?>" <?= (isset($_POST['id_parent']) && $_POST['id_parent'] == $p['id_parent']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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