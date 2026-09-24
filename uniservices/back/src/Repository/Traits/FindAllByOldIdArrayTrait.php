<?php

namespace App\Repository\Traits;

trait FindAllByOldIdArrayTrait
{
    public function findAllByOldIdArray(): array
    {
        $datas = $this->findAll();
        $result = [];

        foreach ($datas as $data) {
            $result[$data->getOldId()] = $data;
        }

        return $result;
    }
}
