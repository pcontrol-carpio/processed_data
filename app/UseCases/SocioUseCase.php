<?php
namespace App\UseCases;

use DB;
use Exception;

class SocioUseCase extends CsvChunkReader
{

   public $colunas = [
        'cnpj_basico',
        'identificador_socio',
        'nome_socio',
        'cnpj_cpf_socio',
        'qualificacao_socio',
        'data_entrada_sociedade',
        'pais',
        'representante_legal',
        'nome_representante',
        'qualificacao_representante_legal',
        'faixa_etaria',
    ];


    public function __invoke($file)
    {
        foreach ($this->readCsv($file, $this->colunas) as $chunk) {
            try{
            DB::table('socio')->upsert($chunk, ['cnpj_basico'], $this->colunas);
            }catch(Exception $e){
                    file_put_contents('/tmp/erro.txt', print_r($e->getMessage(),true).PHP_EOL.print_r($chunk,true));
                    return false;
            }
        }

    }

}
