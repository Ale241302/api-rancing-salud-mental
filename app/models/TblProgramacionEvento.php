<?php

use Phalcon\Mvc\Model;
use Phalcon\Validation;
use Phalcon\Validation\Validator\StringLength;

class TblProgramacionEvento extends Model
{
    public $id;
    public $dia;
    public $titulo_programa;
    public $id_evento;
    public $fecha_creacion;

    public function initialize()
    {
        $this->setSource('tbl_programacion_evento');
    }

    public function validation()
    {
        $validator = new Validation();

        $validator->add('titulo_programa', new StringLength([
            'min' => 3,
            'max' => 255,
            'messageMinimum' => 'El título del programa debe tener al menos 3 caracteres',
            'messageMaximum' => 'El título del programa no puede tener más de 255 caracteres'
        ]));

        $validator->add('dia', new StringLength([
            'min' => 3,
            'max' => 50,
            'messageMinimum' => 'El día debe tener al menos 3 caracteres',
            'messageMaximum' => 'El día no puede tener más de 50 caracteres'
        ]));

        return $this->validate($validator);
    }

    public function columnMap()
    {
        return [
            'id' => 'id',
            'dia' => 'dia',
            'titulo_programa' => 'titulo_programa',
            'id_evento' => 'id_evento',
            'fecha_creacion' => 'fecha_creacion'
        ];
    }

    public static function findByEvento($idEvento)
    {
        return self::find([
            'conditions' => 'id_evento = :id_evento:',
            'bind' => ['id_evento' => $idEvento],
            'order' => 'dia ASC'
        ]);
    }
}
