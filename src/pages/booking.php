<?php
// Cette page illustre des concepts avancés de développement web et de conception d'interface :
// 1. Formulaires complexes avec validation côté serveur ET côté client
// 2. Interaction en temps réel avec JavaScript pour vérifier les disponibilités
// 3. Gestion d'état de l'interface utilisateur (étapes du processus de réservation)
// 4. Sécurisation des données utilisateur et protection contre les attaques
// 5. Expérience utilisateur optimisée avec feedback visuel immédiat

require_once '../config/database.php';

// Vérification obligatoire de l'authentification - principe de sécurité fondamental
// Un utilisateur non connecté ne peut pas accéder au système de réservation
if (!isLoggedIn()) {
    redirect('../auth/login.php?redirect=booking.php');
}

$database = new Database();
$db = $database->getConnection();

// Variables pour le processus de réservation
$property_id = filter_input(INPUT_GET, 'property_id', FILTER_VALIDATE_INT);
$agent_id = filter_input(INPUT_GET, 'agent_id', FILTER_VALIDATE_INT);
$step = filter_input(INPUT_GET, 'step', FILTER_VALIDATE_INT) ?: 1;

$error = '';
$success = '';
$property = null;
$agent = null;
$available_agents = [];

// Étape 1 : Sélection de la propriété ou de l'agent
// Cette logique permet d'arriver sur la page de réservation depuis différents points d'entrée
if ($property_id) {    // Cas où l'utilisateur vient d'une page de propriété spécifique
    $property_query = "SELECT p.*, a.user_id as agent_id, u.first_name as agent_first_name, u.last_name as agent_last_name, u.phone as agent_phone
                      FROM properties p 
                      LEFT JOIN agents a ON p.agent_id = a.user_id 
                      LEFT JOIN users u ON a.user_id = u.id 
                      WHERE p.id = :property_id AND p.status = 'available'";
    $property_stmt = $db->prepare($property_query);
    $property_stmt->bindParam(':property_id', $property_id, PDO::PARAM_INT);
    $property_stmt->execute();
    $property = $property_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($property) {
        $agent_id = $property['agent_id'];
    }
}

if ($agent_id) {
    // Récupération des informations de l'agent sélectionné
    $agent_query = "SELECT a.*, u.first_name, u.last_name, u.phone, u.email
                   FROM agents a 
                   JOIN users u ON a.user_id = u.id 
                   WHERE a.user_id = :agent_id";
    $agent_stmt = $db->prepare($agent_query);
    $agent_stmt->bindParam(':agent_id', $agent_id, PDO::PARAM_INT);
    $agent_stmt->execute();
    $agent = $agent_stmt->fetch(PDO::FETCH_ASSOC);
}

// Si aucun agent n'est spécifié, récupérer la liste des agents disponibles
if (!$agent_id) {
    $agents_query = "SELECT a.user_id as id, u.first_name, u.last_name, a.agency_name,
                    COUNT(p.id) as nb_proprietes
                    FROM agents a 
                    JOIN users u ON a.user_id = u.id 
                    LEFT JOIN properties p ON a.user_id = p.agent_id AND p.status = 'available'
                    WHERE u.role = 'agent'
                    GROUP BY a.user_id 
                    ORDER BY u.first_name, u.last_name";
    $agents_stmt = $db->prepare($agents_query);
    $agents_stmt->execute();
    $available_agents = $agents_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Traitement du formulaire de réservation
// Cette section illustre la validation complète des données et la gestion des erreurs
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $selected_agent_id = filter_input(INPUT_POST, 'agent_id', FILTER_VALIDATE_INT);
    $selected_property_id = filter_input(INPUT_POST, 'property_id', FILTER_VALIDATE_INT);
    $date_rdv = $_POST['date_rdv'] ?? '';
    $heure_rdv = $_POST['heure_rdv'] ?? '';
    $type_rdv = $_POST['type_rdv'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    
    // Validation rigoureuse des données d'entrée
    if (!$selected_agent_id) {
        $error = 'Veuillez sélectionner un agent.';
    } elseif (!$date_rdv || !$heure_rdv) {
        $error = 'Veuillez sélectionner une date et une heure.';
    } elseif (!in_array($type_rdv, ['visite', 'consultation', 'signature'])) {
        $error = 'Type de rendez-vous invalide.';
    } else {
        // Vérification de la validité de la date et heure
        $datetime_rdv = $date_rdv . ' ' . $heure_rdv;
        $rdv_timestamp = strtotime($datetime_rdv);
        
        if (!$rdv_timestamp || $rdv_timestamp <= time()) {
            $error = 'La date et heure sélectionnées ne sont pas valides ou sont dans le passé.';
        } else {            // Vérification de la disponibilité de l'agent (éviter les conflits)
            $conflict_query = "SELECT id FROM appointments 
                              WHERE agent_id = :agent_id 
                              AND appointment_date = :date_rdv 
                              AND status IN ('scheduled')";
            $conflict_stmt = $db->prepare($conflict_query);
            $conflict_stmt->bindParam(':agent_id', $selected_agent_id);
            $conflict_stmt->bindParam(':date_rdv', $datetime_rdv);
            $conflict_stmt->execute();
            
            if ($conflict_stmt->rowCount() > 0) {
                $error = 'Ce créneau est déjà réservé. Veuillez choisir un autre horaire.';
            } else {
                // Insertion de la demande de rendez-vous dans la base de données
                // Utilisation d'une transaction pour garantir la cohérence des données
                try {
                    $db->beginTransaction();
                      $insert_query = "INSERT INTO appointments 
                                   (client_id, agent_id, property_id, appointment_date, location, status) 
                                   VALUES (:client_id, :agent_id, :property_id, :date_rdv, :notes, 'scheduled')";
                    
                    $insert_stmt = $db->prepare($insert_query);
                    $insert_stmt->bindParam(':client_id', $_SESSION['user_id']);
                    $insert_stmt->bindParam(':agent_id', $selected_agent_id);
                    $insert_stmt->bindParam(':property_id', $selected_property_id);
                    $insert_stmt->bindParam(':date_rdv', $datetime_rdv);
                    $insert_stmt->bindParam(':notes', $notes);
                    
                    if ($insert_stmt->execute()) {
                        $rdv_id = $db->lastInsertId();
                        
                        // Ici, dans un projet réel, on enverrait un email de confirmation
                        // et une notification à l'agent concerné
                        
                        $db->commit();
                        $success = 'Votre demande de rendez-vous a été envoyée avec succès ! L\'agent vous contactera pour confirmer.';
                        
                        // Redirection vers la page de confirmation après un délai
                        header("refresh:3;url=rendez-vous.php?message=rdv_confirmed");
                    } else {
                        throw new Exception('Erreur lors de l\'enregistrement');
                    }
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = 'Une erreur est survenue lors de la réservation. Veuillez réessayer.';
                }
            }
        }
    }
}

// Récupération des types de rendez-vous disponibles
// Cette approche permet une personnalisation facile des options
$types_rdv = [
    'visite' => [
        'label' => 'Visite de propriété',
        'description' => 'Visite guidée d\'un bien immobilier',
        'duree' => 60,
        'icon' => 'fa-home'
    ],
    'consultation' => [
        'label' => 'Consultation générale',
        'description' => 'Discussion sur vos projets immobiliers',
        'duree' => 45,
        'icon' => 'fa-comments'
    ],
    'signature' => [
        'label' => 'Signature de documents',
        'description' => 'Finalisation d\'une transaction',
        'duree' => 30,
        'icon' => 'fa-file-signature'
    ]
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réserver un rendez-vous - Omnes Immobilier</title>
    
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

    <!-- En-tête de la page avec indicateur de progression -->
    <section class="booking-header py-5 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <h1 class="display-5 fw-bold mb-3">Réserver un rendez-vous</h1>
                    <p class="lead">Prenez rendez-vous avec nos experts immobiliers</p>
                    
                    <!-- Indicateur de progression du processus de réservation -->
                    <div class="booking-progress mt-4">
                        <div class="progress-container">
                            <div class="progress-step <?= $step >= 1 ? 'active' : '' ?>">
                                <div class="step-number">1</div>
                                <span class="step-label">Sélection</span>
                            </div>
                            <div class="progress-line <?= $step > 1 ? 'active' : '' ?>"></div>
                            <div class="progress-step <?= $step >= 2 ? 'active' : '' ?>">
                                <div class="step-number">2</div>
                                <span class="step-label">Date & Heure</span>
                            </div>
                            <div class="progress-line <?= $step > 2 ? 'active' : '' ?>"></div>
                            <div class="progress-step <?= $step >= 3 ? 'active' : '' ?>">
                                <div class="step-number">3</div>
                                <span class="step-label">Confirmation</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="container my-5">
        <!-- Messages d'état avec styles cohérents -->
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

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Formulaire de réservation avec sections conditionnelles -->
                <div class="card booking-card">
                    <div class="card-body p-4">
                        <form method="POST" action="" id="bookingForm" class="needs-validation" novalidate>
                            <!-- Section 1 : Sélection de l'agent et de la propriété -->
                            <div class="booking-section mb-4">
                                <h4 class="section-title mb-3">
                                    <i class="fas fa-user-tie text-primary me-2"></i>
                                    Sélection de l'agent
                                </h4>
                                
                                <?php if ($agent): ?>
                                    <!-- Agent pré-sélectionné -->
                                    <div class="selected-agent-card p-3 bg-light rounded">
                                        <input type="hidden" name="agent_id" value="<?= $agent['id'] ?>">
                                        <div class="d-flex align-items-center">                                            <div class="agent-avatar me-3">
                                                <img src="../assets/images/agents/placeholder-agent.jpg" 
                                                     alt="<?= htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']) ?>"
                                                     class="rounded-circle" width="60" height="60">
                                            </div>
                                            <div class="agent-info flex-grow-1">
                                                <h6 class="mb-1"><?= htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']) ?></h6>
                                                <?php if ($agent['speciality']): ?>
                                                    <p class="text-muted small mb-1"><?= htmlspecialchars($agent['speciality']) ?></p>
                                                <?php endif; ?>
                                                <p class="text-muted small mb-0">
                                                    <i class="fas fa-phone me-1"></i><?= htmlspecialchars($agent['phone']) ?>
                                                </p>
                                            </div>
                                            <div class="agent-actions">
                                                <a href="?step=1" class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-exchange-alt me-1"></i>Changer
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <!-- Sélection d'agent -->
                                    <div class="agent-selection">
                                        <?php if (empty($available_agents)): ?>
                                            <div class="text-center py-4">
                                                <i class="fas fa-user-slash fa-2x text-muted mb-3"></i>
                                                <p class="text-muted">Aucun agent disponible pour le moment.</p>
                                                <a href="../index.php" class="btn btn-primary">Retour à l'accueil</a>
                                            </div>
                                        <?php else: ?>
                                            <div class="row">
                                                <?php foreach ($available_agents as $available_agent): ?>
                                                    <div class="col-md-6 mb-3">
                                                        <div class="agent-option">
                                                            <input type="radio" class="btn-check" name="agent_id" 
                                                                   id="agent_<?= $available_agent['id'] ?>" 
                                                                   value="<?= $available_agent['id'] ?>" required>
                                                            <label class="btn btn-outline-primary w-100 p-3" 
                                                                   for="agent_<?= $available_agent['id'] ?>">
                                                                <div class="d-flex align-items-center">
                                                                    <div class="agent-avatar me-3">                                                                    <img src="../assets/images/agents/placeholder-agent.jpg" 
                                                                             alt="<?= htmlspecialchars($available_agent['first_name'] . ' ' . $available_agent['last_name']) ?>"
                                                                             class="rounded-circle" width="50" height="50">
                                                                    </div>
                                                                    <div class="agent-info text-start">
                                                                        <h6 class="mb-1"><?= htmlspecialchars($available_agent['first_name'] . ' ' . $available_agent['last_name']) ?></h6>
                                                                        <?php if ($available_agent['speciality']): ?>
                                                                            <p class="small text-muted mb-1"><?= htmlspecialchars($available_agent['speciality']) ?></p>
                                                                        <?php endif; ?>
                                                                        <p class="small text-muted mb-0">
                                                                            <?= $available_agent['nb_proprietes'] ?> propriétés • 
                                                                            <?= $available_agent['experience_annees'] ?: 'N/A' ?> ans d'exp.
                                                                        </p>
                                                                    </div>
                                                                </div>
                                                            </label>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Section 2 : Sélection de la propriété (optionnelle) -->
                            <div class="booking-section mb-4">
                                <h4 class="section-title mb-3">
                                    <i class="fas fa-home text-primary me-2"></i>
                                    Propriété concernée (optionnel)
                                </h4>
                                
                                <?php if ($property): ?>
                                    <!-- Propriété pré-sélectionnée -->
                                    <div class="selected-property-card p-3 bg-light rounded">
                                        <input type="hidden" name="property_id" value="<?= $property['id'] ?>">
                                        <div class="d-flex align-items-center">
                                            <div class="property-image me-3">
                                                <img src="../assets/images/placeholder-property.jpg" 
                                                     alt="<?= htmlspecialchars($property['titre']) ?>"
                                                     class="rounded" width="80" height="60" style="object-fit: cover;">
                                            </div>
                                            <div class="property-info flex-grow-1">
                                                <h6 class="mb-1"><?= htmlspecialchars($property['titre']) ?></h6>
                                                <p class="text-muted small mb-1">
                                                    <i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($property['ville']) ?>
                                                </p>
                                                <p class="text-primary small mb-0 fw-bold"><?= formatPrice($property['prix']) ?></p>
                                            </div>
                                            <div class="property-actions">
                                                <a href="?agent_id=<?= $agent_id ?>&step=1" class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-times me-1"></i>Retirer
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <!-- Sélection optionnelle de propriété -->
                                    <div class="property-selection">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="no_property">
                                            <label class="form-check-label" for="no_property">
                                                Ce rendez-vous ne concerne pas une propriété spécifique
                                            </label>
                                        </div>
                                        
                                        <div id="property_search_container" class="mt-3">
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="property_search" 
                                                       placeholder="Rechercher une propriété par nom ou ville...">
                                                <button class="btn btn-outline-secondary" type="button" onclick="searchProperties()">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                            </div>
                                            <div id="property_results" class="mt-2"></div>
                                        </div>
                                        
                                        <input type="hidden" name="property_id" id="selected_property_id">
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Section 3 : Type de rendez-vous -->
                            <div class="booking-section mb-4">
                                <h4 class="section-title mb-3">
                                    <i class="fas fa-calendar-alt text-primary me-2"></i>
                                    Type de rendez-vous
                                </h4>
                                
                                <div class="row">
                                    <?php foreach ($types_rdv as $key => $type): ?>
                                        <div class="col-md-4 mb-3">
                                            <div class="rdv-type-option">
                                                <input type="radio" class="btn-check" name="type_rdv" 
                                                       id="type_<?= $key ?>" value="<?= $key ?>" required>
                                                <label class="btn btn-outline-primary w-100 p-3" for="type_<?= $key ?>">
                                                    <div class="text-center">
                                                        <i class="fas <?= $type['icon'] ?> fa-2x mb-2"></i>
                                                        <h6><?= $type['label'] ?></h6>
                                                        <p class="small text-muted mb-1"><?= $type['description'] ?></p>
                                                        <span class="badge bg-info"><?= $type['duree'] ?> min</span>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Section 4 : Date et heure -->
                            <div class="booking-section mb-4">
                                <h4 class="section-title mb-3">
                                    <i class="fas fa-clock text-primary me-2"></i>
                                    Date et heure souhaitées
                                </h4>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Date du rendez-vous</label>
                                        <input type="date" class="form-control" name="date_rdv" 
                                               min="<?= date('Y-m-d', strtotime('+1 day')) ?>" 
                                               max="<?= date('Y-m-d', strtotime('+30 days')) ?>"
                                               required>
                                        <div class="form-text">Les rendez-vous peuvent être pris jusqu'à 30 jours à l'avance</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Créneau horaire</label>
                                        <select class="form-select" name="heure_rdv" required disabled>
                                            <option value="">Choisissez d'abord une date</option>
                                        </select>
                                        <div class="form-text">Les créneaux disponibles s'afficheront après sélection de la date</div>
                                    </div>
                                </div>
                                
                                <!-- Calendrier de disponibilité visuelle (sera implémenté via JavaScript) -->
                                <div id="availability_calendar" class="mt-3" style="display: none;">
                                    <div class="availability-legend d-flex justify-content-center gap-3 mb-3">
                                        <span><i class="fas fa-circle text-success"></i> Disponible</span>
                                        <span><i class="fas fa-circle text-danger"></i> Occupé</span>
                                        <span><i class="fas fa-circle text-warning"></i> Partiellement libre</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Section 5 : Message optionnel -->
                            <div class="booking-section mb-4">
                                <h4 class="section-title mb-3">
                                    <i class="fas fa-comment-dots text-primary me-2"></i>
                                    Message pour l'agent (optionnel)
                                </h4>
                                
                                <textarea class="form-control" name="notes" rows="4" 
                                          placeholder="Décrivez brièvement l'objet de votre rendez-vous, vos questions spécifiques, ou toute information utile pour l'agent..."></textarea>
                                <div class="form-text">
                                    Ce message aidera l'agent à mieux préparer votre rendez-vous. 
                                    Vous pouvez mentionner vos critères de recherche, votre budget, ou vos contraintes de timing.
                                </div>
                            </div>

                            <!-- Récapitulatif et validation -->
                            <div class="booking-summary p-3 bg-light rounded mb-4" style="display: none;" id="booking_summary">
                                <h5 class="mb-3">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Récapitulatif de votre demande
                                </h5>
                                <div id="summary_content">
                                    <!-- Le contenu sera rempli via JavaScript -->
                                </div>
                            </div>

                            <!-- Conditions et soumission -->
                            <div class="booking-actions">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="terms_accept" required>
                                    <label class="form-check-label" for="terms_accept">
                                        J'accepte les <a href="../cgu.php" target="_blank">conditions d'utilisation</a> 
                                        et la <a href="../politique-confidentialite.php" target="_blank">politique de confidentialité</a>
                                    </label>
                                </div>
                                
                                <div class="form-check mb-4">
                                    <input class="form-check-input" type="checkbox" id="data_consent">
                                    <label class="form-check-label" for="data_consent">
                                        J'accepte que mes données soient partagées avec l'agent sélectionné 
                                        pour le traitement de ma demande de rendez-vous
                                    </label>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="../index.php" class="btn btn-outline-secondary me-md-2">
                                        <i class="fas fa-times me-1"></i>Annuler
                                    </a>
                                    <button type="submit" class="btn btn-primary btn-lg" id="submit_button">
                                        <i class="fas fa-paper-plane me-2"></i>Envoyer la demande
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar avec conseils et informations -->
            <div class="col-lg-4">
                <div class="sticky-top" style="top: 2rem;">
                    <!-- Conseils pour bien préparer son rendez-vous -->
                    <div class="card info-card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-lightbulb text-warning me-2"></i>
                                Conseils pour votre rendez-vous
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="tip-item mb-3">
                                <h6 class="text-primary">Préparez vos questions</h6>
                                <p class="small text-muted">
                                    Listez vos critères, votre budget et vos contraintes à l'avance.
                                </p>
                            </div>
                            
                            <div class="tip-item mb-3">
                                <h6 class="text-primary">Apportez vos documents</h6>
                                <p class="small text-muted">
                                    Pièce d'identité, justificatifs de revenus si pertinents.
                                </p>
                            </div>
                            
                            <div class="tip-item">
                                <h6 class="text-primary">Soyez ponctuel</h6>
                                <p class="small text-muted">
                                    Arrivez 5 minutes en avance pour optimiser votre temps de rendez-vous.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Informations de contact d'urgence -->
                    <div class="card contact-emergency mb-4">
                        <div class="card-body">
                            <h6 class="text-primary mb-3">
                                <i class="fas fa-phone-alt me-2"></i>
                                Besoin d'aide ?
                            </h6>
                            <p class="small text-muted mb-2">
                                Notre équipe est disponible pour vous accompagner :
                            </p>
                            <p class="small">
                                <i class="fas fa-phone me-1 text-primary"></i>
                                <a href="tel:+33123456789" class="text-decoration-none">+33 1 23 45 67 89</a>
                            </p>
                            <p class="small">
                                <i class="fas fa-envelope me-1 text-primary"></i>
                                <a href="mailto:rdv@omnesimmobilier.fr" class="text-decoration-none">rdv@omnesimmobilier.fr</a>
                            </p>
                        </div>
                    </div>
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
        // Gestion complexe du formulaire de réservation avec validation en temps réel
        // Ce script illustre la programmation événementielle moderne et la gestion d'état
        
        document.addEventListener('DOMContentLoaded', function() {
            // Initialisation des éléments du formulaire
            const form = document.getElementById('bookingForm');
            const dateInput = document.querySelector('input[name="date_rdv"]');
            const heureSelect = document.querySelector('select[name="heure_rdv"]');
            const agentInputs = document.querySelectorAll('input[name="agent_id"]');
            const typeInputs = document.querySelectorAll('input[name="type_rdv"]');
            const submitButton = document.getElementById('submit_button');
            
            // Gestion de la sélection d'agent avec mise à jour des disponibilités
            agentInputs.forEach(input => {
                input.addEventListener('change', function() {
                    if (this.checked) {
                        updateAvailabilityForAgent(this.value);
                        updateFormValidation();
                    }
                });
            });
            
            // Gestion du changement de date avec vérification des créneaux
            if (dateInput) {
                dateInput.addEventListener('change', function() {
                    const selectedDate = this.value;
                    const selectedAgent = document.querySelector('input[name="agent_id"]:checked')?.value;
                    
                    if (selectedDate && selectedAgent) {
                        loadAvailableSlots(selectedAgent, selectedDate);
                    } else {
                        heureSelect.disabled = true;
                        heureSelect.innerHTML = '<option value="">Sélectionnez d\'abord un agent et une date</option>';
                    }
                });
            }
            
            // Validation en temps réel du formulaire
            form.addEventListener('input', updateFormValidation);
            form.addEventListener('change', updateFormValidation);
            
            // Gestion de la recherche de propriétés
            setupPropertySearch();
            
            // Gestion de la case "aucune propriété"
            const noPropertyCheck = document.getElementById('no_property');
            if (noPropertyCheck) {
                noPropertyCheck.addEventListener('change', function() {
                    const container = document.getElementById('property_search_container');
                    const hiddenInput = document.getElementById('selected_property_id');
                    
                    if (this.checked) {
                        container.style.display = 'none';
                        hiddenInput.value = '';
                    } else {
                        container.style.display = 'block';
                    }
                    updateFormValidation();
                });
            }
            
            // Soumission du formulaire avec vérifications finales
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (validateCompleteForm()) {
                    // Affichage du récapitulatif avant soumission
                    if (showBookingSummary()) {
                        // Confirmation utilisateur avant envoi définitif
                        if (confirm('Confirmer l\'envoi de votre demande de rendez-vous ?')) {
                            this.submit();
                        }
                    }
                } else {
                    // Mise en évidence des erreurs
                    highlightFormErrors();
                }
            });
        });
        
        // Fonction pour charger les créneaux disponibles via AJAX
        // Cette fonction illustre l'interaction asynchrone avec le serveur
        function loadAvailableSlots(agentId, date) {
            const heureSelect = document.querySelector('select[name="heure_rdv"]');
            const loadingOption = '<option value="">Chargement des créneaux...</option>';
            
            heureSelect.innerHTML = loadingOption;
            heureSelect.disabled = true;
            
            // Appel AJAX pour récupérer les disponibilités
            fetch(`../api/check-availability.php?agent_id=${agentId}&date=${date}`)
                .then(response => response.json())
                .then(data => {
                    heureSelect.innerHTML = '<option value="">Choisir un créneau</option>';
                    
                    if (data.success && data.slots.length > 0) {
                        data.slots.forEach(slot => {
                            const option = document.createElement('option');
                            option.value = slot.time;
                            option.textContent = `${slot.display} ${slot.available ? '' : '(Limité)'}`;
                            option.disabled = !slot.available;
                            heureSelect.appendChild(option);
                        });
                        heureSelect.disabled = false;
                    } else {
                        heureSelect.innerHTML = '<option value="">Aucun créneau disponible</option>';
                    }
                })
                .catch(error => {
                    console.error('Erreur lors du chargement des créneaux:', error);
                    heureSelect.innerHTML = '<option value="">Erreur de chargement</option>';
                });
        }
        
        // Fonction de recherche de propriétés avec autocomplétion
        function setupPropertySearch() {
            const searchInput = document.getElementById('property_search');
            const resultsContainer = document.getElementById('property_results');
            let searchTimeout;
            
            if (!searchInput) return;
            
            searchInput.addEventListener('input', function() {
                const query = this.value.trim();
                
                clearTimeout(searchTimeout);
                
                if (query.length < 2) {
                    resultsContainer.innerHTML = '';
                    return;
                }
                
                // Délai de recherche pour éviter trop de requêtes
                searchTimeout = setTimeout(() => {
                    searchProperties(query);
                }, 300);
            });
        }
        
        function searchProperties(query) {
            const resultsContainer = document.getElementById('property_results');
            
            resultsContainer.innerHTML = '<div class="text-center p-2"><i class="fas fa-spinner fa-spin"></i> Recherche...</div>';
            
            fetch(`../api/search-properties.php?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.properties.length > 0) {
                        let html = '<div class="property-search-results">';
                        data.properties.forEach(property => {
                            html += `
                                <div class="property-result-item p-2 border rounded mb-2" 
                                     onclick="selectProperty(${property.id}, '${property.titre.replace(/'/g, "\\'")}', '${property.ville}', '${property.prix}')">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">${property.titre}</h6>
                                            <small class="text-muted">${property.ville} • ${property.prix}</small>
                                        </div>
                                        <i class="fas fa-plus text-primary"></i>
                                    </div>
                                </div>
                            `;
                        });
                        html += '</div>';
                        resultsContainer.innerHTML = html;
                    } else {
                        resultsContainer.innerHTML = '<div class="text-muted text-center p-2">Aucune propriété trouvée</div>';
                    }
                })
                .catch(error => {
                    console.error('Erreur de recherche:', error);
                    resultsContainer.innerHTML = '<div class="text-danger text-center p-2">Erreur de recherche</div>';
                });
        }
        
        // Fonction pour sélectionner une propriété depuis les résultats de recherche
        function selectProperty(id, titre, ville, prix) {
            document.getElementById('selected_property_id').value = id;
            document.getElementById('property_search').value = `${titre} - ${ville}`;
            document.getElementById('property_results').innerHTML = '';
            
            // Notification visuelle de la sélection
            window.omnesImmobilier.showToast(`Propriété sélectionnée: ${titre}`, 'success');
            
            updateFormValidation();
        }
        
        // Validation complète du formulaire avant soumission
        // Cette fonction centralise toutes les vérifications nécessaires
        function validateCompleteForm() {
            let isValid = true;
            const errors = [];
            
            // Vérification de la sélection d'agent
            const selectedAgent = document.querySelector('input[name="agent_id"]:checked');
            if (!selectedAgent) {
                errors.push('Veuillez sélectionner un agent');
                isValid = false;
            }
            
            // Vérification du type de rendez-vous
            const selectedType = document.querySelector('input[name="type_rdv"]:checked');
            if (!selectedType) {
                errors.push('Veuillez sélectionner un type de rendez-vous');
                isValid = false;
            }
            
            // Vérification de la date et heure
            const dateValue = document.querySelector('input[name="date_rdv"]').value;
            const heureValue = document.querySelector('select[name="heure_rdv"]').value;
            
            if (!dateValue || !heureValue) {
                errors.push('Veuillez sélectionner une date et une heure');
                isValid = false;
            }
            
            // Vérification de l'acceptation des conditions
            const termsAccepted = document.getElementById('terms_accept').checked;
            if (!termsAccepted) {
                errors.push('Veuillez accepter les conditions d\'utilisation');
                isValid = false;
            }
            
            // Affichage des erreurs si nécessaire
            if (!isValid) {
                window.omnesImmobilier.showToast('Formulaire incomplet: ' + errors.join(', '), 'error');
            }
            
            return isValid;
        }
        
        // Mise à jour de l'état de validation du formulaire en temps réel
        function updateFormValidation() {
            const submitButton = document.getElementById('submit_button');
            const isFormValid = checkFormCompleteness();
            
            if (isFormValid) {
                submitButton.disabled = false;
                submitButton.classList.remove('btn-secondary');
                submitButton.classList.add('btn-primary');
            } else {
                submitButton.disabled = true;
                submitButton.classList.remove('btn-primary');
                submitButton.classList.add('btn-secondary');
            }
        }
        
        // Vérification rapide de la complétude du formulaire
        function checkFormCompleteness() {
            const hasAgent = document.querySelector('input[name="agent_id"]:checked');
            const hasType = document.querySelector('input[name="type_rdv"]:checked');
            const hasDate = document.querySelector('input[name="date_rdv"]').value;
            const hasHeure = document.querySelector('select[name="heure_rdv"]').value;
            const hasTerms = document.getElementById('terms_accept').checked;
            
            return hasAgent && hasType && hasDate && hasHeure && hasTerms;
        }
        
        // Affichage du récapitulatif avant soumission finale
        function showBookingSummary() {
            const summaryContainer = document.getElementById('booking_summary');
            const summaryContent = document.getElementById('summary_content');
            
            // Construction du contenu du récapitulatif
            const agentName = document.querySelector('input[name="agent_id"]:checked')?.closest('label')?.textContent.trim();
            const typeRdv = document.querySelector('input[name="type_rdv"]:checked')?.closest('label')?.textContent.trim();
            const dateRdv = document.querySelector('input[name="date_rdv"]').value;
            const heureRdv = document.querySelector('select[name="heure_rdv"] option:checked')?.textContent;
            
            let html = `
                <div class="row">
                    <div class="col-md-6">
                        <strong>Agent :</strong> ${agentName || 'Non spécifié'}<br>
                        <strong>Type :</strong> ${typeRdv || 'Non spécifié'}<br>
                    </div>
                    <div class="col-md-6">
                        <strong>Date :</strong> ${dateRdv ? new Date(dateRdv).toLocaleDateString('fr-FR') : 'Non spécifiée'}<br>
                        <strong>Heure :</strong> ${heureRdv || 'Non spécifiée'}<br>
                    </div>
                </div>
            `;
            
            summaryContent.innerHTML = html;
            summaryContainer.style.display = 'block';
            
            // Scroll vers le récapitulatif
            summaryContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            return true;
        }
        
        // Mise en évidence des erreurs de formulaire
        function highlightFormErrors() {
            // Animation d'attention sur les champs requis non remplis
            const requiredFields = document.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                if (!field.value || (field.type === 'radio' && !document.querySelector(`input[name="${field.name}"]:checked`))) {
                    field.classList.add('is-invalid');
                    
                    // Animation d'attention
                    field.style.animation = 'shake 0.5s ease-in-out';
                    setTimeout(() => {
                        field.style.animation = '';
                    }, 500);
                } else {
                    field.classList.remove('is-invalid');
                }
            });
        }
    </script>

    <style>
        /* Styles spécifiques pour la page de réservation */
        .booking-header {
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.1) 0%, rgba(29, 78, 216, 0.1) 100%);
        }
        
        .booking-progress .progress-container {
            display: flex;
            align-items: center;
            justify-content: center;
            max-width: 400px;
            margin: 0 auto;
        }
        
        .progress-step {
            text-align: center;
            position: relative;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--gray-300);
            color: var(--gray-600);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin: 0 auto 0.5rem;
            transition: all 0.3s ease;
        }
        
        .progress-step.active .step-number {
            background: var(--primary-color);
            color: white;
        }
        
        .step-label {
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .progress-step.active .step-label {
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .progress-line {
            width: 80px;
            height: 2px;
            background: var(--gray-300);
            margin: 0 1rem;
            transition: all 0.3s ease;
        }
        
        .progress-line.active {
            background: var(--primary-color);
        }
        
        .booking-card {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: none;
        }
        
        .section-title {
            color: var(--gray-800);
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 0.5rem;
        }
        
        .agent-option .btn-check:checked + .btn {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .rdv-type-option .btn-check:checked + .btn {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .selected-agent-card, .selected-property-card {
            border: 2px solid var(--primary-color);
        }
        
        .property-result-item {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .property-result-item:hover {
            background: var(--gray-100) !important;
            border-color: var(--primary-color) !important;
        }
        
        .tip-item {
            border-left: 3px solid var(--primary-color);
            padding-left: 1rem;
        }
        
        .booking-summary {
            border: 2px solid var(--success-color);
        }
        
        /* Animation shake pour les erreurs */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        .is-invalid {
            border-color: var(--danger-color) !important;
            box-shadow: 0 0 0 0.2rem rgba(239, 68, 68, 0.25) !important;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .progress-container {
                flex-direction: column;
                gap: 1rem;
            }
            
            .progress-line {
                width: 2px;
                height: 40px;
                margin: 0;
            }
            
            .agent-option, .rdv-type-option {
                margin-bottom: 1rem;
            }
        }
    </style>
</body>
</html>