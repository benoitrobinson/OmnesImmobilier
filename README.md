# Omnes Immobilier 🏠

## Au service des besoins immobiliers de la communauté Omnes

**Omnes Immobilier** est une plateforme web innovante dédiée aux besoins immobiliers en ligne de la communauté Omnes Education. Cette application permet aux clients de consulter des propriétés immobilières, de sélectionner des agents spécialisés, et de prendre des rendez-vous pour visiter des biens, le tout dans un environnement numérique moderne et intuitif.

---

## 🎯 Objectifs du Projet

Ce projet académique vise à créer une plateforme complète de consultation immobilière et de prise de rendez-vous en ligne. Les utilisateurs peuvent parcourir différents types de biens immobiliers, interagir avec des agents certifiés, et gérer leurs consultations de manière efficace.

**Contexte académique :** Projet Web Dynamique 2025 - ING2  
**Institution :** ECE École d'Ingénieurs  
**Durée :** Mai - Juin 2025

---

## ✨ Fonctionnalités Principales

### Pour les Clients
- **Consultation de biens** : Parcourir les propriétés par catégorie (résidentiel, commercial, terrain, location, enchères)
- **Recherche avancée** : Trouver des biens par ville, agent ou numéro de propriété
- **Prise de rendez-vous** : Réserver des créneaux avec les agents immobiliers en temps réel
- **Communication multimédia** : Échanger avec les agents par texto, audio, vidéo ou email
- **Gestion de compte** : Suivre l'historique des consultations et gérer les rendez-vous
- **Système de paiement** : Régler les frais d'agence et services premium

### Pour les Agents Immobiliers
- **Gestion de calendrier** : Définir les disponibilités et gérer les rendez-vous
- **Communication client** : Outils intégrés pour échanger avec les prospects
- **Profil professionnel** : CV détaillé, spécialités, et coordonnées
- **Suivi des consultations** : Historique des interactions avec les clients

### Pour les Administrateurs
- **Gestion des biens** : Ajouter, modifier et retirer les propriétés du catalogue
- **Gestion des agents** : Intégrer de nouveaux agents et gérer leurs profils
- **Administration globale** : Superviser l'ensemble de la plateforme

### Fonctionnalités Avancées
- **Ventes aux enchères** : Système d'enchères en ligne pour certains biens
- **Événements de la semaine** : Mise en avant d'activités spéciales (portes ouvertes, séminaires)
- **Cartes de rabais** : Système de réductions pour fidéliser la clientèle
- **Géolocalisation** : Intégration Google Maps pour localiser les biens

---

## 🛠️ Technologies Utilisées

### Frontend
- **HTML5** : Structure sémantique des pages
- **CSS3** : Stylisation et responsive design
- **JavaScript** : Interactivité côté client
- **jQuery** : Manipulation DOM et requêtes AJAX
- **Bootstrap** : Framework CSS pour un design moderne et responsive

### Backend
- **PHP** : Logique serveur et traitement des données
- **MySQL** : Base de données relationnelle pour le stockage
- **PDO** : Interface sécurisée pour les interactions base de données

### Outils de Développement
- **Git** : Versioning et collaboration en équipe
- **GitHub** : Hébergement du code source
- **phpMyAdmin** : Administration de la base de données

---

## 📁 Structure du Projet

```
omnes-immobilier-2025/
├── docs/                          # Documentation du projet
│   ├── wireframes/               # Maquettes et storyboards
│   ├── database/                 # Modèles entité-association
│   └── specifications/           # Spécifications fonctionnelles
├── src/                          # Code source principal
│   ├── config/                   # Configuration de l'application
│   ├── assets/                   # Ressources statiques
│   │   ├── css/                 # Feuilles de style
│   │   ├── js/                  # Scripts JavaScript
│   │   └── img/                 # Images et médias
│   ├── php/                     # Scripts PHP
│   │   ├── classes/             # Classes et modèles
│   │   ├── controllers/         # Contrôleurs
│   │   └── includes/            # Fichiers inclus
│   ├── pages/                   # Pages HTML/PHP
│   └── database/                # Scripts SQL
├── tests/                       # Tests (si applicable)
└── README.md                    # Documentation principale
```

---

## 🚀 Installation et Configuration

### Prérequis
- **Serveur web** : Apache ou Nginx
- **PHP** : Version 7.4 ou supérieure
- **MySQL** : Version 5.7 ou supérieure
- **Git** : Pour le clonage et la collaboration

### Étapes d'Installation

1. **Cloner le repository**
   ```bash
   git clone https://github.com/votre-username/omnes-immobilier-2025.git
   cd omnes-immobilier-2025
   ```

2. **Configuration de la base de données**
   ```bash
   # Créer une base de données MySQL
   mysql -u root -p
   CREATE DATABASE omnes_immobilier;
   
   # Importer le schéma de base (à venir)
   mysql -u root -p omnes_immobilier < src/database/schema.sql
   ```

3. **Configuration PHP**
   ```bash
   # Copier le fichier de configuration
   cp src/config/config.example.php src/config/config.php
   
   # Éditer les paramètres de connexion base de données
   nano src/config/config.php
   ```

4. **Lancement du serveur de développement**
   ```bash
   # Avec PHP intégré
   cd src
   php -S localhost:8000
   
   # Ou configurer un virtual host Apache/Nginx
   ```

---

## 🎨 Guide de Développement

### Conventions de Code
- **PHP** : Suivre les standards PSR-4 pour l'autoloading des classes
- **CSS** : Utiliser la méthodologie BEM pour le nommage des classes
- **JavaScript** : Respecter les conventions ES6+ et utiliser des commentaires explicatifs
- **SQL** : Nommer les tables en snake_case, utiliser des clés étrangères appropriées

### Workflow Git
1. Créer une branche pour chaque fonctionnalité : `git checkout -b feature/nom-fonctionnalite`
2. Faire des commits réguliers avec des messages descriptifs
3. Tester localement avant de pousser
4. Créer une Pull Request pour review en équipe

---

## 👥 Équipe de Développement

| Rôle | Nom | Responsabilités |
|------|-----|----------------|
| Chef de Projet | [Nom] | Coordination générale, livrables |
| Développeur Frontend | [Nom] | Interface utilisateur, UX/UI |
| Développeur Backend | [Nom] | Logique serveur, base de données |
| Développeur Fullstack | [Nom] | Intégration frontend/backend |

---

## 📋 Fonctionnalités par Sprint

### Sprint 1 - Fondations (29 mai 2025)
- [ ] Architecture de l'application
- [ ] Modèle de base de données
- [ ] Wireframes et storyboards
- [ ] Structure de fichiers

### Sprint 2 - Développement Core (1er juin 2025)
- [ ] Système d'authentification
- [ ] Gestion des utilisateurs (clients, agents, admin)
- [ ] CRUD des propriétés immobilières
- [ ] Interface de consultation des biens
- [ ] Système de rendez-vous
- [ ] Communication temps réel

---

## 🧪 Tests et Qualité

- **Tests unitaires** : Validation des fonctions critiques
- **Tests d'intégration** : Vérification des interactions entre composants
- **Tests utilisateur** : Validation de l'expérience utilisateur
- **Validation W3C** : Conformité HTML/CSS aux standards web

---

## 🚀 Déploiement

### Environnements
- **Développement** : Serveur local avec base de données de test
- **Staging** : Environnement de préproduction pour tests finaux
- **Production** : Serveur final pour démonstration du projet

---

## 📝 Journal d'Assistance IA

Conformément aux directives du projet, ce document trace l'utilisation de l'intelligence artificielle :

- **Tâches assistées par IA** : [À documenter au fur et à mesure]
- **Pourcentage d'influence IA** : < 40% (conforme aux exigences)
- **Code généré par IA** : [Exemples à inclure]

---

## 📚 Ressources et Références

- [Documentation PHP](https://www.php.net/docs.php)
- [Bootstrap Documentation](https://getbootstrap.com/docs/)
- [MySQL Documentation](https://dev.mysql.com/doc/)
- [Meilleures pratiques Web](https://developer.mozilla.org/en-US/docs/Web)

---

## 📄 Licence

Ce projet est développé dans un cadre académique pour l'ECE École d'Ingénieurs. Tous les droits sont réservés à l'équipe de développement et à l'institution.

---

## 📞 Contact

Pour toute question concernant ce projet :

- **Email institutionnel** : [votre-email@edu.ece.fr]
- **Repository GitHub** : [lien-vers-le-repo]
- **Documentation complète** : Voir le dossier `/docs`

---

*Projet réalisé dans le cadre du cours "Web Dynamique ING2 2025" sous la supervision de l'équipe pédagogique ECE.*
