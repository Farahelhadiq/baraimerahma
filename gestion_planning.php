<?php
session_start();
require_once 'config.php';
if (!isset($_SESSION['admin_id'])) {
    header('Location: adminlogin.php');
    exit;
}

try {
    $pdo = connectDB();
    $startDate = new DateTime('monday this week');
    $days = [];
    for ($i = 0; $i < 5; $i++) {
        $days[] = (clone $startDate)->modify("+$i day");
    }
    $sql = "
        SELECT ga.date_d_activite, a.id_activite, a.nom_activite, a.description,
               g.id_groupe, g.nom_groupe
        FROM groupes_activites ga
        JOIN activite a ON ga.id_activite = a.id_activite
        JOIN groupes g ON ga.id_groupe = g.id_groupe
        WHERE DATE(ga.date_d_activite) BETWEEN :start_date AND :end_date
        ORDER BY ga.date_d_activite, a.nom_activite
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':start_date' => $days[0]->format('Y-m-d'),
        ':end_date' => $days[4]->format('Y-m-d'),
    ]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $activitiesByDate = [];
    foreach ($results as $row) {
        $date = (new DateTime($row['date_d_activite']))->format('Y-m-d');
        if (!isset($activitiesByDate[$date])) {
            $activitiesByDate[$date] = [];
        }
        $activitiesByDate[$date][] = $row;
    }

} catch (PDOException $e) {
    die("Erreur base de données : " . htmlspecialchars($e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion du planning hebdomadaire</title>
   <link rel="stylesheet" href="gestion_planning.css">
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
                <a href="gestion_planning.php" class="nav-item active">
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
                <h1 class="page-title">Planning hebdomadaire</h1>
                <p class="page-subtitle">Semaine du <?= htmlspecialchars($days[0]->format('d/m/Y')) ?> au <?= htmlspecialchars($days[4]->format('d/m/Y')) ?></p>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Activités par jour</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <?php foreach ($days as $day): ?>
                                <th><?= htmlspecialchars($day->format('l d/m/Y')) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <?php foreach ($days as $day): 
                                $dateStr = $day->format('Y-m-d');
                                ?>
                                <td>
                                    <strong><?= htmlspecialchars($day->format('d/m/Y')) ?></strong>
                                    <?php if (isset($activitiesByDate[$dateStr])): ?>
                                        <ul class="activity-list">
                                            <?php foreach ($activitiesByDate[$dateStr] as $activity): ?>
                                                <li class="activity-item">
                                                    <div class="activity-title"><?= htmlspecialchars($activity['nom_activite']) ?></div>
                                                    <div class="activity-group">Groupe: <?= htmlspecialchars($activity['nom_groupe']) ?></div>
                                                    <div class="activity-time">Heure: <?= htmlspecialchars((new DateTime($activity['date_d_activite']))->format('H:i')) ?></div>
                                                    <div class="activity-description"><?= nl2br(htmlspecialchars($activity['description'] ?? 'Aucune description')) ?></div>
                                                    <div class="activity-actions">
                                                        <a href="modifier_activite.php?id_activite=<?= urlencode($activity['id_activite']) ?>&date=<?= urlencode($activity['date_d_activite']) ?>&id_groupe=<?= urlencode($activity['id_groupe']) ?>" 
                                                           class="action-button modifier">Modifier</a>
                                                        <a href="supprimer_activite.php?id_activite=<?= urlencode($activity['id_activite']) ?>&date=<?= urlencode($activity['date_d_activite']) ?>&id_groupe=<?= urlencode($activity['id_groupe']) ?>" 
                                                           onclick="return confirm('Confirmer la suppression de cette activité ?');" 
                                                           class="action-button supprimer">Supprimer</a>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <div class="no-activities">Aucune activité prévue.</div>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
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