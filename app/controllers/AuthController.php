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
    public function creartarjetaAction()
    {
        try {
            $input = $this->getJsonInput();

            // Validar que se reciban los datos requeridos
            if (
                !$input || !isset($input['id_user']) || !isset($input['numero_tarjeta']) ||
                !isset($input['vencimiento_tarjeta']) || !isset($input['cvc_tarjeta']) ||
                !isset($input['nombre_tarjeta'])
            ) {
                return $this->jsonError('Todos los campos de la tarjeta son requeridos', 400);
            }

            $idUser = (int)$input['id_user'];
            $numeroTarjeta = trim($input['numero_tarjeta']);
            $vencimientoTarjeta = trim($input['vencimiento_tarjeta']);
            $cvcTarjeta = trim($input['cvc_tarjeta']);
            $nombreTarjeta = trim(strtoupper($input['nombre_tarjeta']));

            // Validaciones básicas
            if ($idUser <= 0) {
                return $this->jsonError('ID de usuario inválido', 400);
            }

            // Validar formato de número de tarjeta (solo números, 13-19 dígitos)
            $numeroTarjetaLimpio = preg_replace('/\s+/', '', $numeroTarjeta);
            if (!preg_match('/^\d{13,19}$/', $numeroTarjetaLimpio)) {
                return $this->jsonError('Número de tarjeta inválido', 400);
            }

            // Validar formato de fecha de vencimiento (MM/YY)
            if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $vencimientoTarjeta)) {
                return $this->jsonError('Formato de fecha de vencimiento inválido (MM/YY)', 400);
            }

            // Validar CVC (3 o 4 dígitos)
            if (!preg_match('/^\d{3,4}$/', $cvcTarjeta)) {
                return $this->jsonError('CVC inválido', 400);
            }

            // Validar nombre (al menos 3 caracteres)
            if (strlen($nombreTarjeta) < 3) {
                return $this->jsonError('El nombre en la tarjeta debe tener al menos 3 caracteres', 400);
            }

            // Verificar que el usuario existe
            $usuario = Users::findFirst([
                'conditions' => 'id = :id:',
                'bind' => ['id' => $idUser]
            ]);

            if (!$usuario) {
                return $this->jsonError('Usuario no encontrado', 404);
            }

            // Verificar si el usuario ya tiene esta tarjeta registrada
            $tarjetaExistente = TblTarjetaPago::findFirst([
                'conditions' => 'id_user = :id_user: AND numero_tarjeta = :numero_tarjeta:',
                'bind' => [
                    'id_user' => $idUser,
                    'numero_tarjeta' => $numeroTarjetaLimpio
                ]
            ]);

            if ($tarjetaExistente) {
                // Si existe, actualizar los datos
                $tarjetaExistente->vencimiento_tarjeta = $vencimientoTarjeta;
                $tarjetaExistente->cvc_tarjeta = $cvcTarjeta; // ⚠️ NOTA: En producción, considera no guardar el CVC
                $tarjetaExistente->nombre_tarjeta = $nombreTarjeta;

                if (!$tarjetaExistente->save()) {
                    $errors = [];
                    foreach ($tarjetaExistente->getMessages() as $message) {
                        $errors[] = $message->getMessage();
                    }
                    return $this->jsonError('Error al actualizar la tarjeta: ' . implode(', ', $errors), 400);
                }

                $tarjeta = $tarjetaExistente;
                $mensaje = 'Tarjeta actualizada exitosamente';
            } else {
                // Crear nueva tarjeta
                $tarjeta = new TblTarjetaPago();
                $tarjeta->id_user = $idUser;
                $tarjeta->numero_tarjeta = $numeroTarjetaLimpio;
                $tarjeta->vencimiento_tarjeta = $vencimientoTarjeta;
                $tarjeta->cvc_tarjeta = $cvcTarjeta; // ⚠️ NOTA: En producción, considera no guardar el CVC
                $tarjeta->nombre_tarjeta = $nombreTarjeta;

                if (!$tarjeta->save()) {
                    $errors = [];
                    foreach ($tarjeta->getMessages() as $message) {
                        $errors[] = $message->getMessage();
                    }
                    return $this->jsonError('Error al crear la tarjeta: ' . implode(', ', $errors), 400);
                }

                $mensaje = 'Tarjeta creada exitosamente';
            }

            // Log de registro exitoso
            error_log("Card created/updated successfully for user: {$idUser}");

            return $this->jsonResponse([
                'tarjeta' => [
                    'id' => (int)$tarjeta->id,
                    'numero_tarjeta' => $tarjeta->numero_tarjeta,
                    'vencimiento_tarjeta' => $tarjeta->vencimiento_tarjeta,
                    'nombre_tarjeta' => $tarjeta->nombre_tarjeta,
                    'fecha_creacion' => $tarjeta->fecha_creacion
                ]
            ], 200, $mensaje);
        } catch (Exception $e) {
            error_log('Create Card Error: ' . $e->getMessage());
            return $this->jsonError('Error interno del servidor', 500);
        }
    }
    public function registroeventoAction()
    {
        try {
            $input = $this->getJsonInput();

            // Validar que se reciban todos los datos requeridos
            if (
                !$input || !isset($input['id_evento']) || !isset($input['id_user']) ||
                !isset($input['id_tarjeta_pago']) || !isset($input['cantidad_pago'])
            ) {
                return $this->jsonError('Todos los campos son requeridos: id_evento, id_user, id_tarjeta_pago, cantidad_pago', 400);
            }

            $idEvento = (int)$input['id_evento'];
            $idUser = (int)$input['id_user'];
            $idTarjetaPago = (int)$input['id_tarjeta_pago'];
            $cantidadPago = (float)$input['cantidad_pago'];

            // Validaciones básicas
            if ($idEvento <= 0) {
                return $this->jsonError('ID de evento inválido', 400);
            }

            if ($idUser <= 0) {
                return $this->jsonError('ID de usuario inválido', 400);
            }

            if ($idTarjetaPago <= 0) {
                return $this->jsonError('ID de tarjeta de pago inválido', 400);
            }

            if ($cantidadPago <= 0) {
                return $this->jsonError('Cantidad de pago inválida', 400);
            }

            // Verificar que el evento existe
            $evento = TblEventos::findFirst([
                'conditions' => 'id = :id:',
                'bind' => ['id' => $idEvento]
            ]);

            if (!$evento) {
                return $this->jsonError('Evento no encontrado', 404);
            }

            // ✅ VERIFICAR QUE HAY CUPOS DISPONIBLES
            if ($evento->cupos_evento <= 0) {
                return $this->jsonError('No hay cupos disponibles para este evento', 400);
            }

            // Verificar que el usuario existe
            $usuario = Users::findFirst([
                'conditions' => 'id = :id:',
                'bind' => ['id' => $idUser]
            ]);

            if (!$usuario) {
                return $this->jsonError('Usuario no encontrado', 404);
            }

            // Verificar que la tarjeta de pago existe y pertenece al usuario
            $tarjetaPago = TblTarjetaPago::findFirst([
                'conditions' => 'id = :id: AND id_user = :id_user:',
                'bind' => [
                    'id' => $idTarjetaPago,
                    'id_user' => $idUser
                ]
            ]);

            if (!$tarjetaPago) {
                return $this->jsonError('Tarjeta de pago no encontrada o no pertenece al usuario', 404);
            }

            // Verificar si el usuario ya está registrado en este evento
            $ventaExistente = TblVentasEvento::findFirst([
                'conditions' => 'id_evento = :id_evento: AND id_user = :id_user:',
                'bind' => [
                    'id_evento' => $idEvento,
                    'id_user' => $idUser
                ]
            ]);

            if ($ventaExistente) {
                return $this->jsonError('El usuario ya está registrado en este evento', 409);
            }

            // ✅ INICIAR TRANSACCIÓN PARA ATOMICIDAD
            $this->db->begin();

            try {
                // Crear nuevo registro de venta/inscripción
                $venta = new TblVentasEvento();
                $venta->id_evento = $idEvento;
                $venta->id_user = $idUser;
                $venta->id_tarjeta_pago = $idTarjetaPago;
                $venta->cantidad_pago = $cantidadPago;

                if (!$venta->save()) {
                    $errors = [];
                    foreach ($venta->getMessages() as $message) {
                        $errors[] = $message->getMessage();
                    }
                    throw new Exception('Error al registrar la venta: ' . implode(', ', $errors));
                }

                // ✅ RESTAR 1 CUPO AL EVENTO
                $evento->cupos_evento = $evento->cupos_evento - 1;

                if (!$evento->save()) {
                    $errors = [];
                    foreach ($evento->getMessages() as $message) {
                        $errors[] = $message->getMessage();
                    }
                    throw new Exception('Error al actualizar cupos del evento: ' . implode(', ', $errors));
                }

                // ✅ CONFIRMAR TRANSACCIÓN
                $this->db->commit();

                // Log de registro exitoso
                error_log("Event registration successful - User: {$idUser}, Event: {$idEvento}, Amount: {$cantidadPago}, Cupos restantes: {$evento->cupos_evento}");

                return $this->jsonResponse([
                    'venta' => [
                        'id' => (int)$venta->id,
                        'id_evento' => (int)$venta->id_evento,
                        'id_user' => (int)$venta->id_user,
                        'id_tarjeta_pago' => (int)$venta->id_tarjeta_pago,
                        'cantidad_pago' => (float)$venta->cantidad_pago,
                        'fecha_creacion' => $venta->fecha_creacion
                    ],
                    'evento' => [
                        'id' => (int)$evento->id,
                        'titulo' => $evento->titulo_evento,
                        'fecha' => $evento->fecha_evento,
                        'cupos_restantes' => (int)$evento->cupos_evento // ✅ DEVOLVER CUPOS ACTUALIZADOS
                    ]
                ], 200, 'Registro en evento exitoso');
            } catch (Exception $e) {
                // ✅ REVERTIR TRANSACCIÓN EN CASO DE ERROR
                $this->db->rollback();
                throw $e;
            }
        } catch (Exception $e) {
            error_log('Register Event Error: ' . $e->getMessage());
            return $this->jsonError('Error interno del servidor: ' . $e->getMessage(), 500);
        }
    }



    // ✅ MÉTODO AUXILIAR PARA ENMASCARAR NÚMERO DE TARJETA


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

            // ✅ OBTENER TARJETA DE PAGO DEL USUARIO
            $tarjetaPago = TblTarjetaPago::findFirst([
                'conditions' => 'id_user = :id_user:',
                'bind' => ['id_user' => $user->id],
                'order' => 'fecha_creacion DESC' // Obtener la más reciente
            ]);

            // Generar token JWT
            $token = $this->generateJWT($user);

            // Log de login exitoso
            error_log("Login successful: {$email}");

            // ✅ RESPUESTA ACTUALIZADA CON TARJETA DE PAGO
            $responseData = [
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
            ];

            // ✅ AGREGAR TARJETA SI EXISTE
            if ($tarjetaPago) {
                $responseData['card'] = [
                    'id' => (int)$tarjetaPago->id,
                    'numero_tarjeta' => $tarjetaPago->numero_tarjeta, // ✅ Enmascarar número
                    'vencimiento_tarjeta' => $tarjetaPago->vencimiento_tarjeta,
                    'nombre_tarjeta' => $tarjetaPago->nombre_tarjeta,
                    'fecha_creacion' => $tarjetaPago->fecha_creacion
                ];
            } else {
                $responseData['card'] = null; // No tiene tarjeta registrada
            }

            return $this->jsonResponse($responseData, 200, 'Login exitoso');
        } catch (Exception $e) {
            error_log('Login Error: ' . $e->getMessage());
            return $this->jsonError('Error interno del servidor', 500);
        }
    }

    // ✅ MÉTODO AUXILIAR PARA ENMASCARAR EL NÚMERO DE TARJETA
    private function maskCardNumber($cardNumber)
    {
        if (strlen($cardNumber) < 4) {
            return $cardNumber;
        }

        $lastFour = substr($cardNumber, -4);
        $masked = str_repeat('*', strlen($cardNumber) - 4) . $lastFour;

        return $masked;
    }


    /**
     * Obtener perfil del usuario autenticado
     * GET /api/auth/profile
     */
    public function profileAction()
    {
        try {
            // 1. Usuario autenticado por el JWT
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                return $this->jsonError('Token inválido o expirado', 401);
            }

            /* 2. Obtener la tarjeta más reciente del usuario
           (ajusta el nombre del modelo si es distinto) */
            $tarjeta = TblTarjetaPago::findFirst([
                'conditions' => 'id_user = :uid:',
                'bind'       => ['uid' => $user->id],
                'order'      => 'fecha_creacion DESC'   // la última añadida
            ]);

            // 3. Normalizar datos de tarjeta (o null)
            $cardData = null;
            if ($tarjeta) {
                $cardData = [
                    'id'                 => (int) $tarjeta->id,
                    'numero_tarjeta'     => $tarjeta->numero_tarjeta,      // o enmascara aquí
                    'vencimiento_tarjeta' => $tarjeta->vencimiento_tarjeta,
                    'nombre_tarjeta'     => $tarjeta->nombre_tarjeta,
                    'fecha_creacion'     => $tarjeta->fecha_creacion
                ];
            }

            // 4. Respuesta completa
            return $this->jsonResponse([
                'user' => [
                    'id'         => (int) $user->id,
                    'email'      => $user->email,
                    'first_name' => $user->first_name,
                    'last_name'  => $user->last_name,
                    'full_name'  => $user->first_name . ' ' . $user->last_name,
                    'status'     => (int) $user->status,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at
                ],
                'card' => $cardData                        // ✅ tarjeta o null
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
    /**
     * Listar todos los eventos con información relacionada
     * GET /api/auth/listar-eventos
     */
    public function listareventosAction()
    {
        try {
            // Obtener todos los eventos activos
            $eventos = TblEventos::find([
                'conditions' => 'id_status = :status:',
                'bind' => ['status' => '1'],
                'order' => 'fecha_evento DESC'
            ]);

            if (count($eventos) === 0) {
                return $this->jsonResponse([], 200, 'No se encontraron eventos');
            }

            $eventosData = [];

            foreach ($eventos as $evento) {
                // Obtener status del evento
                $status = Status::findFirst([
                    'conditions' => 'id = :id:',
                    'bind' => ['id' => $evento->id_status]
                ]);

                // Obtener ponentes del evento a través de la tabla intermedia
                $eventoPonentes = TblEventoPonente::findByEvento($evento->id);
                $ponentesData = [];

                foreach ($eventoPonentes as $eventoPonente) {
                    $ponente = TblPonentes::findFirst([
                        'conditions' => 'id = :id:',
                        'bind' => ['id' => $eventoPonente->id_ponente]
                    ]);

                    if ($ponente) {
                        $ponentesData[] = [
                            'id' => (int)$ponente->id,
                            'titulo' => $ponente->titulo,
                            'nombre' => $ponente->nombre,
                            'cargo' => $ponente->cargo,
                            'compania' => $ponente->compania,
                            'fecha_creacion' => $ponente->fecha_creacion,
                            'fecha_asignacion' => $eventoPonente->fecha_creacion
                        ];
                    }
                }

                // Obtener programación del evento
                $programaciones = TblProgramacionEvento::findByEvento($evento->id);
                $programacionData = [];

                foreach ($programaciones as $programacion) {
                    // Obtener horarios de cada día de programación
                    $horarios = TblHorarioDiaEvento::findByProgramacion($programacion->id);
                    $horariosData = [];

                    foreach ($horarios as $horario) {
                        // Buscar información del ponente para este horario
                        $ponenteHorario = null;
                        if ($horario->id_ponente) {
                            $ponenteInfo = TblPonentes::findFirst([
                                'conditions' => 'id = :id:',
                                'bind' => ['id' => $horario->id_ponente]
                            ]);

                            if ($ponenteInfo) {
                                $ponenteHorario = [
                                    'id' => (int)$ponenteInfo->id,
                                    'titulo' => $ponenteInfo->titulo,
                                    'nombre' => $ponenteInfo->nombre,
                                    'cargo' => $ponenteInfo->cargo,
                                    'compania' => $ponenteInfo->compania
                                ];
                            }
                        }

                        $horariosData[] = [
                            'id' => (int)$horario->id,
                            'hora' => $horario->hora,
                            'titulo' => $horario->titulo,
                            'ponente' => $ponenteHorario,
                            'fecha_creacion' => $horario->fecha_creacion
                        ];
                    }

                    $programacionData[] = [
                        'id' => (int)$programacion->id,
                        'dia' => $programacion->dia,
                        'titulo_programa' => $programacion->titulo_programa,
                        'horarios' => $horariosData,
                        'fecha_creacion' => $programacion->fecha_creacion
                    ];
                }

                // Construir data del evento
                $eventoData = [
                    'id' => (int)$evento->id,
                    'titulo_evento' => $evento->titulo_evento,
                    'fecha_evento' => $evento->fecha_evento,
                    'pais_evento' => $evento->pais_evento,
                    'lugar_evento' => $evento->lugar_evento,
                    'acerca_evento' => $evento->acerca_evento,
                    'cupos_evento' => (int)$evento->cupos_evento,
                    'precio_evento' => $evento->precio_evento ? (float)$evento->precio_evento : null,
                    'imagenes_evento' => $evento->imagenes_evento ? json_decode($evento->imagenes_evento, true) : [],
                    'videos_evento' => $evento->videos_evento ? json_decode($evento->videos_evento, true) : [],
                    'tipo_evento' => $evento->tipo_evento,
                    'status' => $status ? [
                        'id' => (int)$status->id,
                        'nombre' => $status->nombre
                    ] : null,
                    'fecha_creacion' => $evento->fecha_creacion,
                    'ponentes' => $ponentesData,
                    'programacion' => $programacionData,
                    'total_ponentes' => count($ponentesData),
                    'total_dias_programacion' => count($programacionData)
                ];

                $eventosData[] = $eventoData;
            }

            return $this->jsonResponse([
                'eventos' => $eventosData,
                'total' => count($eventosData),
                'fecha_consulta' => date('Y-m-d H:i:s')
            ], 200, 'Eventos obtenidos exitosamente');
        } catch (Exception $e) {
            error_log('Listar Eventos Error: ' . $e->getMessage());
            return $this->jsonError('Error interno del servidor', 500);
        }
    }


    /**
     * Obtener un evento específico con toda su información relacionada
     * GET /api/auth/evento/{id}
     */
    public function eventoAction($id = null)
    {
        try {
            if (!$id) {
                return $this->jsonError('ID del evento es requerido', 400);
            }

            // Buscar el evento
            $evento = TblEventos::findFirst([
                'conditions' => 'id = :id:',
                'bind' => ['id' => $id]
            ]);

            if (!$evento) {
                return $this->jsonError('Evento no encontrado', 404);
            }

            // Obtener status del evento
            $status = Status::findFirst([
                'conditions' => 'id = :id:',
                'bind' => ['id' => $evento->id_status]
            ]);

            // Obtener ponentes del evento
            $eventoPonentes = TblEventoPonente::findByEvento($evento->id);
            $ponentesData = [];

            foreach ($eventoPonentes as $eventoPonente) {
                $ponente = TblPonentes::findFirst([
                    'conditions' => 'id = :id:',
                    'bind' => ['id' => $eventoPonente->id_ponente]
                ]);

                if ($ponente) {
                    $ponentesData[] = [
                        'id' => (int)$ponente->id,
                        'titulo' => $ponente->titulo,
                        'imagen' => $ponente->imagen_perfil,
                        'nombre' => $ponente->nombre,
                        'cargo' => $ponente->cargo,
                        'compania' => $ponente->compania,
                        'fecha_creacion' => $ponente->fecha_creacion,
                        'fecha_asignacion' => $eventoPonente->fecha_creacion
                    ];
                }
            }

            // Obtener programación del evento
            $programaciones = TblProgramacionEvento::findByEvento($evento->id);
            $programacionData = [];

            foreach ($programaciones as $programacion) {
                // Obtener horarios de cada día de programación
                $horarios = TblHorarioDiaEvento::findByProgramacion($programacion->id);
                $horariosData = [];

                foreach ($horarios as $horario) {
                    // Buscar información del ponente para este horario
                    $ponenteHorario = null;
                    if ($horario->id_ponente) {
                        $ponenteInfo = TblPonentes::findFirst([
                            'conditions' => 'id = :id:',
                            'bind' => ['id' => $horario->id_ponente]
                        ]);

                        if ($ponenteInfo) {
                            $ponenteHorario = [
                                'id' => (int)$ponenteInfo->id,
                                'titulo' => $ponenteInfo->titulo,
                                'nombre' => $ponenteInfo->nombre,
                                'cargo' => $ponenteInfo->cargo,
                                'compania' => $ponenteInfo->compania
                            ];
                        }
                    }

                    $horariosData[] = [
                        'id' => (int)$horario->id,
                        'hora' => $horario->hora,
                        'titulo' => $horario->titulo,
                        'ponente' => $ponenteHorario,
                        'fecha_creacion' => $horario->fecha_creacion
                    ];
                }

                $programacionData[] = [
                    'id' => (int)$programacion->id,
                    'dia' => $programacion->dia,
                    'titulo_programa' => $programacion->titulo_programa,
                    'horarios' => $horariosData,
                    'fecha_creacion' => $programacion->fecha_creacion
                ];
            }

            // ✅ OBTENER TODOS LOS ALIADOS (sin filtrar por evento)
            $aliados = TblAliados::find([
                'order' => 'nombre ASC'
            ]);

            $aliadosData = [];
            foreach ($aliados as $aliado) {
                $aliadosData[] = [
                    'id' => (int)$aliado->id,
                    'nombre' => $aliado->nombre,
                    'imagen' => $aliado->imagen,
                    'categoria_id' => (int)$aliado->id_categoria,
                    'fecha_creacion' => $aliado->fecha_creacion
                ];
            }
            $this->processImagenesEvento = function ($imagenesString) {
                if (!$imagenesString) {
                    return [];
                }

                // Intentar decodificar como JSON válido primero
                $decoded = json_decode($imagenesString, true);
                if ($decoded !== null) {
                    return $decoded;
                }

                // Si falla, procesar formato incorrecto: {url1,url2}
                if (preg_match('/^\{(.+)\}$/', $imagenesString, $matches)) {
                    $urls = explode(',', $matches[1]);
                    return array_map('trim', $urls);
                }

                return [];
            };


            // Construir data del evento
            $eventoData = [
                'id' => (int)$evento->id,
                'titulo_evento' => $evento->titulo_evento,
                'fecha_evento' => $evento->fecha_evento,
                'pais_evento' => $evento->pais_evento,
                'lugar_evento' => $evento->lugar_evento,
                'acerca_evento' => $evento->acerca_evento,
                'cupos_evento' => (int)$evento->cupos_evento,
                'precio_evento' => $evento->precio_evento ? (float)$evento->precio_evento : null,
                'imagenes_evento' => $this->processImagenesEvento($evento->imagenes_evento),
                'videos_evento' => $evento->videos_evento ? json_decode($evento->videos_evento, true) : [],
                'tipo_evento' => $evento->tipo_evento,
                'status' => $status ? [
                    'id' => (int)$status->id,
                    'nombre' => $status->nombre
                ] : null,
                'fecha_creacion' => $evento->fecha_creacion,
                'ponentes' => $ponentesData,
                'programacion' => $programacionData,
                'aliados' => $aliadosData, // ✅ TODOS LOS ALIADOS
                'total_ponentes' => count($ponentesData),
                'total_dias_programacion' => count($programacionData),
                'total_aliados' => count($aliadosData)
            ];

            return $this->jsonResponse([
                'evento' => $eventoData
            ], 200, 'Evento obtenido exitosamente');
        } catch (Exception $e) {
            error_log('Obtener Evento Error: ' . $e->getMessage());
            return $this->jsonError('Error interno del servidor', 500);
        }
    }

    private function processImagenesEvento($imagenesString)
    {
        if (!$imagenesString) {
            return [];
        }

        // Intentar JSON válido primero
        $decoded = json_decode($imagenesString, true);
        if ($decoded !== null) {
            return $decoded;
        }

        // Manejar formato {url1,url2}
        $imagenesString = trim($imagenesString, '{}');
        $urls = explode(',', $imagenesString);
        return array_map('trim', $urls);
    }

    /**
     * Asignar ponente a evento
     * POST /api/auth/asignar-ponente-evento
     */
    public function asignarPonentEventoAction()
    {
        try {
            $input = $this->getJsonInput();

            if (!$input || !isset($input['id_evento']) || !isset($input['id_ponente'])) {
                return $this->jsonError('id_evento e id_ponente son requeridos', 400);
            }

            // Verificar que el evento existe
            $evento = TblEventos::findFirst([
                'conditions' => 'id = :id:',
                'bind' => ['id' => $input['id_evento']]
            ]);

            if (!$evento) {
                return $this->jsonError('Evento no encontrado', 404);
            }

            // Verificar que el ponente existe
            $ponente = TblPonentes::findFirst([
                'conditions' => 'id = :id:',
                'bind' => ['id' => $input['id_ponente']]
            ]);

            if (!$ponente) {
                return $this->jsonError('Ponente no encontrado', 404);
            }

            // Verificar que no existe ya la relación
            $existeRelacion = TblEventoPonente::findEventoPonente($input['id_evento'], $input['id_ponente']);

            if ($existeRelacion) {
                return $this->jsonError('El ponente ya está asignado a este evento', 409);
            }

            // Crear la relación
            $eventoPonente = new TblEventoPonente();
            $eventoPonente->id_evento = $input['id_evento'];
            $eventoPonente->id_ponente = $input['id_ponente'];

            if ($eventoPonente->save()) {
                return $this->jsonResponse([
                    'id' => (int)$eventoPonente->id,
                    'evento' => $evento->titulo_evento,
                    'ponente' => $ponente->nombre,
                    'fecha_asignacion' => $eventoPonente->fecha_creacion
                ], 201, 'Ponente asignado al evento exitosamente');
            } else {
                $messages = [];
                foreach ($eventoPonente->getMessages() as $message) {
                    $messages[] = $message->getMessage();
                }
                return $this->jsonError(implode(', ', $messages), 400);
            }
        } catch (Exception $e) {
            error_log('Asignar Ponente Evento Error: ' . $e->getMessage());
            return $this->jsonError('Error interno del servidor', 500);
        }
    }
    /* ------------------------------------------------------------------
 *  POST /api-rancing-salud-mental/api/auth/listar-compras
 * ------------------------------------------------------------------ */
    public function listarcomprasAction()
    {
        try {
            // 1) Entrada
            $input = $this->getJsonInput();
            if (!$input || !isset($input['id_user'])) {
                return $this->jsonError('Se requiere el campo id_user', 400);
            }

            $idUser = (int) $input['id_user'];
            if ($idUser <= 0) {
                return $this->jsonError('id_user inválido', 400);
            }

            // 2) Verificar usuario
            $usuario = Users::findFirst([
                'conditions' => 'id = :id:',
                'bind'       => ['id' => $idUser]
            ]);
            if (!$usuario) {
                return $this->jsonError('Usuario no encontrado', 404);
            }

            // 3) Obtener compras
            $ventas   = TblVentasEvento::findByUser($idUser);
            $compras  = [];

            foreach ($ventas as $venta) {
                // --- Evento ---
                $evento      = TblEventos::findFirst($venta->id_evento);
                $eventoBlock = null;

                if ($evento) {
                    $eventoBlock = [
                        'id'             => (int) $evento->id,
                        'titulo_evento'  => $evento->titulo_evento,
                        'fecha_evento'   => $evento->fecha_evento,
                        'pais_evento'    => $evento->pais_evento,
                        'lugar_evento'   => $evento->lugar_evento,
                        'precio_evento'  => (float) $evento->precio_evento,
                        // ✅ NUEVO: imágenes procesadas
                        'imagenes_evento' => $this->processImagenesEvento($evento->imagenes_evento)
                    ];
                }

                // --- Tarjeta (últimos 4) ---
                $tarjeta   = TblTarjetaPago::findFirst($venta->id_tarjeta_pago);
                $cardBlock = null;
                if ($tarjeta) {
                    $cardBlock = [
                        'id'             => (int) $tarjeta->id,
                        'ultima_4'       => substr($tarjeta->numero_tarjeta, -4),
                        'vencimiento'    => $tarjeta->vencimiento_tarjeta,
                        'nombre_tarjeta' => $tarjeta->nombre_tarjeta
                    ];
                }

                // --- Armar compra ---
                $compras[] = [
                    'venta'  => [
                        'id'            => (int)   $venta->id,
                        'cantidad_pago' => (float) $venta->cantidad_pago,
                        'fecha_creacion' => $venta->fecha_creacion
                    ],
                    'evento'  => $eventoBlock,
                    'tarjeta' => $cardBlock
                ];
            }

            return $this->jsonResponse(
                ['compras' => $compras],
                200,
                'Compras listadas exitosamente'
            );
        } catch (Exception $e) {
            error_log('Listar Compras Error: ' . $e->getMessage());
            return $this->jsonError('Error interno del servidor', 500);
        }
    }
}
