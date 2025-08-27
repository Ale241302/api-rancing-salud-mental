<?php

use Phalcon\Mvc\Model;
use Phalcon\Validation;
use Phalcon\Validation\Validator\StringLength;

class TblHorarioDiaEvento extends Model
{
    public $id;
    public $hora;
    public $titulo;
    public $id_ponente;
    public $id_programacion;
    public $fecha_creacion;

    public function initialize()
    {
        $this->setSource('tbl_horario_dia_evento');
    }

    public function validation()
    {
        $validator = new Validation();

        $validator->add('titulo', new StringLength([
            'min' => 3,
            'max' => 255,
            'messageMinimum' => 'El título debe tener al menos 3 caracteres',
            'messageMaximum' => 'El título no puede tener más de 255 caracteres'
        ]));

        return $this->validate($validator);
    }

    public function columnMap()
    {
        return [
            'id' => 'id',
            'hora' => 'hora',
            'titulo' => 'titulo',
            'id_ponente' => 'id_ponente',
            'id_programacion' => 'id_programacion',
            'fecha_creacion' => 'fecha_creacion'
        ];
    }

    public static function findByProgramacion($idProgramacion)
    {
        return self::find([
            'conditions' => 'id_programacion = :id_programacion:',
            'bind' => ['id_programacion' => $idProgramacion],
            'order' => 'hora ASC'
        ]);
    }
}
