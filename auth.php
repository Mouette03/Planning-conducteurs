<?php
session_start();

function verifierAuthentification() {
    if (!isset($_SESSION['user'])) {
        header('Location: login.php');
        exit;
    }
}

function verifierAdmin() {
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        header('Location: index.php');
        exit;
    }
}

function getUserById($id) {
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare("SELECT id, username, role, nom, email, date_creation, dernier_login, actif FROM " . DB_PREFIX . "users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getUsers() {
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare("SELECT id, username, role, nom, email, date_creation, dernier_login, actif FROM " . DB_PREFIX . "users ORDER BY username");
    $stmt->execute();
    return $stmt->fetchAll();
}

function createUser($data) {
    if (empty($data['username']) || empty($data['password'])) {
        throw new Exception("Nom d'utilisateur et mot de passe requis");
    }

    $pdo = Database::getInstance();
    
    // Vérifier si l'utilisateur existe déjà
    $stmt = $pdo->prepare("SELECT id FROM " . DB_PREFIX . "users WHERE username = ?");
    $stmt->execute([$data['username']]);
    if ($stmt->fetch()) {
        throw new Exception("Ce nom d'utilisateur existe déjà");
    }

    // Hash du mot de passe
    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

    $sql = "INSERT INTO " . DB_PREFIX . "users 
            (username, password, role, nom, email) 
            VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $data['username'],
        $hashedPassword,
        $data['role'] ?? 'user',
        $data['nom'] ?? null,
        $data['email'] ?? null
    ]);

    return $pdo->lastInsertId();
}

function updateUser($id, $data) {
    $pdo = Database::getInstance();
    $updates = [];
    $params = [];

    // Mise à jour des champs de base
    if (isset($data['nom'])) {
        $updates[] = "nom = ?";
        $params[] = $data['nom'];
    }
    if (isset($data['email'])) {
        $updates[] = "email = ?";
        $params[] = $data['email'];
    }
    if (isset($data['role'])) {
        $updates[] = "role = ?";
        $params[] = $data['role'];
    }
    if (isset($data['actif'])) {
        $updates[] = "actif = ?";
        $params[] = $data['actif'];
    }

    // Mise à jour du mot de passe si fourni
    if (!empty($data['password'])) {
        $updates[] = "password = ?";
        $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
    }

    if (empty($updates)) {
        return true;
    }

    $params[] = $id;
    $sql = "UPDATE " . DB_PREFIX . "users SET " . implode(", ", $updates) . " WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

function deleteUser($id) {
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare("DELETE FROM " . DB_PREFIX . "users WHERE id = ?");
    return $stmt->execute([$id]);
}

function authentifier($username, $password) {
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare("SELECT * FROM " . DB_PREFIX . "users WHERE username = ? AND actif = TRUE");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Mise à jour de la date de dernière connexion
        $stmt = $pdo->prepare("UPDATE " . DB_PREFIX . "users SET dernier_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);

        // Stockage en session (sans le mot de passe)
        unset($user['password']);
        $_SESSION['user'] = $user;
        return true;
    }

    return false;
}