<?php

use Phalcon\Mvc\Model;
use Phalcon\Validation;
use Phalcon\Validation\Validator\StringLength;

class TblPonentes extends Model
{
    public $id;
    public $titulo;
    public $nombre;
    public $cargo;
    public $compania;

    public $imagen_perfil;

    public $fecha_creacion;

    public function initialize()
    {
        $this->setSource('tbl_ponentes');
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

        $validator->add('cargo', new StringLength([
            'min' => 2,
            'max' => 255,
            'messageMinimum' => 'El cargo debe tener al menos 2 caracteres',
            'messageMaximum' => 'El cargo no puede tener más de 255 caracteres'
        ]));

        return $this->validate($validator);
    }

    public function columnMap()
    {
        return [
            'id' => 'id',
            'titulo' => 'titulo',
            'nombre' => 'nombre',
            'cargo' => 'cargo',
            'compania' => 'compania',
            'imagen_perfil' => 'imagen_perfil',
            'fecha_creacion' => 'fecha_creacion'
        ];
    }
}
