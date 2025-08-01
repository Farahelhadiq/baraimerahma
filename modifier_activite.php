<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: adminlogin.php');
    exit;
}

try {
    $pdo = connectDB();

    // Vérifier les paramètres GET
    if (!isset($_GET['id_activite'], $_GET['date'], $_GET['id_groupe'])) {
        die("Paramètres manquants.");
    }

    $id_activite = (int)$_GET['id_activite'];
    $date_activite = urldecode($_GET['date']);
    $id_groupe = (int)$_GET['id_groupe'];

    // Normaliser la date pour la comparaison
    try {
        $date_activite_obj = new DateTime($date_activite);
        $date_activite_formatted = $date_activite_obj->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        die("Format de date invalide : " . htmlspecialchars($e->getMessage()));
    }

    // Récupérer la liste des groupes pour le select
    $stmtGroupes = $pdo->query("SELECT id_groupe, nom_groupe FROM groupes ORDER BY nom_groupe");
    $groupes = $stmtGroupes->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les données de l'activité et de l'association groupes_activites
    $stmt = $pdo->prepare("
        SELECT a.nom_activite, a.description, ga.date_d_activite, ga.id_groupe
        FROM activite a
        JOIN groupes_activites ga ON a.id_activite = ga.id_activite
        WHERE a.id_activite = :id_activite AND ga.date_d_activite = :date_d_activite AND ga.id_groupe = :id_groupe
    ");
    $stmt->execute([
        ':id_activite' => $id_activite,
        ':date_d_activite' => $date_activite_formatted,
        ':id_groupe' => $id_groupe
    ]);
    $activite = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$activite) {
        die("Activité introuvable pour la combinaison spécifiée.");
    }

    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nom_activite = trim($_POST['nom_activite'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $date_d_activite = trim($_POST['date_d_activite'] ?? '');
        $id_groupe_nouveau = (int)($_POST['id_groupe'] ?? 0);

        // Validation des champs
        if ($nom_activite === '') {
            $errors[] = "Le nom de l'activité est obligatoire.";
        }
        if ($date_d_activite === '') {
            $errors[] = "La date et l'heure de l'activité sont obligatoires.";
        } elseif (!preg_match("/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/", $date_d_activite)) {
            $errors[] = "Format de date et heure invalide (utilisez AAAA-MM-JJ HH:MM).";
        }
        if ($id_groupe_nouveau === 0) {
            $errors[] = "Le groupe doit être sélectionné.";
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // Mettre à jour la table activite
                $stmtUpdateActivite = $pdo->prepare("UPDATE activite SET nom_activite = :nom, description = :desc WHERE id_activite = :id");
                $stmtUpdateActivite->execute([
                    ':nom' => $nom_activite,
                    ':desc' => $description,
                    ':id' => $id_activite
                ]);

                // Normaliser la date postée
                $date_d_activite_formatted = (new DateTime($date_d_activite))->format('Y-m-d H:i:s');

                // Si date ou groupe ont changé, mettre à jour groupes_activites
                if ($date_d_activite_formatted !== $activite['date_d_activite'] || $id_groupe_nouveau !== $id_groupe) {
                    // Vérifier si la nouvelle combinaison existe déjà
                    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM groupes_activites WHERE id_groupe = :groupe AND id_activite = :id AND date_d_activite = :date");
                    $stmtCheck->execute([
                        ':groupe' => $id_groupe_nouveau,
                        ':id' => $id_activite,
                        ':date' => $date_d_activite_formatted
                    ]);
                    if ($stmtCheck->fetchColumn() > 0) {
                        $errors[] = "Cette combinaison de groupe, activité et date existe déjà.";
                        $pdo->rollBack();
                    } else {
                        // Supprimer l'ancien lien
                        $stmtDelete = $pdo->prepare("DELETE FROM groupes_activites WHERE id_activite = :id AND date_d_activite = :date AND id_groupe = :groupe");
                        $stmtDelete->execute([
                            ':id' => $id_activite,
                            ':date' => $activite['date_d_activite'],
                            ':groupe' => $id_groupe
                        ]);

                        // Insérer le nouveau lien
                        $stmtInsert = $pdo->prepare("INSERT INTO groupes_activites (id_groupe, id_activite, date_d_activite) VALUES (:groupe, :id, :date)");
                        $stmtInsert->execute([
                            ':groupe' => $id_groupe_nouveau,
                            ':id' => $id_activite,
                            ':date' => $date_d_activite_formatted
                        ]);
                    }
                }

                if (empty($errors)) {
                    $pdo->commit();
                    header('Location: gestion_planning.php?msg=activite_modifiee');
                    exit;
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                $errors[] = "Erreur lors de la mise à jour : " . htmlspecialchars($e->getMessage());
            }
        }
    }
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . htmlspecialchars($e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier une activité</title>
      <link rel="stylesheet" href="modifier_activite.css">
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
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
                <h1 class="page-title">Modifier une activité</h1>
                <p class="page-subtitle">Mettez à jour les informations de l'activité</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="notification error">
                    <ul style="margin: 0; padding-left: 20px;">
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Formulaire de modification</h2>
                </div>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="nom_activite">Nom de l'activité *</label>
                        <input type="text" id="nom_activite" name="nom_activite" required value="<?= htmlspecialchars($_POST['nom_activite'] ?? $activite['nom_activite']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="4"><?= htmlspecialchars($_POST['description'] ?? $activite['description'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="date_d_activite">Date et heure de l'activité *</label>
                        <input type="datetime-local" id="date_d_activite" name="date_d_activite" required value="<?= htmlspecialchars($_POST['date_d_activite'] ?? (new DateTime($activite['date_d_activite']))->format('Y-m-d\TH:i')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="id_groupe">Groupe assigné *</label>
                        <select id="id_groupe" name="id_groupe" required>
                            <option value="">-- Choisir un groupe --</option>
                            <?php foreach ($groupes as $groupe): ?>
                                <option value="<?= htmlspecialchars($groupe['id_groupe']) ?>" <?= ((isset($_POST['id_groupe']) && $_POST['id_groupe'] == $groupe['id_groupe']) || (!isset($_POST['id_groupe']) && $activite['id_groupe'] == $groupe['id_groupe'])) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($groupe['nom_groupe']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-actions">
                        <button type="submit">Enregistrer</button>
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