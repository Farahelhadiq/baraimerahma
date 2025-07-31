<?php
session_start();
require 'config.php';

if (!isset($_SESSION['id_professeur'])) {
    header("Location: login_professeur.php");
    exit();
}

$pdo = connectDB();
$id_professeur = $_SESSION['id_professeur'];
$errorMessage = '';
$successMessage = '';

$stmt = $pdo->prepare("SELECT id_groupe FROM professeurs_groupes WHERE id_professeur = ? ORDER BY annee_scolaire DESC LIMIT 1");
$stmt->execute([$id_professeur]);
$groupe = $stmt->fetch();   
$id_groupe = $groupe ? $groupe['id_groupe'] : null;

$enfants = [];
if ($id_groupe) {

    $stmt = $pdo->prepare("
        SELECT e.* 
        FROM enfants e
        INNER JOIN Enfant_groupe eg ON e.id_enfant = eg.id_enfant
        WHERE eg.id_groupe = ? AND eg.annee_scolaire = (
            SELECT MAX(annee_scolaire) FROM Enfant_groupe WHERE id_groupe = ?
        )
    ");//les enfant lifgroup lif année scolaire
    $stmt->execute([$id_groupe, $id_groupe]);
    $enfants = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();//ytnafdo tous les opération 
        foreach ($enfants as $enfant) {
            $id_enfant = $enfant['id_enfant'];
            $is_absent = isset($_POST['absents'][$id_enfant]);//wax absent checkbox
            $heure_debut = $_POST['heure_debut'][$id_enfant] ?? null;
            $heure_fin = $_POST['heure_fin'][$id_enfant] ?? null;
            $justification = $_POST['justification'][$id_enfant] ?? null;

            if ($is_absent) {
                if (empty($heure_debut) || empty($heure_fin)) {
                    throw new Exception("Les heures de début et de fin sont obligatoires pour une absence.");
                }

                $check = $pdo->prepare("SELECT * FROM absences WHERE id_enfant = ? AND date_ = CURDATE()");
                $check->execute([$id_enfant]);
                if ($check->rowCount() > 0) {
                  
                    $update = $pdo->prepare("UPDATE absences SET heure_debut = ?, heure_fin = ?, justification = ? WHERE id_enfant = ? AND date_ = CURDATE()");
                    $update->execute([$heure_debut, $heure_fin, $justification, $id_enfant]);
                } //mise à jour l'heur début w la fin hit déja kayn
                else {
    
                    $insert = $pdo->prepare("INSERT INTO absences (date_, heure_debut, heure_fin, justification, id_enfant) VALUES (CURDATE(), ?, ?, ?, ?)");
                    $insert->execute([$heure_debut, $heure_fin, $justification, $id_enfant]);
                }//Enregistrement   
            }
            else {
            
                $delete = $pdo->prepare("DELETE FROM absences WHERE id_enfant = ? AND date_ = CURDATE()");
                $delete->execute([$id_enfant]);
            }//delet absence
        }
        $pdo->commit();//tous opération bien
        $successMessage = "Absences mises à jour avec succès.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMessage = "Erreur lors de la mise à jour des absences : " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Marquer absence</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
<link rel="stylesheet" href="marquer_presences.css">
</head>
<body>
    <div class="app-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-title">Espace Professeur</div>
                <button class="toggle-btn" onclick="toggleSidebar()">←</button>
            </div>
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($_SESSION['prenom'], 0, 1)) ?></div>
                <div class="user-details">
                    <h4><?= htmlspecialchars($_SESSION['prenom']) ?> <?= htmlspecialchars($_SESSION['nom']) ?></h4>
                    <p>Professeur</p>
                </div>
            </div>
            <div class="nav-menu">
                <a href="espace_professeur.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="marquer_presences.php" class="nav-item ">
                    <span class="nav-icon"><i class="fa-solid fa-calendar-xmark"></i></span>
                    <span class="nav-text">Marquer absence</span>
                </a>
                <a href="voir_planning.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-regular fa-clock"></i></span>
                    <span class="nav-text">Voir le planning</span>
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
                <h1 class="page-title">Marquer absence</h1>
                <p class="page-subtitle">Enregistrez les absences des enfants pour aujourd'hui</p>
            </div>

            <?php if (!empty($errorMessage)): ?>
                <div class="notification error"><?= htmlspecialchars($errorMessage) ?></div>
            <?php endif; ?>
            <?php if (!empty($successMessage)): ?>
                <div class="notification success"><?= htmlspecialchars($successMessage) ?></div>
            <?php endif; ?>

            <div class="dashboard-grid">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Marquer absence</h2>
                        <span class="card-icon"><i class="fa-solid fa-calendar-xmark"></i></span>
                    </div>

                    <form method="post" action="">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Prénom</th>
                                    <th>Absent</th>
                                    <th>Heure début</th>
                                    <th>Heure fin</th>
                                    <th>Justification</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enfants as $enfant): 
                                   
                                    $stmt = $pdo->prepare("SELECT * FROM absences WHERE id_enfant = ? AND date_ = CURDATE()");
                                    $stmt->execute([$enfant['id_enfant']]);
                                    $absence = $stmt->fetch(PDO::FETCH_ASSOC);
                                ?>
                                <tr>
                                    <td data-label="Nom"><?= htmlspecialchars($enfant['nom']) ?></td>
                                    <td data-label="Prénom"><?= htmlspecialchars($enfant['prenom']) ?></td>
                                    <td data-label="Absent" style="text-align:center;">
                                        <input type="checkbox" name="absents[<?= $enfant['id_enfant'] ?>]" id="absent_<?= $enfant['id_enfant'] ?>" <?= $absence ? 'checked' : '' ?> />
                                    </td>
                                    <td data-label="Heure début">
                                        <input type="time" name="heure_debut[<?= $enfant['id_enfant'] ?>]" value="<?= htmlspecialchars($absence['heure_debut'] ?? '') ?>" />
                                    </td>
                                    <td data-label="Heure fin">
                                        <input type="time" name="heure_fin[<?= $enfant['id_enfant'] ?>]" value="<?= htmlspecialchars($absence['heure_fin'] ?? '') ?>" />
                                    </td>
                                    <td data-label="Justification">
                                        <textarea name="justification[<?= $enfant['id_enfant'] ?>]" placeholder="Justification..."><?= htmlspecialchars($absence['justification'] ?? '') ?></textarea>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <button type="submit">Enregistrer les absences</button>
                    </form>
                </div>
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