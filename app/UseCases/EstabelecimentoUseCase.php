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

        $maxPlaceholders = 500000;
        $colunas         = count($this->colunas);
        $chunkSize       = floor($maxPlaceholders / $colunas);
        foreach ($this->readCsv($file, $this->colunas, $startChunk) as $chunkInsert) {

            // foreach (array_chunk($chunk, $chunkSize) as $chunkInsert) {
                try {
                    $inicio = microtime(true);
                    $newChunk = [];
                    foreach ($chunkInsert as &$linha) {
                        // Formata as datas
                        $linha['data_situacao_cadastral'] = $this->formatarData($linha['data_situacao_cadastral']);
                        $linha['data_inicio_atividade'] = $this->formatarData($linha['data_inicio_atividade']);
                        $linha['data_situacao_especial'] = $this->formatarData($linha['data_situacao_especial']);
                        $linha['correio_eletronico'] = strtolower($linha['correio_eletronico']);

                        $empresa = $this->pegarEmpresa($linha['cnpj_basico']);
                        if (empty($empresa)) {
                            echo PHP_EOL . "❌ Empresa não encontrada: " . $linha['cnpj_basico'] . PHP_EOL.json_encode($linha, JSON_UNESCAPED_UNICODE) . PHP_EOL;
                            continue;
                        }
                        $linha['empresa_id'] = ! empty($empresa) ? $empresa['id'] : null;
                        $linha['cnpj']       = "{$linha['cnpj_basico']}{$linha['cnpj_ordem']}{$linha['cnpj_dv']}";
                        $razaoSocial         = $empresa !== null ? $empresa['razao_social'] : null;
                        if (empty($linha['nome_fantasia'])) {
                            $linha['nome_fantasia'] = $razaoSocial;
                        }
                        $newChunk[] = $linha;
                    }

                    DB::table('estabelecimento')->upsert($newChunk, ['cnpj_basico'], $this->colunas);
                    $fim   = microtime(true);
                    $tempo = $fim - $inicio;
                    echo '✅ OK - Linha inserida com sucesso em ' . number_format($tempo, 2) . ' segundos.' . PHP_EOL;

                } catch (Exception $e) {
                    file_put_contents('/tmp/erro.txt', print_r($e->getMessage(), true));
                    echo '❌ Erro ao processar chunk: ' . $e->getMessage() . PHP_EOL;
                    echo "Testando a linha que deu erro" . PHP_EOL;

                    dd($e);

                    foreach ($newChunk as $key => $linhaInsert) {
                        try {
                            echo '✅ Linha: ' . json_encode($linhaInsert, JSON_UNESCAPED_UNICODE) . PHP_EOL.PHP_EOL;
                            DB::table('estabelecimento')->upsert([$linhaInsert], ['cnpj_basico'], $this->colunas);
                        } catch (Exception $e) {
                            echo '❌ Erro ao inserir linha: ' . $e->getMessage() . PHP_EOL;
                            exit;
                        }

                    }


                    return true;
                }
            // }
        }
        return true;

    }

}
