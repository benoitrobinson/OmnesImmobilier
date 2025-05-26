<?php
require_once '../config/database.php';

// Cette page permet aux utilisateurs de gérer leur compte personnel
// Elle utilise des concepts de programmation orientée objet et de sécurité web

// Vérifier si l'utilisateur est connecté - concept de sécurité fondamental
if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Récupérer les informations complètes de l'utilisateur
// Cette requête utilise une jointure LEFT JOIN pour récupérer les informations d'agent si applicable
$query = "SELECT u.*, a.specialite, a.cv, a.photo, a.experience_annees, a.langues, a.disponible 
          FROM users u 
          LEFT JOIN agents a ON u.id = a.user_id 
          WHERE u.id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Traitement du formulaire de mise à jour du profil
// Cette section illustre la validation côté serveur et la sécurisation des données
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        // Validation et nettoyage des données d'entrée
        $nom = trim($_POST['nom']);
        $prenom = trim($_POST['prenom']);
        $telephone = trim($_POST['telephone']);
        $adresse_ligne1 = trim($_POST['adresse_ligne1']);
        $adresse_ligne2 = trim($_POST['adresse_ligne2']);
        $ville = trim($_POST['ville']);
        $code_postal = trim($_POST['code_postal']);
        $pays = trim($_POST['pays']);
        
        // Validation des champs obligatoires
        if (empty($nom) || empty($prenom)) {
            $error = 'Le nom et le prénom sont obligatoires.';
        } else {
            // Mise à jour des informations utilisateur avec une requête préparée (sécurité)
            $update_query = "UPDATE users SET 
                           nom = :nom, prenom = :prenom, telephone = :telephone,
                           adresse_ligne1 = :adresse_ligne1, adresse_ligne2 = :adresse_ligne2,
                           ville = :ville, code_postal = :code_postal, pays = :pays,
                           updated_at = CURRENT_TIMESTAMP
                           WHERE id = :user_id";
            
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':nom', $nom);
            $update_stmt->bindParam(':prenom', $prenom);
            $update_stmt->bindParam(':telephone', $telephone);
            $update_stmt->bindParam(':adresse_ligne1', $adresse_ligne1);
            $update_stmt->bindParam(':adresse_ligne2', $adresse_ligne2);
            $update_stmt->bindParam(':ville', $ville);
            $update_stmt->bindParam(':code_postal', $code_postal);
            $update_stmt->bindParam(':pays', $pays);
            $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
            
            if ($update_stmt->execute()) {
                // Mise à jour des informations d'agent si l'utilisateur est un agent
                if (isAgent()) {
                    $specialite = trim($_POST['specialite'] ?? '');
                    $cv = trim($_POST['cv'] ?? '');
                    $experience_annees = (int)($_POST['experience_annees'] ?? 0);
                    $langues = trim($_POST['langues'] ?? '');
                    
                    $agent_query = "UPDATE agents SET 
                                  specialite = :specialite, cv = :cv, 
                                  experience_annees = :experience_annees, langues = :langues
                                  WHERE user_id = :user_id";
                    
                    $agent_stmt = $db->prepare($agent_query);
                    $agent_stmt->bindParam(':specialite', $specialite);
                    $agent_stmt->bindParam(':cv', $cv);
                    $agent_stmt->bindParam(':experience_annees', $experience_annees);
                    $agent_stmt->bindParam(':langues', $langues);
                    $agent_stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $agent_stmt->execute();
                }
                
                // Mettre à jour les variables de session
                $_SESSION['nom'] = $nom;
                $_SESSION['prenom'] = $prenom;
                
                $success = 'Profil mis à jour avec succès !';
                
                // Recharger les données utilisateur
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error = 'Erreur lors de la mise à jour du profil.';
            }
        }
    }
    
    // Traitement du changement de mot de passe - sécurité renforcée
    elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Vérifier le mot de passe actuel
        if (!password_verify($current_password, $user['password'])) {
            $error = 'Mot de passe actuel incorrect.';
        } elseif (strlen($new_password) < 6) {
            $error = 'Le nouveau mot de passe doit contenir au moins 6 caractères.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Les nouveaux mots de passe ne correspondent pas.';
        } else {
            // Hasher le nouveau mot de passe avec bcrypt (sécurité)
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $password_query = "UPDATE users SET password = :password WHERE id = :user_id";
            $password_stmt = $db->prepare($password_query);
            $password_stmt->bindParam(':password', $hashed_password);
            $password_stmt->bindParam(':user_id', $_SESSION['user_id']);
            
            if ($password_stmt->execute()) {
                $success = 'Mot de passe modifié avec succès !';
            } else {
                $error = 'Erreur lors du changement de mot de passe.';
            }
        }
    }
}

// Récupérer les statistiques de l'utilisateur selon son type
$stats = [];
if (isClient()) {
    // Statistiques pour les clients : nombre de RDV, propriétés favorites
    $stats_query = "SELECT 
                   (SELECT COUNT(*) FROM rendez_vous WHERE client_id = :user_id) as total_rdv,
                   (SELECT COUNT(*) FROM rendez_vous WHERE client_id = :user_id AND status = 'confirme') as rdv_confirmes";
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
} elseif (isAgent()) {
    // Statistiques pour les agents : propriétés gérées, RDV programmés
    $stats_query = "SELECT 
                   (SELECT COUNT(*) FROM proprietes WHERE agent_id = (SELECT id FROM agents WHERE user_id = :user_id)) as proprietes_gerees,
                   (SELECT COUNT(*) FROM rendez_vous r JOIN agents a ON r.agent_id = a.id WHERE a.user_id = :user_id AND r.status = 'confirme') as rdv_programmes";
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Compte - Omnes Immobilier</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body data-user-logged-in="true">
    <!-- Navigation -->
    <?php include '../includes/navbar.php'; ?>

    <!-- Page Header -->
    <section class="page-header py-5 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <h1 class="display-4 fw-bold">Mon Compte</h1>
                    <p class="lead">Gérez vos informations personnelles et vos préférences</p>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../index.php">Accueil</a></li>
                            <li class="breadcrumb-item active">Mon Compte</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </section>

    <div class="container my-5">
        <!-- Messages d'état -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Sidebar avec navigation -->
            <div class="col-lg-3">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="profile-avatar mb-3">
                            <?php if (isAgent() && !empty($user['photo'])): ?>
                                <img src="../uploads/agents/<?= $user['photo'] ?>" alt="Photo de profil" class="rounded-circle" width="80" height="80">
                            <?php else: ?>
                                <div class="avatar-placeholder rounded-circle mx-auto" style="width: 80px; height: 80px; background: var(--primary-color); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-user fa-2x text-white"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <h5><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></h5>
                        <p class="text-muted small">
                            <?php 
                            switch ($user['type_user']) {
                                case 'admin': echo 'Administrateur'; break;
                                case 'agent': echo 'Agent Immobilier'; break;
                                case 'client': echo 'Client'; break;
                            }
                            ?>
                        </p>
                    </div>
                </div>

                <!-- Statistiques personnelles -->
                <?php if (!empty($stats)): ?>
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Mes Statistiques</h6>
                        </div>
                        <div class="card-body">
                            <?php if (isClient()): ?>
                                <div class="stat-item mb-2">
                                    <div class="d-flex justify-content-between">
                                        <span class="small">Total RDV:</span>
                                        <strong><?= $stats['total_rdv'] ?? 0 ?></strong>
                                    </div>
                                </div>
                                <div class="stat-item">
                                    <div class="d-flex justify-content-between">
                                        <span class="small">RDV confirmés:</span>
                                        <strong><?= $stats['rdv_confirmes'] ?? 0 ?></strong>
                                    </div>
                                </div>
                            <?php elseif (isAgent()): ?>
                                <div class="stat-item mb-2">
                                    <div class="d-flex justify-content-between">
                                        <span class="small">Propriétés gérées:</span>
                                        <strong><?= $stats['proprietes_gerees'] ?? 0 ?></strong>
                                    </div>
                                </div>
                                <div class="stat-item">
                                    <div class="d-flex justify-content-between">
                                        <span class="small">RDV programmés:</span>
                                        <strong><?= $stats['rdv_programmes'] ?? 0 ?></strong>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Navigation rapide -->
                <div class="card mt-3">
                    <div class="card-body">
                        <h6 class="mb-3"><i class="fas fa-compass me-2"></i>Navigation rapide</h6>
                        <div class="d-grid gap-2">
                            <?php if (isClient()): ?>
                                <a href="rendez-vous.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-calendar me-1"></i>Mes RDV
                                </a>
                                <a href="mes-favoris.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-heart me-1"></i>Mes Favoris
                                </a>
                            <?php elseif (isAgent()): ?>
                                <a href="../agent/dashboard.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-chart-bar me-1"></i>Tableau de bord
                                </a>
                                <a href="../agent/schedule.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-calendar-alt me-1"></i>Planning
                                </a>
                            <?php elseif (isAdmin()): ?>
                                <a href="../admin/dashboard.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-cogs me-1"></i>Administration
                                </a>
                            <?php endif; ?>
                            <a href="tout-parcourir.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-building me-1"></i>Propriétés
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contenu principal -->
            <div class="col-lg-9">
                <!-- Onglets de navigation -->
                <nav>
                    <div class="nav nav-tabs" id="nav-tab" role="tablist">
                        <button class="nav-link active" id="nav-profile-tab" data-bs-toggle="tab" data-bs-target="#nav-profile" type="button" role="tab">
                            <i class="fas fa-user me-1"></i>Informations personnelles
                        </button>
                        <button class="nav-link" id="nav-password-tab" data-bs-toggle="tab" data-bs-target="#nav-password" type="button" role="tab">
                            <i class="fas fa-lock me-1"></i>Sécurité
                        </button>
                        <?php if (isAgent()): ?>
                            <button class="nav-link" id="nav-agent-tab" data-bs-toggle="tab" data-bs-target="#nav-agent" type="button" role="tab">
                                <i class="fas fa-user-tie me-1"></i>Profil Agent
                            </button>
                        <?php endif; ?>
                    </div>
                </nav>

                <div class="tab-content" id="nav-tabContent">
                    <!-- Onglet Informations personnelles -->
                    <div class="tab-pane fade show active" id="nav-profile" role="tabpanel">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-4">Informations personnelles</h5>
                                
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="update_profile">
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3">
                                                <input type="text" class="form-control" id="prenom" name="prenom" 
                                                       value="<?= htmlspecialchars($user['prenom']) ?>" required>
                                                <label for="prenom">Prénom *</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3">
                                                <input type="text" class="form-control" id="nom" name="nom" 
                                                       value="<?= htmlspecialchars($user['nom']) ?>" required>
                                                <label for="nom">Nom *</label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-floating mb-3">
                                        <input type="email" class="form-control" id="email" 
                                               value="<?= htmlspecialchars($user['email']) ?>" disabled>
                                        <label for="email">Adresse email (non modifiable)</label>
                                    </div>
                                    
                                    <div class="form-floating mb-3">
                                        <input type="tel" class="form-control" id="telephone" name="telephone" 
                                               value="<?= htmlspecialchars($user['telephone']) ?>">
                                        <label for="telephone">Téléphone</label>
                                    </div>
                                    
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="adresse_ligne1" name="adresse_ligne1" 
                                               value="<?= htmlspecialchars($user['adresse_ligne1']) ?>">
                                        <label for="adresse_ligne1">Adresse ligne 1</label>
                                    </div>
                                    
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="adresse_ligne2" name="adresse_ligne2" 
                                               value="<?= htmlspecialchars($user['adresse_ligne2']) ?>">
                                        <label for="adresse_ligne2">Adresse ligne 2</label>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3">
                                                <input type="text" class="form-control" id="ville" name="ville" 
                                                       value="<?= htmlspecialchars($user['ville']) ?>">
                                                <label for="ville">Ville</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-floating mb-3">
                                                <input type="text" class="form-control" id="code_postal" name="code_postal" 
                                                       value="<?= htmlspecialchars($user['code_postal']) ?>">
                                                <label for="code_postal">Code postal</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-floating mb-3">
                                                <input type="text" class="form-control" id="pays" name="pays" 
                                                       value="<?= htmlspecialchars($user['pays'] ?: 'France') ?>">
                                                <label for="pays">Pays</label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Sauvegarder les modifications
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Onglet Sécurité / Mot de passe -->
                    <div class="tab-pane fade" id="nav-password" role="tabpanel">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-4">Changer le mot de passe</h5>
                                
                                <form method="POST" action="" id="passwordForm">
                                    <input type="hidden" name="action" value="change_password">
                                    
                                    <div class="form-floating mb-3">
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                        <label for="current_password">Mot de passe actuel *</label>
                                    </div>
                                    
                                    <div class="form-floating mb-3">
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                        <label for="new_password">Nouveau mot de passe *</label>
                                        <div id="passwordStrength" class="password-strength mt-1"></div>
                                    </div>
                                    
                                    <div class="form-floating mb-3">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        <label for="confirm_password">Confirmer le nouveau mot de passe *</label>
                                        <div id="passwordMatch" class="password-strength mt-1"></div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-warning">
                                        <i class="fas fa-key me-2"></i>Changer le mot de passe
                                    </button>
                                </form>
                                
                                <!-- Conseils de sécurité -->
                                <div class="mt-4 p-3 bg-light rounded">
                                    <h6><i class="fas fa-shield-alt text-success me-2"></i>Conseils de sécurité</h6>
                                    <ul class="small text-muted mb-0">
                                        <li>Utilisez au moins 8 caractères</li>
                                        <li>Mélangez majuscules, minuscules, chiffres et symboles</li>
                                        <li>Évitez les informations personnelles</li>
                                        <li>Changez régulièrement votre mot de passe</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Onglet Profil Agent (uniquement pour les agents) -->
                    <?php if (isAgent()): ?>
                        <div class="tab-pane fade" id="nav-agent" role="tabpanel">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-4">Profil professionnel d'agent</h5>
                                    
                                    <form method="POST" action="">
                                        <input type="hidden" name="action" value="update_profile">
                                        
                                        <div class="form-floating mb-3">
                                            <input type="text" class="form-control" id="specialite" name="specialite" 
                                                   value="<?= htmlspecialchars($user['specialite']) ?>">
                                            <label for="specialite">Spécialité</label>
                                        </div>
                                        
                                        <div class="form-floating mb-3">
                                            <input type="number" class="form-control" id="experience_annees" name="experience_annees" 
                                                   value="<?= $user['experience_annees'] ?>" min="0" max="50">
                                            <label for="experience_annees">Années d'expérience</label>
                                        </div>
                                        
                                        <div class="form-floating mb-3">
                                            <input type="text" class="form-control" id="langues" name="langues" 
                                                   value="<?= htmlspecialchars($user['langues']) ?>">
                                            <label for="langues">Langues parlées (séparées par des virgules)</label>
                                        </div>
                                        
                                        <div class="form-floating mb-3">
                                            <textarea class="form-control" id="cv" name="cv" style="height: 150px;"><?= htmlspecialchars($user['cv']) ?></textarea>
                                            <label for="cv">Présentation / CV</label>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Sauvegarder le profil agent
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include '../includes/footer.php'; ?>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
    <script src="../assets/js/main.js"></script>

    <script>
        // Script pour la validation du mot de passe en temps réel
        // Cet exemple illustre la programmation événementielle en JavaScript
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('passwordStrength');
            
            if (password.length === 0) {
                strengthDiv.innerHTML = '';
                return;
            }
            
            // Calcul de la force du mot de passe
            let strength = 0;
            let feedback = [];
            
            if (password.length >= 8) strength++;
            else feedback.push('Au moins 8 caractères');
            
            if (/[a-z]/.test(password)) strength++;
            else feedback.push('Une minuscule');
            
            if (/[A-Z]/.test(password)) strength++;
            else feedback.push('Une majuscule');
            
            if (/[0-9]/.test(password)) strength++;
            else feedback.push('Un chiffre');
            
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            else feedback.push('Un caractère spécial');
            
            const levels = ['Très faible', 'Faible', 'Moyen', 'Fort', 'Très fort'];
            const classes = ['text-danger', 'text-danger', 'text-warning', 'text-success', 'text-success'];
            
            strengthDiv.innerHTML = `<small class="${classes[strength - 1] || 'text-danger'}">${levels[strength - 1] || 'Très faible'}</small>`;
            if (feedback.length > 0) {
                strengthDiv.innerHTML += `<br><small class="text-muted">Manque: ${feedback.join(', ')}</small>`;
            }
        });
        
        // Validation de la confirmation du mot de passe
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('new_password').value;
            const confirm = this.value;
            const matchDiv = document.getElementById('passwordMatch');
            
            if (confirm.length === 0) {
                matchDiv.innerHTML = '';
                return;
            }
            
            if (password === confirm) {
                matchDiv.innerHTML = '<small class="text-success"><i class="fas fa-check me-1"></i>Les mots de passe correspondent</small>';
            } else {
                matchDiv.innerHTML = '<small class="text-danger"><i class="fas fa-times me-1"></i>Les mots de passe ne correspondent pas</small>';
            }
        });
    </script>

    <style>
        .password-strength {
            font-size: 0.875rem;
        }
        
        .stat-item {
            padding: 0.25rem 0;
        }
        
        .avatar-placeholder {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
        }
        
        .nav-tabs .nav-link {
            color: var(--gray-600);
            border: none;
            border-bottom: 2px solid transparent;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
            background: none;
        }
        
        .tab-content {
            margin-top: 2rem;
        }
    </style>
</body>
</html>