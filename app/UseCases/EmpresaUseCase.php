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
        $progress   = DB::table('csv_progress')->where('filename', basename($file))->first();
        $startChunk = $progress->last_chunk ?? 0;
        echo PHP_EOL;
        echo "Iniciando processamento do arquivo: {$file}" . PHP_EOL;
        foreach ($this->readCsv($file, $this->colunas, $startChunk) as $chunk) {
            try {
                // Visualização: Mostra cada linha que será inserida/atualizada
                foreach ($chunk as $key => &$linha) {

                    dd($linha);
                    foreach ($linha as $key => &$value) {
                        if ($key === 'porte') {
                            // Verifica se o valor é numérico e converte para inteiro, caso contrário, define como null
                            if (empty($value)) {
                                $linha[$key] = null;
                            }
                        }

                        if ($key === 'ente_federativo') {
                            // Verifica se o valor é numérico e converte para inteiro, caso contrário, define como null
                            if (empty($value)) {
                                $linha[$key] = (string) '';
                            }
                        } else {
                            // $linha[$key] = (string) $value;
                        }

                    }

                }

                DB::table('empresa')->upsert($chunk, ['cnpj_basico'], $this->colunas);
                echo "Chunk inserido com sucesso" . PHP_EOL;

            } catch (Exception $e) {
              dd($e);
                file_put_contents('/tmp/erro.txt', print_r($e->getMessage(), true) . PHP_EOL);
                exit;

            }
            // foreach ($chunk as $key => $linha) {

            //     if(empty($linha['porte'])){
            //         $linha['porte'] = null;

            //     }
            //     if(empty($linha['ente_federativo'])){
            //         $linha['ente_federativo'] = null;

            //     }

            //     DB::table('empresa')->upsert([$linha], ['cnpj_basico'], $this->colunas);
            //     echo "✅ {$linha['cnpj_basico']} -  {$linha['razao_social']} -  Inserido com sucesso." . PHP_EOL;
            //     file_put_contents('/tmp/empresas.txt', "{$linha['cnpj_basico']} -  {$linha['razao_social']} -  Inserido com sucesso." . PHP_EOL,FILE_APPEND);

            // }
        }

        echo '✅ Todos os registros foram processados com sucesso.' . PHP_EOL;
        // Retorna true para indicar que o processamento foi concluído com sucesso
        echo '✅ Processamento concluído.' . PHP_EOL;
        return true;
    }
}
