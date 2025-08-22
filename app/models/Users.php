<?php

use Phalcon\Mvc\Model;
use Phalcon\Validation;
use Phalcon\Validation\Validator\Email;
use Phalcon\Validation\Validator\Uniqueness;
use Phalcon\Validation\Validator\StringLength;

class Users extends Model
{
    public $id;
    public $email;
    public $password;
    public $first_name;
    public $last_name;
    public $status;
    public $created_at;
    public $updated_at;

    public function initialize()
    {
        $this->setSource('users');

        // Especificar secuencia para PostgreSQL
        $this->setSequenceName('users_id_seq');
    }

    public function beforeValidationOnCreate()
    {
        // PostgreSQL maneja automáticamente created_at y updated_at con triggers
        $this->status = 1; // Activo por defecto
    }

    public function validation()
    {
        $validator = new Validation();

        $validator->add('email', new Email([
            'message' => 'Email inválido'
        ]));

        $validator->add('email', new Uniqueness([
            'message' => 'Este email ya está registrado'
        ]));

        $validator->add('first_name', new StringLength([
            'min' => 2,
            'max' => 100,
            'messageMinimum' => 'El nombre debe tener al menos 2 caracteres',
            'messageMaximum' => 'El nombre no puede tener más de 100 caracteres'
        ]));

        $validator->add('last_name', new StringLength([
            'min' => 2,
            'max' => 100,
            'messageMinimum' => 'El apellido debe tener al menos 2 caracteres',
            'messageMaximum' => 'El apellido no puede tener más de 100 caracteres'
        ]));

        $validator->add('password', new StringLength([
            'min' => 6,
            'messageMinimum' => 'La contraseña debe tener al menos 6 caracteres'
        ]));

        return $this->validate($validator);
    }

    public function beforeSave()
    {
        if ($this->password && !$this->getDI()->getSecurity()->checkHash($this->password, $this->password)) {
            $this->password = $this->getDI()->getSecurity()->hash($this->password);
        }
    }

    public function columnMap()
    {
        // Mapeo de columnas para PostgreSQL
        return [
            'id' => 'id',
            'email' => 'email',
            'password' => 'password',
            'first_name' => 'first_name',
            'last_name' => 'last_name',
            'status' => 'status',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at'
        ];
    }

    // Método para obtener usuario por email (optimizado para PostgreSQL)
    public static function findByEmail($email)
    {
        return self::findFirst([
            'conditions' => 'email = :email: AND status = :status:',
            'bind' => [
                'email' => $email,
                'status' => 1
            ]
        ]);
    }
}
