<?php
namespace App\UseCases;

use DB;
use Exception;

class SimplesUseCase extends CsvChunkReader
{

   public $colunas = [
        'cnpj_basico',
        'opcao_pelo_simples',
        'data_opcao_pelo_simples',
        'data_exclusao_simples',
        'opcao_pelo_mei',
        'data_opcao_mei',
        'data_exclusao_mei',
    ];

    public function __invoke($file)
    {
        foreach ($this->readCsv($file, $this->colunas) as $chunk) {
            try{
            DB::table('simples')->upsert($chunk, ['cnpj_basico'], $this->colunas);
            }catch(Exception $e){
                    file_put_contents('/tmp/erro.txt', print_r($e->getMessage(),true).PHP_EOL.print_r($chunk,true));
                    return false;
            }
        }

    }

}
