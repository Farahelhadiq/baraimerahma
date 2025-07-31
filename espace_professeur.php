<?php
session_start();
require 'config.php';

if (!isset($_SESSION['id_professeur'])) {
    header("Location: login_professeur.php");
    exit();
}

$id_professeur = $_SESSION['id_professeur'];
$pdo = connectDB();

$stmt = $pdo->prepare("SELECT id_groupe FROM professeurs_groupes WHERE id_professeur = ? ORDER BY annee_scolaire DESC LIMIT 1");
$stmt->execute([$id_professeur]);
$groupe = $stmt->fetch();
$id_groupe = $groupe ? $groupe['id_groupe'] : null;//ila kan id group kayn rah howa lvaleur oila makanx rah null

$nb_total_enfants = 0;
$nb_enfants_presents = 0;
$taux_presence = 0;
$arrivees = [];
$activites_du_jour = [];

if ($id_groupe) {

    $stmt = $pdo->prepare("
        SELECT e.* 
        FROM enfants e
        JOIN Enfant_groupe eg ON e.id_enfant = eg.id_enfant
        WHERE eg.id_groupe = ?
    ");
    $stmt->execute([$id_groupe]);
    $enfants = $stmt->fetchAll();
    $nb_total_enfants = count($enfants);

    $stmt = $pdo->prepare("SELECT id_enfant FROM absences WHERE date_ = CURDATE()");
    $stmt->execute();
    $absents = $stmt->fetchAll(PDO::FETCH_COLUMN);//array id d'enfant absent aujourd'hui

    $nb_enfants_presents = $nb_total_enfants - count(array_intersect(array_column($enfants, 'id_enfant'), $absents));//array_column($enfants, 'id_enfant')=colon id-enfant mn enfant katakhod 2 array okatrja3 les élément mxtarakin
    $taux_presence = $nb_total_enfants > 0 ? round(($nb_enfants_presents / $nb_total_enfants) * 100, 2) : 0;

    $stmt = $pdo->prepare("
        SELECT e.nom, e.prenom, e.photo 
        FROM enfants e
        JOIN Enfant_groupe eg ON e.id_enfant = eg.id_enfant
        WHERE eg.id_groupe = ?
        AND e.id_enfant NOT IN (
            SELECT id_enfant FROM absences WHERE date_ = CURDATE()
        ) 
    ");//NOT IN=tajahal
    $stmt->execute([$id_groupe]);
    $arrivees = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT a.nom_activite, ga.date_d_activite 
        FROM groupes_activites ga
        JOIN activite a ON ga.id_activite = a.id_activite
        WHERE ga.id_groupe = ? AND DATE(ga.date_d_activite) = CURDATE()
    ");//les activité d'aujourd'hui 
    $stmt->execute([$id_groupe]);
    $activites_du_jour = $stmt->fetchAll();
}

function getStatutActivite($date) {
    $aujourdhui = date('Y-m-d');//2025-06-23
    $date_activite = date('Y-m-d', strtotime($date));//2025-06-23 kathawal la form
    if ($date_activite == $aujourdhui) return "En cours";
    elseif ($date_activite > $aujourdhui) return "À venir";
    else return "Planifiée";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Espace Professeur</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
     <link rel="stylesheet" href="espace_professeur.css">
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
                <h1 class="page-title">Bienvenue, <?= htmlspecialchars($_SESSION['prenom']) ?> <?= htmlspecialchars($_SESSION['nom']) ?></h1>
                <p class="page-subtitle">Gérez les présences et le planning de votre groupe</p>
            </div>

            <div class="dashboard-grid">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Statistiques du jour</h2>
                        <span class="card-icon"></span>
                    </div>
                    <div class="details-grid">
                        <div class="detail-item">
                            <span class="detail-label">Enfants présents</span>
                            <span class="detail-value"><?= $nb_enfants_presents ?> / <?= $nb_total_enfants ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Taux de présence</span>
                            <span class="detail-value"><?= $taux_presence ?>%</span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Activités du jour</h2>
                       
                    </div>
                    <?php if ($activites_du_jour): ?>
                        <ul class="activity-list">
                            <?php foreach ($activites_du_jour as $a): ?>
                                <li><?= htmlspecialchars($a['nom_activite']) ?> – <em><?= getStatutActivite($a['date_d_activite']) ?></em></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>Aucune activité pour aujourd'hui.</p>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Les enfants présents aujourd'hui</h2>
                        
                    </div>
                    <?php if ($arrivees): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Photo</th>
                                    <th>Nom</th>
                                    <th>Prénom</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($arrivees as $enfant): ?>
                                    <tr>
                                        <td><img src="<?= htmlspecialchars($enfant['photo']) ?>" alt="Photo de <?= htmlspecialchars($enfant['prenom']) ?>"></td>
                                        <td><?= htmlspecialchars($enfant['nom']) ?></td>
                                        <td><?= htmlspecialchars($enfant['prenom']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>Aucune arrivée enregistrée aujourd'hui.</p>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Actions rapides</h2>
                    </div>
                    <button onclick="location.href='marquer_presences.php'">Marquer absence</button>
                    <button onclick="location.href='voir_planning.php'">Voir le planning</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');//navmenu
            const mainContent = document.querySelector('.main-content');//contenu page 
            const toggleBtn = document.querySelector('.toggle-btn');//flésh
            const showSidebarBtn = document.querySelector('.show-sidebar-btn');//☰

            sidebar.classList.toggle('hidden');
            mainContent.classList.toggle('full-width');
            toggleBtn.classList.toggle('hidden');
            showSidebarBtn.classList.toggle('visible');
        }

        function updateHeure() {
            const now = new Date();
            const heure = now.getHours().toString().padStart(2, '0');
            const minutes = now.getMinutes().toString().padStart(2, '0');
            const secondes = now.getSeconds().toString().padStart(2, '0');
            const heureStr = `${heure}:${minutes}:${secondes}`;
            document.getElementById('heureActuelleStats').textContent = heureStr;
        }

        setInterval(updateHeure, 1000);
        updateHeure();
    </script>
</body>
</html>