<?php
session_start();
require 'config.php';
if (!isset($_SESSION['id_parent'])) {
    header("Location: loginparent.php");
    exit();
}

$pdo = connectDB();
$id_parent = $_SESSION['id_parent'];
$email = $_SESSION['email'];
$stmt = $pdo->prepare("SELECT nom, prenom FROM parent WHERE id_parent = ?");
$stmt->execute([$id_parent]);
$parent = $stmt->fetch(PDO::FETCH_ASSOC);
$nom = $parent['nom'];
$prenom = $parent['prenom'];
$stmt = $pdo->prepare("
    SELECT 
        e.id_enfant,
        e.nom, 
        e.prenom, 
        e.photo, 
        e.date_naissance, 
        g.nom_groupe, 
        g.id_groupe,
        eg.annee_scolaire
    FROM enfants e
    LEFT JOIN Enfant_groupe eg 
        ON e.id_enfant = eg.id_enfant 
        AND eg.annee_scolaire = (
            SELECT MAX(annee_scolaire) 
            FROM Enfant_groupe 
            WHERE id_enfant = e.id_enfant
        )
    LEFT JOIN groupes g 
        ON eg.id_groupe = g.id_groupe
    WHERE e.id_parent = ?
    LIMIT 1
");//bax nxofo akhir année 9ra fiha  SELECT MAX(annee_scolaire) LIMIT 1 bax nakhdo ghir awal enfant l9ina  AND bax yrja3 annee mtloba
$stmt->execute([$id_parent]);
$child = $stmt->fetch(PDO::FETCH_ASSOC);
$educatrice_nom = null;
$educatrice_prenom = null;

if ($child && !empty($child['id_groupe']) && !empty($child['annee_scolaire'])) {
    $stmt = $pdo->prepare("
        SELECT 
            p.nom AS educatrice_nom, 
            p.prenom AS educatrice_prenom
        FROM professeurs_groupes pg 
        JOIN professeur p ON pg.id_professeur = p.id_professeur
        WHERE pg.id_groupe = ? 
        AND pg.annee_scolaire = ?
        LIMIT 1
    ");
    $stmt->execute([$child['id_groupe'], $child['annee_scolaire']]);
    $educatrice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($educatrice) {
        $educatrice_nom = $educatrice['educatrice_nom'];
        $educatrice_prenom = $educatrice['educatrice_prenom'];
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                p.nom AS educatrice_nom, 
                p.prenom AS educatrice_prenom
            FROM professeurs_groupes pg 
            JOIN professeur p ON pg.id_professeur = p.id_professeur
            WHERE pg.id_groupe = ?
            ORDER BY pg.annee_scolaire DESC
            LIMIT 1
        ");//année li9bal
        $stmt->execute([$child['id_groupe']]);
        $educatrice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($educatrice) {
            $educatrice_nom = $educatrice['educatrice_nom'];
            $educatrice_prenom = $educatrice['educatrice_prenom'];
        }
    }
}
if ($child && (empty($child['date_naissance']) || $child['date_naissance'] == '0000-00-00')) {
    $child['date_naissance'] = null;
}
$age = null;
if ($child && !empty($child['date_naissance'])) {
    $dob = new DateTime($child['date_naissance']);//kanakhdo date de naissance
    $now = new DateTime();
    $age = $now->diff($dob)->y;//année dyal diférent 
}

$next_activity_name = "Aucune activité";
$next_activity_time = "";
if (!empty($child['id_groupe'])) {
    $stmt = $pdo->prepare("
        SELECT a.nom_activite, ga.date_d_activite
        FROM groupes_activites ga
        JOIN activite a ON ga.id_activite = a.id_activite
        WHERE ga.id_groupe = ? AND ga.date_d_activite >= CURDATE()
        ORDER BY ga.date_d_activite ASC
        LIMIT 1
    ");//la prochaine activité Croissant=ASC
    $stmt->execute([$child['id_groupe']]);
    $next_activity = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($next_activity) {
        $next_activity_name = $next_activity['nom_activite'];
        $next_activity_time = (new DateTime($next_activity['date_d_activite']))->format('H:i');//heur et minute 
    }
}

$today_activities = [];
if (!empty($child['id_groupe'])) {
    $stmt = $pdo->prepare("
        SELECT a.nom_activite, ga.date_d_activite
        FROM groupes_activites ga
        JOIN activite a ON ga.id_activite = a.id_activite
        WHERE ga.id_groupe = ? AND DATE(ga.date_d_activite) = CURDATE()
        ORDER BY ga.date_d_activite ASC
    ");//tous les activité d'aujourd'hui
    $stmt->execute([$child['id_groupe']]);
    $today_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$absences = [];
if (!empty($child['id_enfant'])) {
    $stmt = $pdo->prepare("
        SELECT date_, justification AS raison, heure_debut, heure_fin
        FROM absences
        WHERE id_enfant = ?
        AND date_ BETWEEN DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) 
        AND DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 6 DAY)
        ORDER BY date_, heure_debut
    ");//1 lundi 2 dimanche
    $stmt->execute([$child['id_enfant']]);
    $absences = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Espace Parent - Tableau de bord</title>
    <link rel="stylesheet" href="espaceparent.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body>
    <button class="show-sidebar-btn" id="show-sidebar-btn">☰</button>
    <div class="app-container">
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header" id="sidebar-header">
                <h1 class="sidebar-title">Espace Parent</h1>
                <button class="toggle-btn" id="toggle-btn">➔</button>
                <div class="user-info">
                    <div class="user-avatar">
                         <?php echo strtoupper(substr($email, 0, 2)); ?><!--//AM -->
                    </div>
                    <div class="user-details">
                        <?php if (!empty($prenom) && !empty($nom)): ?>
                            <h4><?php echo htmlspecialchars($prenom . ' ' . $nom); ?></h4>
                        <?php endif; ?>
                        <p><?php echo htmlspecialchars($email); ?></p>
                    </div>
                </div>
            </div>

            <div class="nav-menu" id="nav-menu">
                <a href="#" class="nav-item active">
                    <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                    <span class="nav-text">Tableau de bord</span>
                </a>
            </div>

            <div class="logout-section" id="logout-section">
                <a href="logout.php" class="logout-btn">
                    <span class="nav-icon"><i class="fa-solid fa-right-from-bracket"></i></span>
                    <span>Déconnexion</span>
                </a>
            </div>
        </nav>

        <main class="main-content" id="main-content">
            <div class="page-header">
                <h1 class="page-title">Tableau de bord</h1>
                <p class="page-subtitle">Consultez toutes les informations concernant votre enfant et suivez son parcours éducatif.</p>
            </div>

            <div class="dashboard-grid">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Profil de l'enfant</h2>
                    </div>
                    
                    <div class="child-profile">
                        <div class="child-avatar">
                            <img src="<?php echo htmlspecialchars($child['photo'] ?: 'https://via.placeholder.com/100x100/4f46e5/ffffff?text=Photo'); ?>" alt="Photo de l'enfant" />
                        </div>
                        
                        <div class="child-details">
                            <h3 class="child-name"><?php echo htmlspecialchars($child['nom'] . ' ' . $child['prenom']); ?></h3>
                            
                            <div class="details-grid">
                                <div class="detail-item">
                                    <span class="detail-label">Âge</span>
                                    <span class="detail-value"><?php echo $age !== null ? $age . ' ans' : 'Non disponible'; ?></span>
                                </div>
                                
                                <div class="detail-item">
                                    <span class="detail-label">Date de naissance</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($child['date_naissance'] ?: 'Non disponible'); ?></span>
                                </div>
                                
                                <div class="detail-item">
                                    <span class="detail-label">Groupe</span>
                                    <span class="detail-value">
                                        <?php if ($child['nom_groupe']): ?>
                                            <span class="group-badge">
                                                ⭐ <?php echo htmlspecialchars($child['nom_groupe']); ?>
                                            </span>
                                        <?php else: ?>
                                            Non attribué
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="educator-card">
                                <div>
                                    <div class="educator-label">Éducatrice responsable</div>
                                    <div class="educator-name">
                                        <?php 
                                        if ($educatrice_prenom && $educatrice_nom) {
                                            echo htmlspecialchars($educatrice_prenom . ' ' . $educatrice_nom);
                                        } else {
                                            echo 'Non attribuée';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="activity-card">
                                <div class="activity-label">Prochaine activité</div>
                                <div class="activity-name"><?php echo htmlspecialchars($next_activity_name); ?></div>
                                <?php if ($next_activity_time): ?>
                                    <div class="activity-time"><?php echo htmlspecialchars($next_activity_time); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bottom-grid">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Événements à venir</h2>
                    </div>
                    
                    <div class="events-list">
                        <?php if (!empty($today_activities)): ?>
                            <?php foreach ($today_activities as $activity): ?>
                                <div class="event-item">
                                    <div class="event-header">
                                        <span class="event-title"><?php echo htmlspecialchars($activity['nom_activite']); ?></span>
                                        <span class="event-date"><?php echo (new DateTime($activity['date_d_activite']))->format('d M'); ?></span>
                                    </div>
                                    <div class="event-date"><?php echo (new DateTime($activity['date_d_activite']))->format('H:i'); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="event-item">
                                <div class="event-title">Aucun événement aujourd'hui</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Absences cette semaine</h2>
                    </div>

                    <div class="card-subtitle">
                        <small>
                            Du <strong><?php echo date('d M Y', strtotime('monday this week')); ?></strong>
                            au <strong><?php echo date('d M Y', strtotime('sunday this week')); ?></strong>
                        </small>
                    </div>

                    <div class="messages-list">
                        <?php if (!empty($absences)): ?>
                            <?php foreach ($absences as $absence): ?>
                                <div class="message-item">
                                    <div class="message-header">
                                        <span class="message-sender">Absence</span>
                                        <span class="message-time"><?php echo (new DateTime($absence['date_']))->format('d M Y'); ?></span>
                                    </div>
                                    <div class="message-preview">
                                        <?php if (!empty($absence['heure_debut']) && !empty($absence['heure_fin'])): ?>
                                            Heure : <?php echo substr($absence['heure_debut'], 0, 5); ?> - <?php echo substr($absence['heure_fin'], 0, 5); ?><br>
                                        <?php else: ?>
                                            Heure : Non spécifiée<br>
                                        <?php endif; ?>
                                        Date : <?php echo (new DateTime($absence['date_']))->format('d M Y'); ?><br>
                                        Raison : <?php echo htmlspecialchars($absence['raison'] ?: 'Aucune raison précisée'); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="message-item">
                                <div class="message-title">Aucune absence cette semaine</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
<script>
   const sidebar = document.getElementById('sidebar');//nav
const toggleBtn = document.getElementById('toggle-btn');//nav button 
const showSidebarBtn = document.getElementById('show-sidebar-btn');//☰
const mainContent = document.getElementById('main-content');//content
const sidebarHeader = document.getElementById('sidebar-header');//partier 1 mn nav email ...
const navMenu = document.getElementById('nav-menu');//house menu
const logoutSection = document.getElementById('logout-section');//déconnecter
function toggleSidebar() {
    sidebar.classList.toggle('hidden');//hidden = ikhfae 
    toggleBtn.classList.toggle('hidden');
    mainContent.classList.toggle('full-width');//contenent yrja3 normal 
    showSidebarBtn.classList.toggle('visible');
     navMenu.classList.toggle('hidden');
        logoutSection.classList.toggle('hidden');
}

 function adjustLayout() {
    if (window.innerWidth > 768 ) {//hna katbdal ila kna dija tala3 sidebar kathaydo 
        sidebar.classList.remove('hidden');//affichage nav 
        mainContent.classList.remove('full-width');//width 
        showSidebarBtn.classList.remove('visible');//hidden ☰
        navMenu.classList.add('visible');//affichage navmenu 
        logoutSection.classList.add('visible');//affichage logout
        toggleBtn.classList.remove('hidden');//affichage nav button
    }else {
        sidebar.classList.add('hidden');
        mainContent.classList.add('full-width');
        showSidebarBtn.classList.add('visible');
        navMenu.classList.add('visible');
        logoutSection.classList.add('visible');
        toggleBtn.classList.add('hidden');
    }
}

window.addEventListener('resize', adjustLayout);//tsghir wla tkbira l'écran
window.addEventListener('load', adjustLayout);//tahmill lcontenu 


toggleBtn.addEventListener('click', toggleSidebar);
showSidebarBtn.addEventListener('click', toggleSidebar);
</script>
</body>
</html>