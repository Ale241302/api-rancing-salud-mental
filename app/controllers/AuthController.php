<?php
require_once '../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthController extends BaseController
{
    /**
     * Registro de nuevo usuario
     * POST /api/auth/register
     */
    public function registerAction()
    {
        try {
            $input = $this->getJsonInput();

            if (!$input) {
                return $this->jsonError('Datos JSON inválidos', 400);
            }

            // Validar campos requeridos
            $required = ['email', 'password', 'first_name', 'last_name'];
            foreach ($required as $field) {
                if (!isset($input[$field]) || empty(trim($input[$field]))) {
                    return $this->jsonError("El campo {$field} es requerido", 400);
                }
            }

            // Validar formato de email
            if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
                return $this->jsonError('El formato del email es inválido', 400);
            }

            // Validar longitud de contraseña
            if (strlen($input['password']) < 6) {
                return $this->jsonError('La contraseña debe tener al menos 6 caracteres', 400);
            }

            // Verificar si el email ya existe
            $existingUser = Users::findByEmail($input['email']);
            if ($existingUser) {
                return $this->jsonError('Este email ya está registrado', 409);
            }

            // Iniciar transacción
            $this->db->begin();

            // Crear nuevo usuario
            $user = new Users();
            $user->email = strtolower(trim($input['email']));
            $user->password = $input['password']; // Se hashea en beforeSave()
            $user->first_name = ucfirst(trim($input['first_name']));
            $user->last_name = ucfirst(trim($input['last_name']));

            if ($user->save()) {
                $this->db->commit();

                // Generar token JWT
                $token = $this->generateJWT($user);

                return $this->jsonResponse([
                    'user' => [
                        'id' => (int)$user->id,
                        'email' => $user->email,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'full_name' => $user->first_name . ' ' . $user->last_name,
                        'created_at' => $user->created_at
                    ],
                    'token' => $token,
                    'expires_in' => $this->config->jwt->expire
                ], 201, 'Usuario registrado exitosamente');
            } else {
                $this->db->rollback();
                $messages = [];
                foreach ($user->getMessages() as $message) {
                    $messages[] = $message->getMessage();
                }
                return $this->jsonError(implode(', ', $messages), 400);
            }
        } catch (Exception $e) {
            if ($this->db->isUnderTransaction()) {
                $this->db->rollback();
            }
            error_log('Register Error: ' . $e->getMessage());
            return $this->jsonError('Error interno del servidor', 500);
        }
    }

    /**
     * Inicio de sesión
     * POST /api/auth/login
     */
    public function loginAction()
    {
        try {
            $input = $this->getJsonInput();

            if (!$input || !isset($input['email']) || !isset($input['password'])) {
                return $this->jsonError('Email y contraseña son requeridos', 400);
            }

            $email = strtolower(trim($input['email']));
            $password = $input['password'];

            // Validar formato de email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->jsonError('Formato de email inválido', 400);
            }

            // Buscar usuario activo
            $user = Users::findByEmail($email);

            if (!$user) {
                // Log de intento de login fallido
                error_log("Login failed - User not found: {$email}");
                return $this->jsonError('Credenciales inválidas', 401);
            }

            // Verificar si el usuario está activo
            if ($user->status != 1) {
                error_log("Login failed - User inactive: {$email}");
                return $this->jsonError('Cuenta inactiva. Contacte al administrador', 403);
            }

            // Verificar contraseña
            if (!$this->security->checkHash($password, $user->password)) {
                error_log("Login failed - Wrong password: {$email}");
                return $this->jsonError('Credenciales inválidas', 401);
            }

            // Actualizar última fecha de login
            $this->updateLastLogin($user->id);

            // Generar token JWT
            $token = $this->generateJWT($user);

            // Log de login exitoso
            error_log("Login successful: {$email}");

            return $this->jsonResponse([
                'user' => [
                    'id' => (int)$user->id,
                    'email' => $user->email,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'full_name' => $user->first_name . ' ' . $user->last_name,
                    'created_at' => $user->created_at
                ],
                'token' => $token,
                'expires_in' => $this->config->jwt->expire,
                'token_type' => 'Bearer'
            ], 200, 'Login exitoso');
        } catch (Exception $e) {
            error_log('Login Error: ' . $e->getMessage());
            return $this->jsonError('Error interno del servidor', 500);
        }
    }

    /**
     * Obtener perfil del usuario autenticado
     * GET /api/auth/profile
     */
    public function profileAction()
    {
        try {
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                return $this->jsonError('Token inválido o expirado', 401);
            }

            return $this->jsonResponse([
                'user' => [
                    'id' => (int)$user->id,
                    'email' => $user->email,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'full_name' => $user->first_name . ' ' . $user->last_name,
                    'status' => (int)$user->status,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at
                ]
            ], 200, 'Perfil obtenido exitosamente');
        } catch (Exception $e) {
            error_log('Profile Error: ' . $e->getMessage());
            return $this->jsonError('Error interno del servidor', 500);
        }
    }

    /**
     * Actualizar perfil del usuario
     * PUT /api/auth/profile
     */
    public function updateProfileAction()
    {
        try {
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                return $this->jsonError('Token inválido o expirado', 401);
            }

            $input = $this->getJsonInput();
            if (!$input) {
                return $this->jsonError('Datos JSON inválidos', 400);
            }

            // Iniciar transacción
            $this->db->begin();

            // Actualizar campos permitidos
            if (isset($input['first_name']) && !empty(trim($input['first_name']))) {
                $user->first_name = ucfirst(trim($input['first_name']));
            }

            if (isset($input['last_name']) && !empty(trim($input['last_name']))) {
                $user->last_name = ucfirst(trim($input['last_name']));
            }

            if ($user->save()) {
                $this->db->commit();

                return $this->jsonResponse([
                    'user' => [
                        'id' => (int)$user->id,
                        'email' => $user->email,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'full_name' => $user->first_name . ' ' . $user->last_name,
                        'updated_at' => $user->updated_at
                    ]
                ], 200, 'Perfil actualizado exitosamente');
            } else {
                $this->db->rollback();
                $messages = [];
                foreach ($user->getMessages() as $message) {
                    $messages[] = $message->getMessage();
                }
                return $this->jsonError(implode(', ', $messages), 400);
            }
        } catch (Exception $e) {
            if ($this->db->isUnderTransaction()) {
                $this->db->rollback();
            }
            error_log('Update Profile Error: ' . $e->getMessage());
            return $this->jsonError('Error interno del servidor', 500);
        }
    }

    /**
     * Cambiar contraseña
     * POST /api/auth/change-password
     */
    public function changePasswordAction()
    {
        try {
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                return $this->jsonError('Token inválido o expirado', 401);
            }

            $input = $this->getJsonInput();
            if (!$input) {
                return $this->jsonError('Datos JSON inválidos', 400);
            }

            // Validar campos requeridos
            if (!isset($input['current_password']) || !isset($input['new_password'])) {
                return $this->jsonError('Contraseña actual y nueva contraseña son requeridas', 400);
            }

            // Validar contraseña actual
            if (!$this->security->checkHash($input['current_password'], $user->password)) {
                return $this->jsonError('La contraseña actual es incorrecta', 400);
            }

            // Validar nueva contraseña
            if (strlen($input['new_password']) < 6) {
                return $this->jsonError('La nueva contraseña debe tener al menos 6 caracteres', 400);
            }

            // Verificar que la nueva contraseña sea diferente
            if ($this->security->checkHash($input['new_password'], $user->password)) {
                return $this->jsonError('La nueva contraseña debe ser diferente a la actual', 400);
            }

            // Iniciar transacción
            $this->db->begin();

            // Actualizar contraseña
            $user->password = $input['new_password']; // Se hashea en beforeSave()

            if ($user->save()) {
                $this->db->commit();

                // Log del cambio de contraseña
                error_log("Password changed for user: {$user->email}");

                return $this->jsonResponse(null, 200, 'Contraseña cambiada exitosamente');
            } else {
                $this->db->rollback();
                $messages = [];
                foreach ($user->getMessages() as $message) {
                    $messages[] = $message->getMessage();
                }
                return $this->jsonError(implode(', ', $messages), 400);
            }
        } catch (Exception $e) {
            if ($this->db->isUnderTransaction()) {
                $this->db->rollback();
            }
            error_log('Change Password Error: ' . $e->getMessage());
            return $this->jsonError('Error interno del servidor', 500);
        }
    }

    /**
     * Validar token
     * POST /api/auth/validate-token
     */
    public function validateTokenAction()
    {
        try {
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                return $this->jsonError('Token inválido o expirado', 401);
            }

            $tokenData = $this->getTokenData();

            return $this->jsonResponse([
                'valid' => true,
                'user_id' => (int)$user->id,
                'email' => $user->email,
                'expires_at' => date('Y-m-d H:i:s', $tokenData->exp)
            ], 200, 'Token válido');
        } catch (Exception $e) {
            error_log('Validate Token Error: ' . $e->getMessage());
            return $this->jsonError('Token inválido', 401);
        }
    }

    /**
     * Cerrar sesión (invalidar token del lado del cliente)
     * POST /api/auth/logout
     */
    public function logoutAction()
    {
        try {
            $user = $this->getAuthenticatedUser();
            if ($user) {
                error_log("User logged out: {$user->email}");
            }

            return $this->jsonResponse(null, 200, 'Sesión cerrada exitosamente');
        } catch (Exception $e) {
            error_log('Logout Error: ' . $e->getMessage());
            return $this->jsonError('Error interno del servidor', 500);
        }
    }

    /**
     * Refrescar token JWT
     * POST /api/auth/refresh-token
     */
    public function refreshTokenAction()
    {
        try {
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                return $this->jsonError('Token inválido o expirado', 401);
            }

            $tokenData = $this->getTokenData();

            // Verificar si el token expira en los próximos 30 minutos
            $now = time();
            $timeUntilExpiry = $tokenData->exp - $now;

            if ($timeUntilExpiry > 1800) { // 30 minutos
                return $this->jsonError('El token aún es válido, no necesita renovación', 400);
            }

            // Generar nuevo token
            $newToken = $this->generateJWT($user);

            return $this->jsonResponse([
                'token' => $newToken,
                'expires_in' => $this->config->jwt->expire,
                'token_type' => 'Bearer'
            ], 200, 'Token renovado exitosamente');
        } catch (Exception $e) {
            error_log('Refresh Token Error: ' . $e->getMessage());
            return $this->jsonError('Error interno del servidor', 500);
        }
    }

    // =============================================
    // MÉTODOS PRIVADOS
    // =============================================

    /**
     * Generar token JWT
     */
    private function generateJWT($user)
    {
        $now = time();
        $payload = [
            'iss' => 'rancing-salud-mental-api', // Issuer
            'sub' => (int)$user->id,             // Subject (user ID)
            'aud' => 'rancing-salud-mental-app', // Audience
            'iat' => $now,                       // Issued at
            'nbf' => $now,                       // Not before
            'exp' => $now + $this->config->jwt->expire, // Expiration
            'jti' => uniqid(),                   // JWT ID
            'user' => [
                'id' => (int)$user->id,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name
            ]
        ];

        return JWT::encode($payload, $this->config->jwt->secret, 'HS256');
    }

    /**
     * Obtener usuario autenticado
     */
    private function getAuthenticatedUser()
    {
        $token = $this->getBearerToken();
        if (!$token) {
            return false;
        }

        try {
            $decoded = JWT::decode($token, new Key($this->config->jwt->secret, 'HS256'));

            // Verificar que el token tenga la estructura correcta
            if (!isset($decoded->sub)) {
                return false;
            }

            $user = Users::findFirst([
                'conditions' => 'id = :id: AND status = :status:',
                'bind' => [
                    'id' => $decoded->sub,
                    'status' => 1
                ]
            ]);

            return $user;
        } catch (Exception $e) {
            error_log('JWT Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener datos del token JWT
     */
    private function getTokenData()
    {
        $token = $this->getBearerToken();
        if (!$token) {
            throw new Exception('Token no encontrado');
        }

        return JWT::decode($token, new Key($this->config->jwt->secret, 'HS256'));
    }

    /**
     * Extraer token Bearer del header Authorization
     */
    private function getBearerToken()
    {
        $headers = $this->request->getHeaders();

        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
        } elseif (isset($headers['authorization'])) {
            $authHeader = $headers['authorization'];
        } else {
            return false;
        }

        $matches = [];
        if (preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
            return $matches[1];
        }

        return false;
    }

    /**
     * Actualizar último login del usuario
     */
    private function updateLastLogin($userId)
    {
        try {
            $this->db->execute(
                "UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE id = :id",
                ['id' => $userId]
            );
        } catch (Exception $e) {
            error_log('Update last login error: ' . $e->getMessage());
        }
    }

    /**
     * Sanitizar entrada de texto
     */
    private function sanitizeInput($input)
    {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validar fortaleza de contraseña
     */
    private function validatePasswordStrength($password)
    {
        $errors = [];

        if (strlen($password) < 6) {
            $errors[] = 'La contraseña debe tener al menos 6 caracteres';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'La contraseña debe contener al menos una letra minúscula';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'La contraseña debe contener al menos una letra mayúscula';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'La contraseña debe contener al menos un número';
        }

        return $errors;
    }
}
