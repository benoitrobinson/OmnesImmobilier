<?php
require_once '../config/database.php';

// Rediriger si déjà connecté
if (isLoggedIn()) {
    redirect('../index.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    $telephone = trim($_POST['telephone']);
    $type_user = $_POST['type_user'] ?? 'client';
    
    // Validation
    if (empty($nom) || empty($prenom) || empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';
    } elseif (strlen($password) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères.';
    } elseif ($password !== $password_confirm) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        // Vérifier si l'email existe déjà
        $check_query = "SELECT id FROM users WHERE email = :email";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':email', $email);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            $error = 'Cette adresse email est déjà utilisée.';
        } else {
            // Créer le compte
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $insert_query = "INSERT INTO users (nom, prenom, email, password, telephone, type_user) 
                           VALUES (:nom, :prenom, :email, :password, :telephone, :type_user)";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':nom', $nom);
            $insert_stmt->bindParam(':prenom', $prenom);
            $insert_stmt->bindParam(':email', $email);
            $insert_stmt->bindParam(':password', $hashed_password);
            $insert_stmt->bindParam(':telephone', $telephone);
            $insert_stmt->bindParam(':type_user', $type_user);
            
            if ($insert_stmt->execute()) {
                // Si c'est un agent, créer l'entrée dans la table agents
                if ($type_user === 'agent') {
                    $user_id = $db->lastInsertId();
                    $agent_query = "INSERT INTO agents (user_id, disponible) VALUES (:user_id, 1)";
                    $agent_stmt = $db->prepare($agent_query);
                    $agent_stmt->bindParam(':user_id', $user_id);
                    $agent_stmt->execute();
                }
                
                $success = 'Compte créé avec succès ! Vous pouvez maintenant vous connecter.';
            } else {
                $error = 'Erreur lors de la création du compte. Veuillez réessayer.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - Omnes Immobilier</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">
    
    <style>
        .auth-container {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 0;
        }
        
        .auth-card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 3rem;
            width: 100%;
            max-width: 500px;
        }
        
        .auth-logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .auth-logo h1 {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .form-floating {
            margin-bottom: 1.5rem;
        }
        
        .auth-links {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .auth-links a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .auth-links a:hover {
            text-decoration: underline;
        }
        
        .password-strength {
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }
        
        .strength-weak { color: var(--danger-color); }
        .strength-medium { color: var(--warning-color); }
        .strength-strong { color: var(--success-color); }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-logo">
                <h1><i class="fas fa-building me-2"></i>Omnes Immobilier</h1>
                <p class="text-muted">Créez votre compte</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                    <div class="mt-2">
                        <a href="login.php" class="btn btn-success btn-sm">Se connecter maintenant</a>
                    </div>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="registerForm">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="prenom" name="prenom" placeholder="Prénom" required value="<?= htmlspecialchars($_POST['prenom'] ?? '') ?>">
                            <label for="prenom"><i class="fas fa-user me-2"></i>Prénom *</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="nom" name="nom" placeholder="Nom" required value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>">
                            <label for="nom"><i class="fas fa-user me-2"></i>Nom *</label>
                        </div>
                    </div>
                </div>
                
                <div class="form-floating">
                    <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    <label for="email"><i class="fas fa-envelope me-2"></i>Adresse email *</label>
                </div>
                
                <div class="form-floating">
                    <input type="tel" class="form-control" id="telephone" name="telephone" placeholder="Téléphone" value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>">
                    <label for="telephone"><i class="fas fa-phone me-2"></i>Téléphone</label>
                </div>
                
                <div class="form-floating">
                    <select class="form-select" id="type_user" name="type_user">
                        <option value="client" <?= ($_POST['type_user'] ?? 'client') === 'client' ? 'selected' : '' ?>>Client</option>
                        <option value="agent" <?= ($_POST['type_user'] ?? '') === 'agent' ? 'selected' : '' ?>>Agent immobilier</option>
                    </select>
                    <label for="type_user"><i class="fas fa-user-tag me-2"></i>Type de compte</label>
                </div>
                
                <div class="form-floating">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Mot de passe" required>
                    <label for="password"><i class="fas fa-lock me-2"></i>Mot de passe *</label>
                    <div id="passwordStrength" class="password-strength"></div>
                </div>
                
                <div class="form-floating">
                    <input type="password" class="form-control" id="password_confirm" name="password_confirm" placeholder="Confirmer le mot de passe" required>
                    <label for="password_confirm"><i class="fas fa-lock me-2"></i>Confirmer le mot de passe *</label>
                    <div id="passwordMatch" class="password-strength"></div>
                </div>
                
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                    <label class="form-check-label" for="terms">
                        J'accepte les <a href="../cgu.php" target="_blank">conditions d'utilisation</a> et la <a href="../politique-confidentialite.php" target="_blank">politique de confidentialité</a>
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary w-100 btn-lg">
                    <i class="fas fa-user-plus me-2"></i>Créer mon compte
                </button>
            </form>
            
            <div class="auth-links">
                <p>Déjà un compte ? <a href="login.php">Se connecter</a></p>
                <p><a href="../index.php">← Retour à l'accueil</a></p>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Validation en temps réel du mot de passe
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('passwordStrength');
            
            if (password.length === 0) {
                strengthDiv.innerHTML = '';
                return;
            }
            
            let strength = 0;
            let feedback = [];
            
            if (password.length >= 6) strength++;
            else feedback.push('Au moins 6 caractères');
            
            if (/[a-z]/.test(password)) strength++;
            else feedback.push('Une minuscule');
            
            if (/[A-Z]/.test(password)) strength++;
            else feedback.push('Une majuscule');
            
            if (/[0-9]/.test(password)) strength++;
            else feedback.push('Un chiffre');
            
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            else feedback.push('Un caractère spécial');
            
            const levels = ['Très faible', 'Faible', 'Moyen', 'Fort', 'Très fort'];
            const classes = ['strength-weak', 'strength-weak', 'strength-medium', 'strength-strong', 'strength-strong'];
            
            strengthDiv.innerHTML = `<span class="${classes[strength - 1]}">${levels[strength - 1] || 'Très faible'}</span>`;
            if (feedback.length > 0) {
                strengthDiv.innerHTML += `<br><small>Manque: ${feedback.join(', ')}</small>`;
            }
        });
        
        // Validation de la confirmation du mot de passe
        document.getElementById('password_confirm').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirm = this.value;
            const matchDiv = document.getElementById('passwordMatch');
            
            if (confirm.length === 0) {
                matchDiv.innerHTML = '';
                return;
            }
            
            if (password === confirm) {
                matchDiv.innerHTML = '<span class="strength-strong"><i class="fas fa-check me-1"></i>Les mots de passe correspondent</span>';
            } else {
                matchDiv.innerHTML = '<span class="strength-weak"><i class="fas fa-times me-1"></i>Les mots de passe ne correspondent pas</span>';
            }
        });
    </script>
</body>
</html>