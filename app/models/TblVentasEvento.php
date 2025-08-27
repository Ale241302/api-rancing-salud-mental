<?php

use Phalcon\Mvc\Model;
use Phalcon\Validation;
use Phalcon\Validation\Validator\PresenceOf;
use Phalcon\Validation\Validator\Numericality;

class TblVentasEvento extends Model
{
    public $id;
    public $id_evento;
    public $id_user;
    public $id_tarjeta_pago;
    public $cantidad_pago;
    public $fecha_creacion;

    public function initialize()
    {
        $this->setSource('tbl_ventas_evento');
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

        $validator->add('id_user', new PresenceOf([
            'message' => 'El ID del usuario es requerido'
        ]));

        $validator->add('id_user', new Numericality([
            'message' => 'El ID del usuario debe ser numérico'
        ]));

        $validator->add('id_tarjeta_pago', new PresenceOf([
            'message' => 'El ID de la tarjeta de pago es requerido'
        ]));

        $validator->add('id_tarjeta_pago', new Numericality([
            'message' => 'El ID de la tarjeta debe ser numérico'
        ]));

        $validator->add('cantidad_pago', new PresenceOf([
            'message' => 'La cantidad de pago es requerida'
        ]));

        $validator->add('cantidad_pago', new Numericality([
            'message' => 'La cantidad de pago debe ser numérica'
        ]));

        return $this->validate($validator);
    }

    public function columnMap()
    {
        return [
            'id' => 'id',
            'id_evento' => 'id_evento',
            'id_user' => 'id_user',
            'id_tarjeta_pago' => 'id_tarjeta_pago',
            'cantidad_pago' => 'cantidad_pago',
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

    public static function findByUser($idUser)
    {
        return self::find([
            'conditions' => 'id_user = :id_user:',
            'bind' => ['id_user' => $idUser],
            'order' => 'fecha_creacion DESC'
        ]);
    }

    public static function findByEventoAndUser($idEvento, $idUser)
    {
        return self::find([
            'conditions' => 'id_evento = :id_evento: AND id_user = :id_user:',
            'bind' => [
                'id_evento' => $idEvento,
                'id_user' => $idUser
            ],
            'order' => 'fecha_creacion DESC'
        ]);
    }
}
