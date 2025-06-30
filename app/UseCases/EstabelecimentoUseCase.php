<?php
namespace App\UseCases;

use DB;
use Exception;

class EstabelecimentoUseCase extends CsvChunkReader
{

    public $colunas = [
        'cnpj_basico',
        'cnpj_ordem',
        'cnpj_dv',
        'matriz_filial',
        'nome_fantasia',
        'situacao_cadastral',
        'data_situacao_cadastral',
        'motivo_situacao_cadastral',
        'nome_cidade_exterior',
        'pais',
        'data_inicio_atividade',
        'cnae_fiscal_principal',
        'cnae_fiscal_secundaria',
        'tipo_logradouro',
        'logradouro',
        'numero',
        'complemento',
        'bairro',
        'cep',
        'uf',
        'municipio',
        'ddd_1',
        'telefone1',
        'ddd_2',
        'telefone2',
        'ddd_fax',
        'fax',
        'correio_eletronico',
        'situacao_especial',
        'data_situacao_especial',
    ];
    private function formatarData($data)
    {

        if ($data == null || $data == '') {
            return null;
        }

        // a data e 20151005 e eu preciso retornar 2015-10-05
        if (strlen($data) == 8) {
            return substr($data, 0, 4) . '-' . substr($data, 4, 2) . '-' . substr($data, 6, 2);
        }
        return (int) substr($data, 0, 4);
    }
    private function pegarEmpresa($baseCnpj)
    {
        return (array) DB::table('empresa')
            ->where('cnpj_basico', $baseCnpj)
            ->first();
    }

    private function pegarSimples($baseCnpj)
    {
        return collect(DB::table('simples')
                ->where('cnpj_basico', $baseCnpj)
                ->first())->toArray();
    }

    public function __invoke($file)
    {
        $progress   = DB::table('csv_progress')->where('filename', basename($file))->first();
        $startChunk = $progress->last_chunk ?? 0;

        $colunas         = $this->colunas;
        $numColunas      = count($colunas);

        foreach ($this->readCsv($file, $colunas, $startChunk) as $chunkInsert) {
            $inicio   = microtime(true);
            $newChunk = [];

            foreach ($chunkInsert as &$linha) {
                $linha['data_situacao_cadastral'] = $this->formatarData($linha['data_situacao_cadastral']);
                $linha['data_inicio_atividade']   = $this->formatarData($linha['data_inicio_atividade']);
                $linha['data_situacao_especial']  = $this->formatarData($linha['data_situacao_especial']);
                $linha['correio_eletronico']      = strtolower($linha['correio_eletronico']);

                $empresa = $this->pegarEmpresa($linha['cnpj_basico']);
                if (empty($empresa)) {
                    echo PHP_EOL . "❌ Empresa não encontrada: " . $linha['cnpj_basico'] . PHP_EOL . json_encode($linha, JSON_UNESCAPED_UNICODE) . PHP_EOL;
                    continue;
                }

                $linha['empresa_id'] = $empresa['id'] ?? null;
                $linha['cnpj']       = "{$linha['cnpj_basico']}{$linha['cnpj_ordem']}{$linha['cnpj_dv']}";
                if (empty($linha['nome_fantasia'])) {
                    $linha['nome_fantasia'] = $empresa['razao_social'] ?? '';
                }

                $newChunk[] = $linha;
            }

            if (empty($newChunk)) {
                continue;
            }

            // Gera SQL bruta para inserção com upsert
            $columns      = implode(', ', array_map(fn($col) => "`$col`", $colunas));
            $placeholders = '(' . implode(', ', array_fill(0, $numColunas, '?')) . ')';
            $valuesSql    = implode(', ', array_fill(0, count($newChunk), $placeholders));
            $bindings     = [];

            foreach ($newChunk as $row) {
                foreach ($colunas as $coluna) {
                    $bindings[] = $row[$coluna] ?? null;
                }
            }

            // Define a cláusula ON DUPLICATE KEY UPDATE
            $updates = implode(', ', array_map(fn($col) => "`$col`=VALUES(`$col`)", $colunas));
            $sql     = "INSERT INTO `estabelecimento` ($columns) VALUES $valuesSql ON DUPLICATE KEY UPDATE $updates";

            try {
                DB::statement($sql, $bindings);
                $fim = microtime(true);
                echo '✅ Inserido com sucesso em ' . number_format($fim - $inicio, 2) . ' segundos.' . PHP_EOL;
            } catch (Exception $e) {
                file_put_contents('/tmp/erro.txt', print_r($e->getMessage(), true) . PHP_EOL . print_r($bindings, true));
                echo '❌ Erro ao processar chunk: ' . $e->getMessage() . PHP_EOL;
            }
        }

        return true;
    }

}
