<?php
namespace App\UseCases;

use DB;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class CompleteDownloadUseCase
{


    public function __invoke($folder, $file): int
    {


        return  DB::table('completados')
            ->where('processado_id', $folder->id)
            ->where('arquivo', $file)->update(['concluido_em' => now()]);

    }

}
