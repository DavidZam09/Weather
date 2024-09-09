<?php

class AuthController {
    private $db;

    public function __construct() {
        require_once __DIR__ . '/../../config/database.php';
        $this->db = $db;
    }

    public function login() {
        // Obtener los datos del cuerpo de la solicitud
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validar que se hayan proporcionado email y password
        if (!isset($data['email']) || !isset($data['password'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email and password are required']);
            return;
        }
    
        $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
        $password = $data['password'];
    
        try {
            // Preparar la consulta
            $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email");
            $stmt->bindParam(':email', $email); // Bind the parameter to prevent SQL injection
            $stmt->execute();
            $user = $stmt->fetch();
            // Verificar si se encontró el usuario
            if ($user === false) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'User not found']);
                return;
            }
    
            // Verificar la contraseña
            if (password_verify($password, $user['password'])) {
                // Autenticación exitosa
                session_start();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['username']; // Guardar el nombre de usuario en la sesión
    
                echo json_encode(['success' => true, 'message' => 'Login successful']);
            } else {
                // Contraseña incorrecta
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Incorrect password']);
            }
        } catch (PDOException $e) {
            // Error en la base de datos
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
    }

    public function createUser() {
        // Obtener los datos del cuerpo de la solicitud
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validar que se hayan proporcionado email, password y username
        if (!isset($data['email']) || !isset($data['password']) || !isset($data['username'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email, password, and username are required']);
            return;
        }
    
        $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
        $password = $data['password'];
        $name = htmlspecialchars($data['username'], ENT_QUOTES, 'UTF-8');
    
        try {
            // Verificar si el usuario ya existe
            $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email");
            $stmt->bindParam(':email', $email); // Bind the parameter to prevent SQL injection
            $stmt->execute();
            $user = $stmt->fetch();

            if ($user !== false) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'User already exists']);
                return;
            }

            // Hashear la contraseña
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Insertar el nuevo usuario
            $stmt = $this->db->prepare("INSERT INTO users (email, password, username) VALUES (:email, :password, :username)");
            $stmt->bindParam(':email', $email); // Bind the parameter to prevent SQL injection
            $stmt->bindParam(':password', $hashedPassword); // Bind the parameter to prevent SQL injection
            $stmt->bindParam(':username', $name); // Bind the parameter to prevent SQL injection
            $stmt->execute();

            http_response_code(201);
            echo json_encode(['success' => true, 'message' => 'User created successfully']);
        } catch (PDOException $e) {
            // Error en la base de datos
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }

    public function validateSession() {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Session is not active']);
            return;
        }
        echo json_encode(['success' => true, 'message' => 'Session is active']);
    }

    public function logout() {
        session_start();
        session_unset();
        session_destroy();
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
    }
}
