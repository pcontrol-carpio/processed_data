<?php
namespace App\UseCases;

use DB;

class EmpresaUseCase extends CsvChunkReader
{

    public $colunas = [
        'cnpj_basico',
        'razao_social',
        'natureza_juridica',
        'qualificacao_responsavel',
        'capital_social',
        'porte',
        'ente_federativo',
    ];
    public function __invoke($file)
    {
        foreach ($this->readCsv($file, $this->colunas) as $chunk) {
            try{
            DB::table('empresa')->upsert($chunk, ['cnpj_basico'], $this->colunas);
            }catch(\Exception $e){
                    file_put_contents('/tmp/erro.txt', print_r($e->getMessage(),true).PHP_EOL.print_r($chunk,true));
              dd($e);exit;

                    // return false;
            }
        }

    }

}
