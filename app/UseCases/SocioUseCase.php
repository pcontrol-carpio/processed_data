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
            // Visualização: Mostra cada linha que será inserida/atualizada
            foreach ($chunk as $linha) {
                echo 'Upserting: ' . json_encode($linha, JSON_UNESCAPED_UNICODE) . PHP_EOL;
            }
            // Tenta inserir ou atualizar os dados na tabela 'socio'
            // Se ocorrer um erro, ele será capturado e registrado no arquivo de log
            try{
            DB::table('socio')->upsert($chunk, ['cnpj_basico'], $this->colunas);
                echo '✅ OK - Chunk com ' . count($chunk) . ' registros inserido.' . PHP_EOL;
            }catch(Exception $e){
                    file_put_contents('/tmp/erro.txt', print_r($e->getMessage(),true).PHP_EOL.print_r($chunk,true));
                    dd($e);
            }
        }

    }

}
