<?php

use Phalcon\Mvc\Model;
use Phalcon\Validation;
use Phalcon\Validation\Validator\StringLength;
use Phalcon\Validation\Validator\InclusionIn;

class TblEventos extends Model
{
    public $id;
    public $titulo_evento;
    public $fecha_evento;
    public $pais_evento;
    public $lugar_evento;
    public $acerca_evento;
    public $cupos_evento;
    public $precio_evento;
    public $imagenes_evento;
    public $videos_evento;
    public $tipo_evento;
    public $id_status;
    public $fecha_creacion;

    public function initialize()
    {
        $this->setSource('tbl_eventos');
    }

    public function beforeValidationOnCreate()
    {
        $this->id_status = '1'; // Activo por defecto
    }

    public function validation()
    {
        $validator = new Validation();

        $validator->add('titulo_evento', new StringLength([
            'min' => 3,
            'max' => 255,
            'messageMinimum' => 'El título del evento debe tener al menos 3 caracteres',
            'messageMaximum' => 'El título del evento no puede tener más de 255 caracteres'
        ]));

        $validator->add('pais_evento', new StringLength([
            'min' => 2,
            'max' => 100,
            'messageMinimum' => 'El país debe tener al menos 2 caracteres',
            'messageMaximum' => 'El país no puede tener más de 100 caracteres'
        ]));

        $validator->add('lugar_evento', new StringLength([
            'min' => 3,
            'max' => 255,
            'messageMinimum' => 'El lugar debe tener al menos 3 caracteres',
            'messageMaximum' => 'El lugar no puede tener más de 255 caracteres'
        ]));

        return $this->validate($validator);
    }

    public function columnMap()
    {
        return [
            'id' => 'id',
            'titulo_evento' => 'titulo_evento',
            'fecha_evento' => 'fecha_evento',
            'pais_evento' => 'pais_evento',
            'lugar_evento' => 'lugar_evento',
            'acerca_evento' => 'acerca_evento',
            'cupos_evento' => 'cupos_evento',
            'precio_evento' => 'precio_evento',
            'imagenes_evento' => 'imagenes_evento',
            'videos_evento' => 'videos_evento',
            'tipo_evento' => 'tipo_evento',
            'id_status' => 'id_status',
            'fecha_creacion' => 'fecha_creacion'
        ];
    }

    public static function findByStatus($status)
    {
        return self::find([
            'conditions' => 'id_status = :status:',
            'bind' => ['status' => $status]
        ]);
    }
}
