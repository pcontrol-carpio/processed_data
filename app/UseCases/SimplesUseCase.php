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
           $progress = DB::table('csv_progress')->where('filename', basename($file))->first();
        $startChunk = $progress->last_chunk ?? 0;
        foreach ($this->readCsv($file, $this->colunas, $startChunk) as $chunk) {

             foreach ($chunk as $linha) {
                echo 'Upserting: ' . json_encode($linha, JSON_UNESCAPED_UNICODE) . PHP_EOL;
            }
            try{
            DB::table('simples')->upsert($chunk, ['cnpj_basico'], $this->colunas);
                 echo '✅ OK - Chunk com ' . count($chunk) . ' registros inserido.' . PHP_EOL;
            }catch(Exception $e){
                    file_put_contents('/tmp/erro.txt', print_r($e->getMessage(),true).PHP_EOL.print_r($chunk,true));
                    dd($e);
            }
        }

        return true;

    }

}
