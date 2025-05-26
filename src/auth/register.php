<?php
// ========================================
// REGISTRATION PAGE
// ========================================
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('../index.php');
}

$error = '';
$success = '';
$form_data = []; // Store form data for re-display on errors

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Collect form data
    $form_data = [
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name' => trim($_POST['last_name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'role' => $_POST['role'] ?? 'client'
    ];
    
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    // Validation
    if (empty($form_data['first_name']) || empty($form_data['last_name']) || empty($form_data['email']) || empty($password)) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';
    } elseif (strlen($password) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères.';
    } elseif ($password !== $password_confirm) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        // Check if email already exists
        $check_query = "SELECT id FROM users WHERE email = :email";
        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->execute(['email' => $form_data['email']]);
        
        if ($check_stmt->rowCount() > 0) {
            $error = 'Cette adresse email est déjà utilisée.';
        } else {
            try {
                // Begin transaction
                $pdo->beginTransaction();
                
                // Create user account
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                $insert_query = "INSERT INTO users (first_name, last_name, email, password_hash, phone, role) 
                               VALUES (:first_name, :last_name, :email, :password_hash, :phone, :role)";
                $insert_stmt = $pdo->prepare($insert_query);
                $insert_stmt->execute([
                    'first_name' => $form_data['first_name'],
                    'last_name' => $form_data['last_name'],
                    'email' => $form_data['email'],
                    'password_hash' => $password_hash,
                    'phone' => $form_data['phone'],
                    'role' => $form_data['role']
                ]);
                
                $user_id = $pdo->lastInsertId();
                
                // Create role-specific records
                if ($form_data['role'] === 'agent') {
                    $agent_query = "INSERT INTO agents (user_id, cv_file_path, agency_name, agency_address, agency_phone, agency_email) 
                                  VALUES (:user_id, '', 'Non spécifié', 'Non spécifié', :phone, :email)";
                    $agent_stmt = $pdo->prepare($agent_query);
                    $agent_stmt->execute([
                        'user_id' => $user_id,
                        'phone' => $form_data['phone'],
                        'email' => $form_data['email']
                    ]);
                } elseif ($form_data['role'] === 'client') {
                    $client_query = "INSERT INTO clients (user_id, address_line1, city, state, postal_code, country, financial_info) 
                                   VALUES (:user_id, 'Non spécifié', 'Non spécifié', 'Non spécifié', '00000', 'France', '{}')";
                    $client_stmt = $pdo->prepare($client_query);
                    $client_stmt->execute(['user_id' => $user_id]);
                }
                
                // Commit transaction
                $pdo->commit();
                
                $success = 'Compte créé avec succès ! Vous pouvez maintenant vous connecter.';
                
                // Clear form data on success
                $form_data = [];
                
                // Log successful registration
                logSecurityEvent('user_registered', 'New user: ' . $form_data['email']);
                
            } catch (Exception $e) {
                // Rollback on error
                $pdo->rollBack();
                error_log('Registration error: ' . $e->getMessage());
                $error = 'Une erreur est survenue lors de la création du compte. Veuillez réessayer.';
            }
        }
    }
}

// Start output buffering to capture HTML content
ob_start();
?>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-logo">
            <h1><i class="fas fa-building me-2"></i>Omnes Immobilier</h1>
            <p class="text-muted">Créez votre compte professionnel</p>
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
                    <a href="login.php" class="btn btn-success btn-sm">
                        <i class="fas fa-sign-in-alt me-1"></i>Se connecter maintenant
                    </a>
                </div>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="registerForm" novalidate>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-floating">
                        <input type="text" class="form-control" id="first_name" name="first_name" 
                               placeholder="Prénom" required value="<?= htmlspecialchars($form_data['first_name'] ?? '') ?>"
                               autocomplete="given-name">
                        <label for="first_name"><i class="fas fa-user me-2"></i>Prénom *</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-floating">
                        <input type="text" class="form-control" id="last_name" name="last_name" 
                               placeholder="Nom" required value="<?= htmlspecialchars($form_data['last_name'] ?? '') ?>"
                               autocomplete="family-name">
                        <label for="last_name"><i class="fas fa-user me-2"></i>Nom *</label>
                    </div>
                </div>
            </div>
            
            <div class="form-floating">
                <input type="email" class="form-control" id="email" name="email" 
                       placeholder="name@example.com" required value="<?= htmlspecialchars($form_data['email'] ?? '') ?>"
                       autocomplete="email">
                <label for="email"><i class="fas fa-envelope me-2"></i>Adresse email *</label>
            </div>
            
            <div class="form-floating">
                <input type="tel" class="form-control" id="phone" name="phone" 
                       placeholder="Téléphone" value="<?= htmlspecialchars($form_data['phone'] ?? '') ?>"
                       autocomplete="tel">
                <label for="phone"><i class="fas fa-phone me-2"></i>Téléphone</label>
            </div>
            
            <div class="form-floating">
                <select class="form-select" id="role" name="role" required>
                    <option value="" disabled>Choisissez votre type de compte</option>
                    <option value="client" <?= ($form_data['role'] ?? 'client') === 'client' ? 'selected' : '' ?>>
                        Client - Je cherche un bien immobilier
                    </option>
                    <option value="agent" <?= ($form_data['role'] ?? '') === 'agent' ? 'selected' : '' ?>>
                        Agent immobilier - Je propose des biens
                    </option>
                </select>
                <label for="role"><i class="fas fa-user-tag me-2"></i>Type de compte *</label>
            </div>
            
            <div class="form-floating">
                <input type="password" class="form-control" id="password" name="password" 
                       placeholder="Mot de passe" required autocomplete="new-password">
                <label for="password"><i class="fas fa-lock me-2"></i>Mot de passe *</label>
                <div id="passwordStrength" class="password-strength"></div>
            </div>
            
            <div class="form-floating">
                <input type="password" class="form-control" id="password_confirm" name="password_confirm" 
                       placeholder="Confirmer le mot de passe" required autocomplete="new-password">
                <label for="password_confirm"><i class="fas fa-lock me-2"></i>Confirmer le mot de passe *</label>
                <div id="passwordMatch" class="password-strength"></div>
            </div>
            
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                <label class="form-check-label" for="terms">
                    J'accepte les <a href="../pages/cgu.php" target="_blank" rel="noopener">conditions d'utilisation</a> 
                    et la <a href="../pages/politique-confidentialite.php" target="_blank" rel="noopener">politique de confidentialité</a>
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

<?php
// Capture the HTML content
$content = ob_get_clean();

// Render the complete page using your template
require_once '../includes/auth_template.php';
renderAuthPage('Inscription', $content, 'Créez votre compte Omnes Immobilier professionnel');
?>