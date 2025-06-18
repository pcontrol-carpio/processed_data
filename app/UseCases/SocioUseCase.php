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
        // Obtém o progresso do processamento do arquivo CSV
        $progress = DB::table('csv_progress')->where('filename', basename($file))->first();
        // Define o chunk inicial a partir do progresso salvo, ou começa do zero
        $startChunk = $progress->last_chunk ?? 0;
        // Lê o arquivo CSV em chunks, processando cada chunk
        foreach ($this->readCsv($file, $this->colunas, $startChunk) as $chunk) {
            try{
            DB::table('socio')->upsert($chunk, ['cnpj_basico'], $this->colunas);
                echo '✅ OK - Chunk com ' . count($chunk) . ' registros inserido.' . PHP_EOL;
            }catch(Exception $e){
                   echo '❌ Erro ao processar chunk: ' . $e->getMessage() . PHP_EOL;
                echo "Testando a linha que deu erro" . PHP_EOL;
                foreach ($chunk as $key => $linha) {
                    try {
                        echo 'Linha: ' . json_encode($linha, JSON_UNESCAPED_UNICODE) . PHP_EOL;
                        DB::table('socio')->upsert([$linha], ['cnpj_basico'], $this->colunas);
                    } catch (Exception $e) {
                        echo '❌ Erro ao inserir linha: ' . $e->getMessage() . PHP_EOL;
                        exit;
                    }
                    file_put_contents('/tmp/erro.txt', print_r($e->getMessage(), true) . PHP_EOL . print_r($chunk, true));
                    continue; // Continua para o próximo chunk
                }
            }
        }

    }

}
