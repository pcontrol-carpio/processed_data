<?php
namespace App\UseCases;

use DB;
use Exception;

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
        $progress = DB::table('csv_progress')->where('filename', basename($file))->first();
        $startChunk = $progress->last_chunk ?? 0;
        foreach ($this->readCsv($file, $this->colunas, $startChunk) as $chunk) {
            try {
                // Visualização: Mostra cada linha que será inserida/atualizada
                foreach ($chunk as $key => $linha) {
                    foreach ($linha as $key => $value) {
                        if ($key === 'porte') {
                            // Verifica se o valor é numérico e converte para inteiro, caso contrário, define como null
                            if (empty($value)) {
                                $chunk[$key][$key] = null;
                            }
                        }

                        if ($key === 'ente_federativo') {
                            // Verifica se o valor é numérico e converte para inteiro, caso contrário, define como null
                            if (empty($value)) {
                                $chunk[$key][$key] = '';
                            }
                        }

                    }
                }
                $inicio = microtime(true);
                DB::table('empresa')->upsert($chunk, ['cnpj_basico'], $this->colunas);
                $fim = microtime(true);
                $tempo = $fim - $inicio;
                echo '✅ OK - Chunk com ' . count($chunk) . ' em ' .basename($file). ' registros inserido.' . number_format($tempo, 2) . ' segundos.' . PHP_EOL;

            } catch (Exception $e) {
                file_put_contents(
                    '/tmp/erro.txt',
                    print_r($e->getMessage(), true) . PHP_EOL . print_r($chunk, true),
                    FILE_APPEND
                );
                dd($e); // Exibe o erro no terminal e para a execução
            }
        }
            echo '✅ Todos os registros foram processados com sucesso.' . PHP_EOL;
        // Retorna true para indicar que o processamento foi concluído com sucesso
        echo '✅ Processamento concluído.' . PHP_EOL;
        return true;

    }

}
