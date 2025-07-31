<?php
session_start();
require 'config.php';

if (!isset($_SESSION['id_professeur'])) {
    header("Location: login_professeur.php");
    exit();
}

$pdo = connectDB();
$id_professeur = $_SESSION['id_professeur'];

$stmtYear = $pdo->prepare("SELECT annee_scolaire FROM professeurs_groupes WHERE id_professeur = ? ORDER BY annee_scolaire DESC LIMIT 1");
$stmtYear->execute([$id_professeur]);
$annee_scolaire = $stmtYear->fetchColumn();

if (!$annee_scolaire) {
    die("Aucune année scolaire trouvée pour ce professeur.");
}

$stmtGroupes = $pdo->prepare("
    SELECT g.id_groupe, g.nom_groupe 
    FROM professeurs_groupes pg 
    JOIN groupes g ON pg.id_groupe = g.id_groupe 
    WHERE pg.id_professeur = ? AND pg.annee_scolaire = ?
");
$stmtGroupes->execute([$id_professeur, $annee_scolaire]);
$groupes = $stmtGroupes->fetchAll();

if (!$groupes) {
    die("Aucun groupe trouvé pour ce professeur cette année scolaire.");
}

function getMondayDate($date) {
    $dt = new DateTime($date);// date aujourd'hui 
    $dt->modify('Monday this week');//date de lundi de la semaine
    return $dt->format('Y-m-d');//la format de la date
}

$monday = getMondayDate(date('Y-m-d'));//la date de lundi de la semaine
$startDate = $monday;
$endDate = (new DateTime($monday))->modify('+6 days')->format('Y-m-d');//la date de dimanche de la semaine (lundi + 6 jours )

$activities = [];

foreach ($groupes as $groupe) {
    $stmtActivities = $pdo->prepare("
        SELECT DATE(ga.date_d_activite) AS date_activite, a.nom_activite, a.description, g.nom_groupe 
        FROM groupes_activites ga 
        JOIN activite a ON ga.id_activite = a.id_activite 
        JOIN groupes g ON ga.id_groupe = g.id_groupe
        WHERE ga.id_groupe = ? AND DATE(ga.date_d_activite) BETWEEN ? AND ?
        ORDER BY ga.date_d_activite
    ");
    $stmtActivities->execute([$groupe['id_groupe'], $startDate, $endDate]);
    $result = $stmtActivities->fetchAll();

    foreach ($result as $act) {
        $activities[$act['date_activite']][] = $act;
    }
}

$days = [];
for ($i = 0; $i < 7; $i++) {
    $dayDate = (new DateTime($monday))->modify("+$i days")->format('Y-m-d');
    $days[$dayDate] = $activities[$dayDate] ?? [];
}

$joursFrancais = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Planning Hebdomadaire</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
   <link rel="stylesheet" href="voir_planning.css">
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
                <h1 class="page-title">Planning Hebdomadaire</h1>
                <p class="page-subtitle">Consultez les activités prévues pour votre groupe</p>
            </div>

            <div class="dashboard-grid">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Activités de la semaine</h2>
                       
                    </div>
                    <div class="week-info">
                        Semaine du <?= (new DateTime($monday))->format('d/m/Y') ?> au <?= (new DateTime($monday))->modify('+6 days')->format('d/m/Y') ?>
                    </div>
                    <?php 
                    $i = 0;
                    foreach ($days as $date => $acts): ?>
                        <div class="day-block">
                            <div class="day-header"><?= $joursFrancais[$i++] ?> - <?= (new DateTime($date))->format('d/m/Y') ?></div>
                            <?php if (count($acts) === 0): ?>
                                <p class="no-activity">Aucune activité prévue.</p>
                            <?php else: ?>
                                <?php foreach ($acts as $act): ?>
                                    <div class="activity">
                                        <div class="groupe-name">Groupe : <?= htmlspecialchars($act['nom_groupe']) ?></div>
                                        <h4><?= htmlspecialchars($act['nom_activite']) ?></h4>
                                        <p><?= nl2br(htmlspecialchars($act['description'])) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
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