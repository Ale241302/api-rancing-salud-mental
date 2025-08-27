<?php

use Phalcon\Mvc\Model;
use Phalcon\Validation;
use Phalcon\Validation\Validator\StringLength;
use Phalcon\Validation\Validator\PresenceOf;

class TblTarjetaPago extends Model
{
    public $id;
    public $id_user;
    public $numero_tarjeta;
    public $vencimiento_tarjeta;
    public $cvc_tarjeta;
    public $nombre_tarjeta;
    public $fecha_creacion;

    public function initialize()
    {
        $this->setSource('tbl_tarjeta_pago');
    }

    public function validation()
    {
        $validator = new Validation();

        $validator->add('numero_tarjeta', new PresenceOf([
            'message' => 'El número de tarjeta es requerido'
        ]));

        $validator->add('numero_tarjeta', new StringLength([
            'min' => 13,
            'max' => 19,
            'messageMinimum' => 'El número de tarjeta debe tener al menos 13 dígitos',
            'messageMaximum' => 'El número de tarjeta no puede tener más de 19 dígitos'
        ]));

        $validator->add('vencimiento_tarjeta', new PresenceOf([
            'message' => 'La fecha de vencimiento es requerida'
        ]));

        $validator->add('vencimiento_tarjeta', new StringLength([
            'min' => 5,
            'max' => 5,
            'messageMinimum' => 'La fecha de vencimiento debe tener formato MM/YY',
            'messageMaximum' => 'La fecha de vencimiento debe tener formato MM/YY'
        ]));

        $validator->add('cvc_tarjeta', new StringLength([
            'min' => 3,
            'max' => 4,
            'messageMinimum' => 'El CVC debe tener al menos 3 dígitos',
            'messageMaximum' => 'El CVC no puede tener más de 4 dígitos'
        ]));

        $validator->add('nombre_tarjeta', new StringLength([
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
            'id_user' => 'id_user',
            'numero_tarjeta' => 'numero_tarjeta',
            'vencimiento_tarjeta' => 'vencimiento_tarjeta',
            'cvc_tarjeta' => 'cvc_tarjeta',
            'nombre_tarjeta' => 'nombre_tarjeta',
            'fecha_creacion' => 'fecha_creacion'
        ];
    }

    public static function findByUser($idUser)
    {
        return self::find([
            'conditions' => 'id_user = :id_user:',
            'bind' => ['id_user' => $idUser],
            'order' => 'fecha_creacion DESC'
        ]);
    }
}
