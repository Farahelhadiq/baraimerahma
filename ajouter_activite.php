<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: adminlogin.php');
    exit;
}

try {
    $pdo = connectDB();
    $erreur = "";
    $success = "";
    $groupes = $pdo->query("SELECT id_groupe, nom_groupe FROM groupes ORDER BY nom_groupe")->fetchAll(PDO::FETCH_ASSOC);
    $activites = $pdo->query("SELECT id_activite, nom_activite FROM activite ORDER BY nom_activite")->fetchAll(PDO::FETCH_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id_groupe = filter_input(INPUT_POST, 'id_groupe', FILTER_VALIDATE_INT);
        $id_activite = filter_input(INPUT_POST, 'id_activite', FILTER_VALIDATE_INT);
        $date_d_activite = trim($_POST['date_d_activite']);
        if (!$id_groupe || !$id_activite || !$date_d_activite) {
            $erreur = "Tous les champs sont obligatoires.";
        } elseif (!preg_match("/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/", $date_d_activite)) {//validation dyal la date "2025-06-24T14:30"
            $erreur = "Format de date invalide. Utilisez le format AAAA-MM-JJ HH:MM.";
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM groupes WHERE id_groupe = ?");
            $stmt->execute([$id_groupe]);
            if ($stmt->fetchColumn() == 0) {
                $erreur = "Le groupe sélectionné n'existe pas.";//hna rah mal9inax lgroup 
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM activite WHERE id_activite = ?");
                $stmt->execute([$id_activite]);
                if ($stmt->fetchColumn() == 0) {
                    $erreur = "L'activité sélectionnée n'existe pas.";//activité no 
                } else {
                    $stmt = $pdo->prepare("INSERT INTO groupes_activites (id_groupe, id_activite, date_d_activite) 
                                           VALUES (:id_groupe, :id_activite, :date_d_activite)");
                    try {
                        $stmt->execute([
                            'id_groupe' => $id_groupe,
                            'id_activite' => $id_activite,
                            'date_d_activite' => $date_d_activite
                        ]);
                        $success = "Activité ajoutée avec succès.";
                        $_POST = [];
                    } catch (PDOException $e) {//les erreur dyal connection db base 
                        if ($e->getCode() == '23000') {
                            $erreur = "Cette combinaison de groupe, activité et date existe déjà.";
                        } else {
                            $erreur = "Erreur lors de l'ajout : " . htmlspecialchars($e->getMessage());
                        }
                    }
                }
            }
        }
    }
} catch (PDOException $e) {
    $erreur = "Erreur de connexion à la base de données : " . htmlspecialchars($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter une activité</title>
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
      <link rel="stylesheet" href="ajouter_activite.css">
</head>
<body>
    <div class="app-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-title">Tableau de bord Admin</div>
                <button class="toggle-btn" onclick="toggleSidebar()">←</button>
            </div>
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($_SESSION['admin_prenom'] ?? 'A', 0, 1)) ?></div>
                <div class="user-details">
                    <h4><?= htmlspecialchars($_SESSION['admin_prenom'] ?? 'Admin') ?> <?= htmlspecialchars($_SESSION['admin_nom'] ?? '') ?></h4>
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
                <a href="ajouter_activite.php" class="nav-item active">
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
                <h1 class="page-title">Ajouter une activité</h1>
                <p class="page-subtitle">Planifiez une nouvelle activité pour un groupe</p>
            </div>

            <?php if ($erreur): ?>
                <div class="notification error"><?= htmlspecialchars($erreur) ?></div>
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
                        <label for="id_groupe">Groupe *</label>
                        <select name="id_groupe" id="id_groupe" required>
                            <option value="">-- Choisir un groupe --</option>
                            <?php foreach ($groupes as $groupe): ?>
                                <option value="<?= $groupe['id_groupe'] ?>" <?= (isset($_POST['id_groupe']) && $_POST['id_groupe'] == $groupe['id_groupe']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($groupe['nom_groupe']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="id_activite">Activité *</label>
                        <select name="id_activite" id="id_activite" required>
                            <option value="">-- Choisir une activité --</option>
                            <?php foreach ($activites as $activite): ?>
                                <option value="<?= $activite['id_activite'] ?>" <?= (isset($_POST['id_activite']) && $_POST['id_activite'] == $activite['id_activite']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($activite['nom_activite']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="date_d_activite">Date de l'activité *</label>
                        <input type="datetime-local" name="date_d_activite" id="date_d_activite" required value="<?= htmlspecialchars($_POST['date_d_activite'] ?? '') ?>">
                    </div>
                    <div class="form-actions">
                        <button type="submit">Ajouter</button>
                        <a href="gestion_planning.php"><button type="button">Annuler</button></a>
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