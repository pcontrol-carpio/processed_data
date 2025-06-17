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

            // Visualização: Mostra cada linha que será inserida/atualizada
            foreach ($chunk as $key => $linha) {
                echo 'Upserting: ' . json_encode($linha, JSON_UNESCAPED_UNICODE) . PHP_EOL;
                foreach ($linha as $key => $value) {

                    if ($key === 'porte') {
                        // Verifica se o valor é numérico e converte para inteiro, caso contrário, define como null
                        if (is_numeric($value)) {
                            $chunk[$key][$key] = (int) $value;
                        } else {
                            $chunk[$key][$key] = null;
                        }
                    }

                }
            }
            try {
                DB::table('empresa')->upsert($chunk, ['cnpj_basico'], $this->colunas);

                echo '✅ OK - Chunk com ' . count($chunk) . ' registros inserido.' . PHP_EOL;

            } catch (\Exception $e) {
                file_put_contents(
                    '/tmp/erro.txt',
                    print_r($e->getMessage(), true) . PHP_EOL . print_r($chunk, true),
                    FILE_APPEND
                );
                dd($e); // Exibe o erro no terminal e para a execução
            }
        }

    }

}
