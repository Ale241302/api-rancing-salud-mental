<?php

use Phalcon\Mvc\Model;
use Phalcon\Validation;

class TblEventoPonente extends Model
{
    public $id;
    public $id_evento;
    public $id_ponente;
    public $fecha_creacion;

    public function initialize()
    {
        $this->setSource('tbl_evento_ponente');
    }

    public function validation()
    {
        $validator = new Validation();

        // Aquí puedes agregar validaciones si necesitas
        // Por ejemplo, verificar que no se duplique la relación evento-ponente

        return $this->validate($validator);
    }

    public function columnMap()
    {
        return [
            'id' => 'id',
            'id_evento' => 'id_evento',
            'id_ponente' => 'id_ponente',
            'fecha_creacion' => 'fecha_creacion'
        ];
    }

    public static function findByEvento($idEvento)
    {
        return self::find([
            'conditions' => 'id_evento = :id_evento:',
            'bind' => ['id_evento' => $idEvento]
        ]);
    }

    public static function findByPonente($idPonente)
    {
        return self::find([
            'conditions' => 'id_ponente = :id_ponente:',
            'bind' => ['id_ponente' => $idPonente]
        ]);
    }

    public static function findEventoPonente($idEvento, $idPonente)
    {
        return self::findFirst([
            'conditions' => 'id_evento = :id_evento: AND id_ponente = :id_ponente:',
            'bind' => [
                'id_evento' => $idEvento,
                'id_ponente' => $idPonente
            ]
        ]);
    }
}
