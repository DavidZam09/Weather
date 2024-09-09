<?php
session_start(); // Start the session at the beginning of the file

class WeatherApiController
{
    private $apiKey;
    private $baseUrl;
    private $db;
    private $user_id; 
    private $imageUrl;

    public function __construct()
    {
        $this->apiKey = 'afa432fac0e3519d7b4c65e3731e8f69';
        $this->baseUrl = 'https://api.openweathermap.org/data/2.5';
        $this->imageUrl = 'https://openweathermap.org/img/wn/';
        require_once __DIR__ . '/../../config/database.php';
        $this->db = $db;
        $this->user_id = $_SESSION['user_id'] ?? null; // Use null coalescing to avoid undefined index notice
    }

    public function getCurrentWeather($city)
    {
        // Asegúrate de que la API key y la URL estén correctas
        $url = $this->baseUrl . '/weather?q=' . urlencode($city) . '&appid=' . $this->apiKey . '&units=metric&lang=es';

        // Realiza la solicitud
        $response = $this->makeRequest($url);

        // Verifica si la respuesta está vacía o no válida
        if ($response === false) {
            return json_encode(['error' => 'No se pudo obtener respuesta de la API']);
        }

        // Decodifica la respuesta JSON
        $responseData = json_decode($response, true);
        echo json_encode($responseData);
        // Verifica si la decodificación fue exitosa
        if (json_last_error() !== JSON_ERROR_NONE) {
            return json_encode(['error' => 'Error al decodificar el JSON: ' . json_last_error_msg()]);
        }

        // Verifica si hay un código de error en la respuesta de la API
        if (isset($responseData['cod']) && $responseData['cod'] != 200) {
            return json_encode(['error' => 'Error en la API: ' . $responseData['message']]);
        }

        // Retorna los datos correctamente decodificados
        return json_encode($responseData);
    }

    public function insertWeatherData($weatherData)
    {
        // Utilizar la conexión a la base de datos disponible en el contexto
        $db = $this->db;
        
        if (!isset($weatherData['weatherData']['cityName']) || !isset($weatherData['weatherData']['country'])) {
            echo json_encode(['error' => 'No se proporcionaron datos de ciudad y país.']);
            return;
        }
        // Paso 1: Insertar ciudad si no existe
        $cityExists = $db->query("SELECT * FROM cities WHERE CITY_NAME = '" . $weatherData['weatherData']['cityName'] . "'")->rowCount();
        if (!$cityExists) {
            $db->query("INSERT INTO cities (CITY_NAME,COUNTRY) VALUES ('" . $weatherData['weatherData']['cityName'] . "','" . $weatherData['weatherData']['country'] . "')");
            $db->commit();
        }

        // Paso 2: Obtener el ID de la ciudad
        $cityId = $db->query("SELECT id FROM cities WHERE CITY_NAME = '" . $weatherData['weatherData']['cityName'] . "'")->fetchColumn();

        // Paso 3: Insertar relación ciudad-usuario

        if ($this->user_id) {
            $insertQuery = "INSERT INTO weather_data (city_id, temperature, description, date_time, user_id,icon) VALUES ('" . $cityId . "', '" . $weatherData['weatherData']['temperature'] . "', '" . $weatherData['weatherData']['description'] . "', sysdate(), '" . $this->user_id . "','" . $weatherData['weatherData']['icon'] . "')";
        }

        // Paso 4: Insertar datos del clima

        if ($db->query($insertQuery)) {
            echo json_encode(['success' => 'Datos del clima insertados correctamente.']);
        } else {
            echo json_encode(['error' => 'Error al insertar datos del clima.']);
        }
    }
    public function getUserWeatherData()
    {
        $db = $this->db;
        $stmt = $db->prepare("SELECT c.city_name, c.country, wd.temperature, wd.description, wd.date_time, wd.icon
                                FROM WEATHER_DATA WD 
                                JOIN cities C ON C.id = WD.CITY_ID 
                                JOIN users US ON US.id = WD.user_id 
                                WHERE US.ID = :userId");
        $stmt->bindParam(':userId', $this->user_id);
        $stmt->execute();
        $weatherData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($weatherData)) {
            return json_encode(['error' => 'No se encontraron datos del clima para el usuario especificado.']);
        }
        echo json_encode($weatherData);
        return json_encode($weatherData);
    }
    public function getLastTemperature($city)
    {
        $db = $this->db;
        $stmt = $db->prepare("SELECT temperature FROM weather_data WHERE city_name = :city ORDER BY date_time DESC LIMIT 1");
        $stmt->bindParam(':city', $city);
        $stmt->execute();
        $temperature = $stmt->fetchColumn();
        echo json_encode(['temperature' => $temperature]);
    }
    private function makeRequest($url)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        $response = curl_exec($curl);
        $err = curl_errno($curl);

        curl_close($curl);

        if ($err) {
            echo 'cURL Error #:' . $err;
            return false;
        }

        return $response;
    }
}
