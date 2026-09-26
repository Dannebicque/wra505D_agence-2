<?php

namespace App\Repository\Traits;

trait FindAllByOldIdArrayTrait
{
    /** @return array<int|string, mixed> */
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
