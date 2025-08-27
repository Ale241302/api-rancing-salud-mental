<?php

use Phalcon\Mvc\Model;
use Phalcon\Validation;
use Phalcon\Validation\Validator\StringLength;

class TblAliados extends Model
{
    public $id;
    public $id_categoria;
    public $nombre;
    public $imagen;
    public $fecha_creacion;

    public function initialize()
    {
        $this->setSource('tbl_aliados');
    }

    public function validation()
    {
        $validator = new Validation();

        $validator->add('nombre', new StringLength([
            'min' => 2,
            'max' => 255,
            'messageMinimum' => 'El nombre debe tener al menos 2 caracteres',
            'messageMaximum' => 'El nombre no puede tener más de 255 caracteres'
        ]));

        return $this->validate($validator);
    }

    public function columnMap()
    {
        return [
            'id' => 'id',
            'id_categoria' => 'id_categoria',
            'nombre' => 'nombre',
            'imagen' => 'imagen',
            'fecha_creacion' => 'fecha_creacion'
        ];
    }

    public static function findByCategoria($idCategoria)
    {
        return self::find([
            'conditions' => 'id_categoria = :id_categoria:',
            'bind' => ['id_categoria' => $idCategoria],
            'order' => 'nombre ASC'
        ]);
    }
}
