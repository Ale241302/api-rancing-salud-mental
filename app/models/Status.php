<?php

use Phalcon\Mvc\Model;
use Phalcon\Validation;
use Phalcon\Validation\Validator\StringLength;
use Phalcon\Validation\Validator\Uniqueness;

class Status extends Model
{
    public $id;
    public $nombre;
    public $fecha_creacion;

    public function initialize()
    {
        $this->setSource('status');
    }

    public function validation()
    {
        $validator = new Validation();

        $validator->add('nombre', new StringLength([
            'min' => 2,
            'max' => 100,
            'messageMinimum' => 'El nombre debe tener al menos 2 caracteres',
            'messageMaximum' => 'El nombre no puede tener más de 100 caracteres'
        ]));

        $validator->add('nombre', new Uniqueness([
            'message' => 'Este nombre de status ya existe'
        ]));

        return $this->validate($validator);
    }

    public function columnMap()
    {
        return [
            'id' => 'id',
            'nombre' => 'nombre',
            'fecha_creacion' => 'fecha_creacion'
        ];
    }

    public static function findByName($nombre)
    {
        return self::findFirst([
            'conditions' => 'nombre = :nombre:',
            'bind' => ['nombre' => $nombre]
        ]);
    }
}
