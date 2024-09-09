<?php

// Importar AltoRouter
require_once __DIR__ . '/../vendor/altorouter/altorouter/AltoRouter.php';

// Importar AuthController
require_once __DIR__ . '/../app/Controllers/AuthController.php';

// Importar WeatherApiController
require_once __DIR__ . '/../app/Controllers/WeatherApiController.php';

// Crear una instancia de AltoRouter
$router = new AltoRouter();

// Definir las rutas
$router->map('GET', '/register', function() {
    echo file_get_contents(__DIR__ . '/../app/Views/Register/register.html');
});

$router->map('GET', '/', function() {
    echo file_get_contents(__DIR__ . '/../app/Views/Login/login.html');
});

$router->map('GET', '/home', function() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start(); // Start the session only if it hasn't been started yet
    }
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Session is not active']);
        // Mostrar toast de error
        ?>
        <div class="toast" id="toast"></div>
        <?php
        echo "<script>document.getElementById('toast').innerText = 'La sesión no está activa'; document.getElementById('toast').style.display = 'block';</script>";
        // Redirigir a la página de inicio de sesión
        echo "<script>window.location.href = '/';</script>";
        return;
    }
    require __DIR__ . '/../app/Views/Home/home.html';
});

$router->map('POST', '/api/login', function() {
    try {
        // Asegurarse de que el namespace y el nombre de la clase sean correctos
        $authController = new AuthController();
        $authController->login();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
});

$router->map('POST', '/api/register', function() {
    try {
        $authController = new AuthController();
        $authController->createUser();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
});

$router->map('GET', '/api/validateSession', function() {
    try {
        $authController = new AuthController();
        $authController->validateSession();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
});

$router->map('GET', '/api/logout', function() {
    try {
        $authController = new AuthController();
        $authController->logout();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
});

$router->map('GET', '/api/weather', function() {
    try {
        $weatherApiController = new WeatherApiController();
        $weatherApiController->getCurrentWeather($_GET['city']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
});

$router->map('GET', '/api/lastTemperature', function() {
    try {
        $weatherApiController = new WeatherApiController();
        $weatherApiController->getLastTemperature($_GET['city']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
});
$router->map('GET', '/api/weatherHistory', function() {
    try {
        $weatherApiController = new WeatherApiController();
        $weatherApiController->getUserWeatherData(); 
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
});
$router->map('GET', '/weatherHistory', function() {
    require_once __DIR__ . '/../app/Views/WeatherHistory/weatherhistory.html';
});


$router->map('POST', '/api/insertWeatherData', function() {
    try {
        $weatherApiController = new WeatherApiController();
        $weatherData = json_decode(file_get_contents('php://input'), true);
        $weatherApiController->insertWeatherData($weatherData);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
});

$router->map('GET|POST', '/error404', function() {
    http_response_code(404);
    echo "Lo sentimos, la página que está buscando no se encuentra.";
});

$router->map('GET|POST', '/error500', function() {
    http_response_code(500);
    echo "Lo sentimos, ha ocurrido un error interno del servidor. Por favor, inténtelo de nuevo más tarde.";
});

// Capturar errores no manejados
set_exception_handler(function($exception) {
    error_log($exception->getMessage());
    http_response_code(500);
    echo "Error: " . $exception->getMessage();
    exit();
});

// Manejar la ruta actual
$match = $router->match();

if ($match && is_callable($match['target'])) {
    call_user_func_array($match['target'], $match['params']);
} else {
    // Manejar 404
    header($_SERVER["SERVER_PROTOCOL"] . ' 404 Not Found');
    echo "Lo sentimos, la página que está buscando no se encuentra.";
}
?>