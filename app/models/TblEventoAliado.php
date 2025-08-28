<?php

use Phalcon\Mvc\Model;
use Phalcon\Validation;
use Phalcon\Validation\Validator\PresenceOf;
use Phalcon\Validation\Validator\Numericality;

class TblEventoAliado extends Model
{
    public $id;
    public $id_evento;
    public $id_aliado;
    public $fecha_creacion;

    public function initialize()
    {
        $this->setSource('tbl_evento_aliado');
    }

    public function validation()
    {
        $validator = new Validation();

        $validator->add('id_evento', new PresenceOf([
            'message' => 'El ID del evento es requerido'
        ]));

        $validator->add('id_evento', new Numericality([
            'message' => 'El ID del evento debe ser numérico'
        ]));

        $validator->add('id_aliado', new PresenceOf([
            'message' => 'El ID del aliado es requerido'
        ]));

        $validator->add('id_aliado', new Numericality([
            'message' => 'El ID del aliado debe ser numérico'
        ]));

        return $this->validate($validator);
    }

    public function columnMap()
    {
        return [
            'id' => 'id',
            'id_evento' => 'id_evento',
            'id_aliado' => 'id_aliado',
            'fecha_creacion' => 'fecha_creacion'
        ];
    }

    public static function findByEvento($idEvento)
    {
        return self::find([
            'conditions' => 'id_evento = :id_evento:',
            'bind' => ['id_evento' => $idEvento],
            'order' => 'fecha_creacion DESC'
        ]);
    }

    public static function findByAliado($idAliado)
    {
        return self::find([
            'conditions' => 'id_aliado = :id_aliado:',
            'bind' => ['id_aliado' => $idAliado],
            'order' => 'fecha_creacion DESC'
        ]);
    }

    public static function findByEventoAndAliado($idEvento, $idAliado)
    {
        return self::find([
            'conditions' => 'id_evento = :id_evento: AND id_aliado = :id_aliado:',
            'bind' => [
                'id_evento' => $idEvento,
                'id_aliado' => $idAliado
            ],
            'order' => 'fecha_creacion DESC'
        ]);
    }

    public static function existeRelacion($idEvento, $idAliado)
    {
        return self::count([
            'conditions' => 'id_evento = :id_evento: AND id_aliado = :id_aliado:',
            'bind' => [
                'id_evento' => $idEvento,
                'id_aliado' => $idAliado
            ]
        ]) > 0;
    }
}
