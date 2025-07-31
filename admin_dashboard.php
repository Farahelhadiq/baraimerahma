<?php
session_start();
require 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: adminlogin.php');
    exit;
}

function supprimerEnfant($id_enfant) {
    if (!is_numeric($id_enfant)) {//id=N
        throw new InvalidArgumentException("ID enfant invalide.");
    }
    $pdo = connectDB();

    $pdo->beginTransaction();

    try {
        $stmtAbsences = $pdo->prepare("DELETE FROM absences WHERE id_enfant = ?");
        $stmtAbsences->execute([$id_enfant]);

        $stmtEnfantGroupe = $pdo->prepare("DELETE FROM Enfant_groupe WHERE id_enfant = ?");
        $stmtEnfantGroupe->execute([$id_enfant]);

        $stmt = $pdo->prepare("DELETE FROM enfants WHERE id_enfant = ?");
        $stmt->execute([$id_enfant]);

        $pdo->commit();// حفظ جميع العمليات
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();//hiya f katssta3mal bax tlghy tous les opération transaction
        throw new RuntimeException("Erreur lors de la suppression : " . $e->getMessage());
    }
}


function supprimerProfesseur($id_professeur) {
    if (!is_numeric($id_professeur)) {
        throw new InvalidArgumentException("ID professeur invalide.");
    }
    $pdo = connectDB();

    $pdo->beginTransaction();//tous opération

    try {
        $stmtProfGroupe = $pdo->prepare("DELETE FROM professeurs_groupes WHERE id_professeur = ?");
        $stmtProfGroupe->execute([$id_professeur]);

    
        $stmt = $pdo->prepare("DELETE FROM professeur WHERE id_professeur = ?");
        $stmt->execute([$id_professeur]);

        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        throw new RuntimeException("Erreur lors de la suppression : " . $e->getMessage());
    }
}

if (isset($_GET['delete_enfant_id'])) {
    $idToDelete = (int)$_GET['delete_enfant_id'];
    try {
        supprimerEnfant($idToDelete);
        header("Location: admin_dashboard.php?msg=child_deleted");
        exit;
    } catch (Exception $e) {
        $errorDelete = $e->getMessage();
    }
}

if (isset($_GET['delete_prof_id'])) {
    $idToDelete = (int)$_GET['delete_prof_id'];//int=entier
    try {
        supprimerProfesseur($idToDelete);
        header("Location: admin_dashboard.php?msg=prof_deleted");
        exit;
    } catch (Exception $e) {
        $errorDelete = $e->getMessage();
    }
}

try {
    $pdo = connectDB();

    // Enfants avec groupe (année scolaire courante)
    $sqlEnfants = "
        SELECT e.id_enfant, e.nom, e.prenom, e.genre, e.photo, e.date_naissance,
               g.nom_groupe,
               p.nom AS nom_parent, p.prenom AS prenom_parent
        FROM enfants e
        LEFT JOIN Enfant_groupe eg ON e.id_enfant = eg.id_enfant AND eg.annee_scolaire = YEAR(CURDATE())
        LEFT JOIN groupes g ON eg.id_groupe = g.id_groupe
        LEFT JOIN parent p ON e.id_parent = p.id_parent
        ORDER BY e.nom, e.prenom
    ";//enfant d'année scollaire
    $stmtEnfants = $pdo->query($sqlEnfants);
    $enfants = $stmtEnfants->fetchAll(PDO::FETCH_ASSOC);

    // Absences
    $sqlAbsences = "
        SELECT a.id_absence, a.date_, a.heure_debut, a.heure_fin, a.justification,
               e.nom AS nom_enfant, e.prenom AS prenom_enfant
        FROM absences a
        LEFT JOIN enfants e ON a.id_enfant = e.id_enfant
        ORDER BY a.date_ DESC, e.nom, e.prenom
    ";
    $stmtAbsences = $pdo->query($sqlAbsences);
    $absences = $stmtAbsences->fetchAll(PDO::FETCH_ASSOC);

    // Professeurs
    $sqlProfesseurs = "SELECT * FROM professeur";
    $stmtProfesseurs = $pdo->query($sqlProfesseurs);
    $professeurs = $stmtProfesseurs->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erreur base de données : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Tableau de bord Administrateur</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
<link rel="stylesheet" href="admin_dashboard.css">
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
                <a href="admin_dashboard.php" class="nav-item active">
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
                <h1 class="page-title">Bienvenue, <?= htmlspecialchars($_SESSION['admin_prenom']) ?></h1>
                <p class="page-subtitle">Gérez les enfants, professeurs et planning</p>
            </div>

            <?php if (isset($_GET['msg'])): ?>
                <?php if ($_GET['msg'] === 'child_deleted'): ?>
                    <div class="notification success">Enfant supprimé avec succès.</div>
                <?php elseif ($_GET['msg'] === 'prof_deleted'): ?>
                    <div class="notification success">Professeur supprimé avec succès.</div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!empty($errorDelete)): ?>
                <div class="notification error">Erreur lors de la suppression : <?= htmlspecialchars($errorDelete) ?></div>
            <?php endif; ?>

            <div class="dashboard-grid">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Profils des enfants</h2>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Nom</th>
                                <th>Prénom</th>
                                <th>Genre</th>
                                <th>Date de naissance</th>
                                <th>Groupe</th>
                                <th>Parent</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($enfants)): ?>
                                <tr><td colspan="8">Aucun enfant trouvé.</td></tr>
                            <?php else: ?>
                                <?php foreach ($enfants as $enfant): ?>
                                    <tr>
                                        <td>
                                            <?php if ($enfant['photo']): ?>
                                                <img src="<?= htmlspecialchars($enfant['photo']) ?>" alt="Photo de <?= htmlspecialchars($enfant['prenom']) ?>" width="60">
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($enfant['nom']) ?></td>
                                        <td><?= htmlspecialchars($enfant['prenom']) ?></td>
                                        <td><?= htmlspecialchars($enfant['genre']) ?></td>
                                        <td><?= htmlspecialchars($enfant['date_naissance']) ?></td>
                                        <td><?= htmlspecialchars($enfant['nom_groupe'] ?? 'Non défini') ?></td>
                                        <td><?= htmlspecialchars(trim($enfant['prenom_parent'] . ' ' . $enfant['nom_parent'])) ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="modifier_enfant.php?id=<?= $enfant['id_enfant'] ?>">
                                                    <button class="edit-btn"><i class="fa-solid fa-pen"></i></button>
                                                </a>
                                                <a href="admin_dashboard.php?delete_enfant_id=<?= $enfant['id_enfant'] ?>" onclick="return confirm('Confirmer la suppression ?');">
                                                   <button class="delete-btn"><i class="fa-solid fa-trash-can"></i></button> <!-- aleret  -->
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Absences des enfants</h2>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Heure début</th>
                                <th>Heure fin</th>
                                <th>Enfant</th>
                                <th>Justification</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($absences)): ?>
                                <tr><td colspan="5">Aucune absence enregistrée.</td></tr>
                            <?php else: ?>
                                <?php foreach ($absences as $absence): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($absence['date_']) ?></td>
                                        <td><?= htmlspecialchars($absence['heure_debut']) ?></td>
                                        <td><?= htmlspecialchars($absence['heure_fin']) ?></td>
                                        <td><?= htmlspecialchars($absence['prenom_enfant'] . ' ' . $absence['nom_enfant']) ?></td>
                                        <td><?= htmlspecialchars($absence['justification']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Gestion des professeurs</h2>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Prénom</th>
                                <th>Email</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($professeurs)): ?>
                                <tr><td colspan="4">Aucun professeur trouvé.</td></tr>
                            <?php else: ?>
                                <?php foreach ($professeurs as $prof): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($prof['nom']) ?></td>
                                        <td><?= htmlspecialchars($prof['prenom']) ?></td>
                                        <td><?= htmlspecialchars($prof['email']) ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="modifier_professeur.php?id=<?= $prof['id_professeur'] ?>">
                                                    <button class="edit-btn"><i class="fa-solid fa-pen"></i></button>
                                                </a>
                                                <a href="admin_dashboard.php?delete_prof_id=<?= $prof['id_professeur'] ?>" onclick="return confirm('Supprimer ce professeur ?');">
                                                    <button class="delete-btn"><i class="fa-solid fa-trash-can"></i></button>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
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