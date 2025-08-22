<?php

use Phalcon\Mvc\Micro;
use Phalcon\Di\FactoryDefault;
use Phalcon\Security;

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

require_once '../vendor/autoload.php';
require_once '../vendor/firebase/php-jwt/src/JWT.php';
require_once '../vendor/firebase/php-jwt/src/Key.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

try {
    $di = new FactoryDefault();

    // Configurar PDO directo para PostgreSQL
    $di->setShared('pdo', function () {
        $dsn = 'pgsql:host=localhost;port=5432;dbname=rancing_salud_mental';
        $username = 'postgres';
        $password = '241302';

        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);

        return $pdo;
    });

    // Security para hash de contraseñas
    $di->setShared('security', function () {
        $security = new Security();
        $security->setWorkFactor(12);
        return $security;
    });

    $app = new Micro($di);

    // JWT Secret
    $jwtSecret = 'tu_jwt_secret_key_super_segura_2024';

    // CORS Headers
    $app->before(function () use ($app) {
        $app->response->setHeader('Access-Control-Allow-Origin', '*')
            ->setHeader('Access-Control-Allow-Methods', 'GET,PUT,POST,DELETE,OPTIONS')
            ->setHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Authorization')
            ->setHeader('Content-Type', 'application/json');
        return true;
    });

    // Handle OPTIONS
    $app->options('/{catch:(.*)}', function () use ($app) {
        return $app->response->setStatusCode(200, "OK");
    });

    // Test route
    $app->get('/api/test', function () use ($app) {
        try {
            $pdo = $app->getDI()->get('pdo');
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
            $result = $stmt->fetch();

            return $app->response->setJsonContent([
                'success' => true,
                'message' => 'API y BD funcionando correctamente',
                'timestamp' => date('Y-m-d H:i:s'),
                'phalcon_version' => \Phalcon\Version::get(),
                'users_count' => $result['count']
            ]);
        } catch (Exception $e) {
            return $app->response->setJsonContent([
                'success' => false,
                'message' => 'Error de BD: ' . $e->getMessage()
            ]);
        }
    });

    // REGISTRO CON PDO NATIVO
    $app->post('/api/auth/register', function () use ($app, $jwtSecret) {
        try {
            $input = json_decode($app->request->getRawBody(), true);

            if (!$input) {
                return $app->response->setStatusCode(400)->setJsonContent([
                    'success' => false,
                    'message' => 'Datos JSON inválidos'
                ]);
            }

            // Validar campos requeridos
            $required = ['email', 'password', 'first_name', 'last_name'];
            foreach ($required as $field) {
                if (!isset($input[$field]) || empty(trim($input[$field]))) {
                    return $app->response->setStatusCode(400)->setJsonContent([
                        'success' => false,
                        'message' => "El campo {$field} es requerido"
                    ]);
                }
            }

            // Validar email
            if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
                return $app->response->setStatusCode(400)->setJsonContent([
                    'success' => false,
                    'message' => 'Email inválido'
                ]);
            }

            $pdo = $app->getDI()->get('pdo');
            $security = $app->getDI()->get('security');

            $email = strtolower(trim($input['email']));

            // Verificar si email ya existe - CON PDO NATIVO
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $checkStmt->execute([$email]);
            $existingUser = $checkStmt->fetch();

            if ($existingUser) {
                return $app->response->setStatusCode(409)->setJsonContent([
                    'success' => false,
                    'message' => 'Este email ya está registrado'
                ]);
            }

            // Hash de contraseña
            $hashedPassword = $security->hash($input['password']);

            // Insertar usuario - CON PDO NATIVO
            $insertStmt = $pdo->prepare(
                "INSERT INTO users (email, password, first_name, last_name) VALUES (?, ?, ?, ?) RETURNING id, email, first_name, last_name, created_at"
            );

            $insertStmt->execute([
                $email,
                $hashedPassword,
                ucfirst(trim($input['first_name'])),
                ucfirst(trim($input['last_name']))
            ]);

            $user = $insertStmt->fetch();

            if ($user) {
                // Generar JWT
                $payload = [
                    'sub' => (int)$user['id'],
                    'email' => $user['email'],
                    'iat' => time(),
                    'exp' => time() + 86400
                ];

                $token = JWT::encode($payload, $jwtSecret, 'HS256');

                return $app->response->setStatusCode(201)->setJsonContent([
                    'success' => true,
                    'message' => 'Usuario registrado exitosamente',
                    'data' => [
                        'user' => [
                            'id' => (int)$user['id'],
                            'email' => $user['email'],
                            'first_name' => $user['first_name'],
                            'last_name' => $user['last_name'],
                            'full_name' => $user['first_name'] . ' ' . $user['last_name'],
                            'created_at' => $user['created_at']
                        ],
                        'token' => $token,
                        'expires_in' => 86400,
                        'token_type' => 'Bearer'
                    ]
                ]);
            } else {
                return $app->response->setStatusCode(500)->setJsonContent([
                    'success' => false,
                    'message' => 'Error al crear el usuario'
                ]);
            }
        } catch (Exception $e) {
            return $app->response->setStatusCode(500)->setJsonContent([
                'success' => false,
                'message' => 'Error interno del servidor: ' . $e->getMessage()
            ]);
        }
    });

    // LOGIN CON PDO NATIVO
    $app->post('/api/auth/login', function () use ($app, $jwtSecret) {
        try {
            $input = json_decode($app->request->getRawBody(), true);

            if (!$input || !isset($input['email']) || !isset($input['password'])) {
                return $app->response->setStatusCode(400)->setJsonContent([
                    'success' => false,
                    'message' => 'Email y contraseña son requeridos'
                ]);
            }

            $pdo = $app->getDI()->get('pdo');
            $security = $app->getDI()->get('security');

            $email = strtolower(trim($input['email']));

            // Buscar usuario
            $stmt = $pdo->prepare(
                "SELECT id, email, password, first_name, last_name, status FROM users WHERE email = ? AND status = ?"
            );
            $stmt->execute([$email, 1]);
            $user = $stmt->fetch();

            if (!$user) {
                return $app->response->setStatusCode(401)->setJsonContent([
                    'success' => false,
                    'message' => 'Credenciales inválidas'
                ]);
            }

            // Verificar contraseña
            if (!$security->checkHash($input['password'], $user['password'])) {
                return $app->response->setStatusCode(401)->setJsonContent([
                    'success' => false,
                    'message' => 'Credenciales inválidas'
                ]);
            }

            // Generar JWT
            $payload = [
                'sub' => (int)$user['id'],
                'email' => $user['email'],
                'iat' => time(),
                'exp' => time() + 86400
            ];

            $token = JWT::encode($payload, $jwtSecret, 'HS256');

            return $app->response->setJsonContent([
                'success' => true,
                'message' => 'Login exitoso',
                'data' => [
                    'user' => [
                        'id' => (int)$user['id'],
                        'email' => $user['email'],
                        'first_name' => $user['first_name'],
                        'last_name' => $user['last_name'],
                        'full_name' => $user['first_name'] . ' ' . $user['last_name']
                    ],
                    'token' => $token,
                    'expires_in' => 86400,
                    'token_type' => 'Bearer'
                ]
            ]);
        } catch (Exception $e) {
            return $app->response->setStatusCode(500)->setJsonContent([
                'success' => false,
                'message' => 'Error interno del servidor: ' . $e->getMessage()
            ]);
        }
    });

    // PERFIL CON PDO NATIVO
    $app->get('/api/auth/profile', function () use ($app, $jwtSecret) {
        try {
            // Obtener token del header
            $authHeader = '';

            if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
                $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            } elseif (function_exists('apache_request_headers')) {
                $headers = apache_request_headers();
                if (isset($headers['Authorization'])) {
                    $authHeader = $headers['Authorization'];
                }
            }

            if (!$authHeader || !preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
                return $app->response->setStatusCode(401)->setJsonContent([
                    'success' => false,
                    'message' => 'Token no proporcionado'
                ]);
            }

            $token = $matches[1];

            // Decodificar JWT
            $decoded = JWT::decode($token, new Key($jwtSecret, 'HS256'));

            $pdo = $app->getDI()->get('pdo');

            // Obtener usuario actual
            $stmt = $pdo->prepare(
                "SELECT id, email, first_name, last_name, status, created_at, updated_at FROM users WHERE id = ? AND status = ?"
            );
            $stmt->execute([$decoded->sub, 1]);
            $user = $stmt->fetch();

            if (!$user) {
                return $app->response->setStatusCode(401)->setJsonContent([
                    'success' => false,
                    'message' => 'Token inválido'
                ]);
            }

            return $app->response->setJsonContent([
                'success' => true,
                'message' => 'Perfil obtenido exitosamente',
                'data' => [
                    'user' => [
                        'id' => (int)$user['id'],
                        'email' => $user['email'],
                        'first_name' => $user['first_name'],
                        'last_name' => $user['last_name'],
                        'full_name' => $user['first_name'] . ' ' . $user['last_name'],
                        'created_at' => $user['created_at'],
                        'updated_at' => $user['updated_at']
                    ]
                ]
            ]);
        } catch (Exception $e) {
            return $app->response->setStatusCode(401)->setJsonContent([
                'success' => false,
                'message' => 'Token inválido: ' . $e->getMessage()
            ]);
        }
    });

    // Default route
    $app->notFound(function () use ($app) {
        return $app->response->setStatusCode(404)->setJsonContent([
            'success' => false,
            'message' => 'Endpoint no encontrado'
        ]);
    });

    $app->handle();
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor: ' . $e->getMessage()
    ]);
}
